<?php

namespace App\Services\MysqlDataSync\ningxia;

use App\Model\WJZ;
use App\Model\Yzb;
use Carbon\Carbon;
use App\Model\SSCZ;
use App\Model\SSSQ;
use App\Model\YCCZ;
use App\Model\Bllb1;
use App\Model\Staff;
use App\Model\ZY_SS;
use App\Model\Appeal;
use App\Model\Bllb288;
use App\Model\Bllb292;
use App\Model\Bllb303;
use App\Model\Setting;
use App\Model\SM_SSAP;
use App\Model\Symptom;
use App\Model\ZY_BRRY;
use App\Model\ZY_HCMX;
use App\Model\ZY_RYZD;
use App\Model\Department;
use App\Model\YS_ZY_HZSQ;
use App\Model\YS_ZY_HZYJ;
use App\Model\Bllb294_295;
use App\Model\Mblb44;
use App\Model\CaseQuality;
use App\Model\DataSyncLog;
use App\Model\EMR_BL_BL01;
use App\Model\EMR_BL_BLSY;
use App\Model\EMR_BL_BLXG;
use App\Model\PatientInfo;
use App\Model\RuleWordMap;
use App\Model\CaseQualityZm;
use App\Model\MainOperation;
use App\Model\MedicinalInfo;
use App\Model\PatientInfoV2;
use App\Services\CaseService;
use App\Model\V_JMGS_YMresult;
use App\Model\BigModelTemplate;
use App\Model\SecondaryOperation;
use App\Model\BLLB1 as ModelBLLB1;
use App\Model\Mblb30304;
use App\Model\Mblb304;
use App\Model\Mblb42;
use App\Model\Mblb82;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Pheanstalk\Command\TubeCommand;
use App\Model\PatientInfoOperationV2;
use App\Services\BlDataFormatService;
use Illuminate\Validation\Rule;
use PHPUnit\Framework\Constraint\IsFalse;

class BanhzkgzService
{
    public $is_sz = 0; // 是否是事中质控
    public $yzzt = [0, 1, 5];
    // 时效性
    public $insertData = [];

    // 内涵质控
    protected $insertNhzkData = [];

    public $caseService;
    public $diffHoure;

    public $rulefirst = '';

    public function __construct()
    {
        $setting = Setting::query()->where('name', '=', 'diff_houre')->get()->toArray();
        $this->diffHoure = (int)$setting[0]['content'];
        $this->caseService = new CaseService();

        $rulefirst = RuleWordMap::query()->where('id', '=', 22)->value('keyword');
        $this->rulefirst = !empty($rulefirst) ? $rulefirst : 'CJSJ';
    }

    /**
     * 质控控规则处理（时效性）
     * @param $caseRule
     * @param $info
     * @return true
     */
    public function zkgzHandle($caseRule, $info, $isSz = 0)
    {
        $ZYH = $info['MED_REC_ID'];

        // 排除新生儿
        $keyword20047 = RuleWordMap::getArrayById(20047);
        if ($keyword20047) {
            $yzb = Yzb::query()->where('ZYH', $ZYH)->get(['YZMC'])->toArray();
            foreach ($yzb as $v) {
                foreach ($keyword20047 as $keyword) {
                    if ($keyword !== '' && strpos($v['YZMC'], $keyword) !== false) {
                        return true;
                    }
                }
            }
        }

        //rule8099
        $rule8099 = RuleWordMap::getArrayById(8099);
        if (!empty($rule8099)) {
            $brks = ZY_BRRY::query()->where('ZYH', $ZYH)->value('BRKS');
            if (!empty($brks)) {
                $depName = Department::query()->where('dep_id', $brks)->value('dep_name');
                if (!empty($depName)) {
                    // 如果科室名称包含 rule8099 中的任何一个，则不质控
                    foreach ($rule8099 as $rule) {
                        if ($rule !== '' && strpos($depName, $rule) !== false) {
                            return true;
                        }
                    }
                }
            }
        }
        $this->insertData = [];
        // 要调用的方法
        $methodList = [
            80 => 'rule80',     // 病案首页
            95 => 'rule95',     // 病案首页
            96 => 'rule96',     // 出院记录
            99 => 'rule99',     // 入院记录
            97 => 'rule97',     // 24小时出入院记录
            278 => 'rule278', // 病案首页，死亡患者在死亡后1周内要完成病案首页
            98 => 'rule98', // 患者死亡后24小时内未完成死亡记录
            1000 => 'rule1000', //24小时入院死亡记录
            101 => 'rule101', // 患者入院后，8小时内未完成【首次病程记录】
            102 => 'rule102', // 开具CT检查的医嘱，未在CT检查报告单结果出具后的72小时内完成相关病程记录
            103 => 'rule103', // 开具MR检查的医嘱（MR、磁共振、DWI、TRICKS、SWI），未在MR检查报告单结果出具后的72小时内完成相关病程记录
            109 => 'rule109',   // 有抗菌药，开嘱时间+24小时 内要有病程记录
            110 => 'rule110',   // 有化疗药，开嘱时间+24小时 内要有病程记录
            219 => 'rule219', //
            260 => 'rule260', // 【手术同意书】在【手术医嘱】之前
            261 => 'rule261', // 术前讨论时间早于手术同意书签署（医师签字时间）且 术前讨论时间早于手术医嘱开嘱时间
            113 => 'rule113', // 副高或正高级职称未体现一周2次查房
            114 => 'rule114', // 护士分床后48小时内，病程记录中，需要有一个 副高或正高签名（首次病程除外）
            133 => 'rule133', // 医嘱名称含"病危"，每天1次上级医师查房
            134 => 'rule134', // 医嘱名称含"病重"，两天1次上级医师查房
            132 => 'rule132', // 稳定患者3天一次查房
            420 => 'rule420', // 新病人入院后没有连记3天
            430 => 'rule430', // 转科没有连记3天
            433 => 'rule433', // 手术前一天无病程
            434 => 'rule434', // 择期手术后3天内无术者查房记录
            435 => 'rule435', // 手术后3天未连续记录病程记录
            448 => 'rule448', // 日常病程记录--重要检查（细菌培养）结果病程无反映
            462 => 'rule462', // 日常病程记录--出院当天或出院前一天无病程记录
            488 => 'rule488', // 抢救记录--无主治或主治以上医师参与抢救或无审核签名
            502 => 'rule502', // 有创操作记录--操作记录无操作时间
            522 => 'rule522', // 会诊申请单无会诊医师签名
            // 503 => 'rule503', // 有创操作记录--操作记录无操作名称
            513 => 'rule513', // 会诊记录--普通会诊记录未在48小时内完成
            519 => 'rule519', // 无申请会诊医师姓名
            542 => 'rule542', // 术前小结无拟施麻醉方式
            548 => 'rule548', // 术前小结无手术者术前查看患者相关情况等
            551 => 'rule551', // 术后首程无手术起止时间
            552 => 'rule552', // 术后首程无麻醉方式
            553 => 'rule553', // 术后首程无手术方式
            554 => 'rule554', // 术后首程复制手术记录
            557 => 'rule557', // 术后首程无术后诊断
            563 => 'rule563', // 术后首程无术后病情告知
            565 => 'rule565', // 术后首程无医师签名
            566 => 'rule566', // 三级以上手术无术前讨论记录，术前1周内进行讨论（急诊例外）
            567 => 'rule567', // 四级手术无多学科讨论记录（急诊例外）
            570 => 'rule570', // 术前讨论记录无讨论日期
            571 => 'rule571', // 术前讨论记录无术前准备情况
            577 => 'rule577', // 术前讨论无记录者签名
            584 => 'rule584', // 手术记录内容无手术日期
            585 => 'rule585', // 手术记录内容无术前诊断
            586 => 'rule586', // 手术记录内容无术后诊断
            587 => 'rule587', // 手术记录内容无手术名称
            588 => 'rule588', // 手术记录内容无术者姓名
            589 => 'rule589', // 手术记录内容无助手姓名
            590 => 'rule590', // 手术记录内容无麻醉师姓名
            591 => 'rule591', // 手术记录内容无麻醉方法
            592 => 'rule592', // 手术记录内容无术中发现
            593 => 'rule593', // 手术记录内容无手术经过
            595 => 'rule595', // 手术记录内容无切下标本处理
            641 => 'rule641', // 死亡记录无入院情况
            642 => 'rule642', // 死亡记录无入院诊断
            648 => 'rule648', // 死亡记录无死亡诊断
            649 => 'rule649', // 死亡记录无死亡时间
            651 => 'rule651', // 死亡记录无主管医师签名
            1001 => 'rule1001', // 术前小结及术前讨论结论记录
            1002 => 'rule1002', // 术后首次病程记录
            1003 => 'rule1003', // 手术记录
            1004 => 'rule1004', // 术者术前24小时查房
            1005 => 'rule1005', // 术者术后24小时查房
            1006 => 'rule1006', // 医嘱名称含"抢救"，开嘱时间后6小时内需有抢救记录
            1007 => 'rule1007', // 无术后第2天病程记录
            1008 => 'rule1008', // 无术后第3天病程记录
            1009 => 'rule1009', // 出院前上级医师查房记录
            1010 => 'rule1010', // 患者死亡后7天内未完成死亡病例讨论结论记录
            1011 => 'rule1011', // 危急值接收后24小时内未完成危急值记录
            1012 => 'rule1012', // 输血结束后24小时内未完成输血记录
            1013 => 'rule1013', // 住院三十天以上未书写阶段小结
            1014 => 'rule1014', // 转出前未完成转出记录
            1015 => 'rule1015', // 转入后未完成转入记录
            1016 => 'rule1016', // 普通会诊未在发出后24小时内完成
            1017 => 'rule1017', // 紧急会诊未在发出后10分钟内到达
            1018 => 'rule1018', // 普通/急会诊结束后24小时内未完成会诊记录
            1021 => 'rule1021', // 抢救记录签名超时
            1022 => 'rule1022', // 日间病历记录签名超时
            1027 => 'rule1027', // 出院记录中入院日期与病案首页中的不一致
            1028 => 'rule1028', // 出院记录中出院日期与病案首页中的不一致
            1029 => 'rule1029', // 入院记录中入院日期与病案首页中的不一致
            1030 => 'rule1030', // 入院记录【主诉】与【现病史】中，症状不一致
            1031 => 'rule1031', // 手术记录与手麻系统的手术时间不一致
            1032 => 'rule1032', // 会诊记录中缺少会诊目的
            1033 => 'rule1033', // 手术记录中术者签名与手术记录-术者不一致
            1034 => 'rule1034', // 抢救记录中描述的关键时间节点未精确到分
            1035 => 'rule1035', // 抢救记录未记录参加抢救的医护人员
            1036 => 'rule1036', // 会诊记录中缺少会诊意见
            1037 => 'rule1037', // 既往史中药物过敏史与病案首页药物过敏史不一致
            1038 => 'rule1038', // 【首次病程记录 / 病例特点第2条】与【入院记录 / 现病史】 85%重复
            1039 => 'rule1039', // 出院记录中"住院天数"与病案首页不一致
            1040 => 'rule1040', // 术后首次病程中手术简要经过与手术记录内容重复≥90%
            1041 => 'rule1041', // 术后首次病程中手术简要经过与手术记录内容重复<=30%
            1045 => 'rule1045', //术后首次病程与手术记录中的手术名称不一致
            1047 => 'rule1047', // 护士分床后48小时内，病程记录中，需要有一个 副高或正高签名（首次病程除外）
            1048 => 'rule1048', // 副高或正高级职称未体现一周2次查房
            1049 => 'rule1049', // 医嘱名称含"病危"，每天1次上级医师查房 新（查blmc）
            1050 => 'rule1050', // 医嘱名称含"病重"，每两天1次上级医师查房 新（查blmc）
            1051 => 'rule1051', // 出院前上级医师查房记录 新（查blmc）
            1052 => 'rule1052', // 上级医师查房记录中的【上级医师】未签名
            1053 => 'rule1053', // 病程记录中的【业务时间】与【病历标题时间】前后顺序不一致
            1023 => 'rule1023', // 首次病程【病例特点】与入院记录【体格检查】中的T（体温）值不一致
            1054 => 'rule1054', // 首次病程【病例特点】与入院记录【体格检查】中的BP（血压）值不一致
            1055 => 'rule1055', // 首次病程【病例特点】与入院记录【体格检查】中的R（呼吸）值不一致
            1056 => 'rule1056', // 首次病程【病例特点】与入院记录【体格检查】中的P（脉搏）值不一致
            1024 => 'rule1024', // 出院记录【入院情况】与入院记录【体格检查】中的T（体温）值不一致
            1057 => 'rule1057', // 出院记录【入院情况】与入院记录【体格检查】中的BP（血压）值不一致
            1058 => 'rule1058', // 出院记录【入院情况】与入院记录【体格检查】中的R（呼吸）值不一致
            1059 => 'rule1059', // 出院记录【入院情况】与入院记录【体格检查】中的P（脉搏）值不一致
            1060 => 'rule1060', // 术者术后24小时查房签名
            1061 => 'rule1061', // 术后第2天病程记录超时
            1062 => 'rule1062', // 术后第3天病程记录超时
            1063 => 'rule1063', // 具"培养"的医嘱，未在检验报告单结果出具后的72小时内完成相关病程记录
            1064 => 'rule1064', // 操作完成后未在24小时内完成操作记录
            1213 => 'rule1213', // 首次病程时间不应早于入院时间
            1214 => 'rule1214', // 抢救记录时间不能早于抢救结束时间
            1215 => 'rule1215', // 死亡记录死亡时间与死亡讨论记录时间不一致
            1216 => 'rule1216', // 病程记录时间应在入院时间和出院时间或当天之间
            1218 => 'rule1218', // 未体现三级医师查房
            1219 => 'rule1219', // 病程记录中未记录标题医师姓名
            1220 => 'rule1220', // 标题无医师姓名及职称
            1221 => 'rule1221', // 病程记录未签名
            1222 => 'rule1222', // 疑难病例讨论结论记录未签名
            1223 => 'rule1223', // 疑难病例讨论结论记录--标题时间早于讨论时间
            1224 => 'rule1224', // 会诊记录中申请单签名医师未签名
            1225 => 'rule1225', // 会诊记录病程中会诊医师姓名、职称与会诊申请单中会诊医师不一致
            1226 => 'rule1226', // 抢救记录--标题时间早于抢救结束时间
            1227 => 'rule1227', // 无术前小结
            1228 => 'rule1228', // 新增规则：疑难病例讨论结论记录--无主持人签名（病区主任签名）
            1229 => 'rule1229', // 抢救记录--由未参加抢救的医师书写
            1230 => 'rule1230', // 抢救记录--无主持抢救的医师审核签名（默认职称最高的医师、抢救者中第一位医师）
            1231 => 'rule1231', // 术前小结--术前讨论时间晚于标题时间或与标题时间一致
            1232 => 'rule1232', // 术前小结--无术中注意事项
            1233 => 'rule1233', // 手术记录--表格中无手术者姓名
            1234 => 'rule1234', // 手术记录--表格中无手术名称
            1235 => 'rule1235', // 手术记录--表格中无麻醉方法
            1236 => 'rule1236', // 手术记录--无手术经过
            1237 => 'rule1237', // 有标本送检时，件数不能为0
            1238 => 'rule1238', // 术前小结--无术后注意事项
            1239 => 'rule1239', // 出院记录--无入院情况
            1240 => 'rule1240', // 出院记录--无诊疗经过
            1241 => 'rule1241', // 出院记录--无出院情况
            1242 => 'rule1242', // 死亡记录--无诊疗经过
            1243 => 'rule1243', // 死亡记录--无死亡原因
            1244 => 'rule1244', // 死亡病例讨论结论记录中死亡原因与死亡记录中的死亡原因不一致
            1245 => 'rule1245', // 死亡病例讨论结论记录中死亡诊断与死亡记录中的死亡诊断不一致
            1246 => 'rule1246', // 死亡病例讨论结论记录中死亡诊断未包括死亡原因
            1247 => 'rule1247', // 死亡病例讨论结论记录--无主持人签名（病区主任签名）
            1248 => 'rule1248', // 危急值记录--标题时间早于危急值接收、处置时间
            1249 => 'rule1249', // 月经生育史--女性生育史未使用月经表达式记录
            1250 => 'rule1250', // 首次病程记录--病例特点1：出现"否认"疾病史
            1251 => 'rule1251', // 有创操作记录--标题时间早于操作时间
            1252 => 'rule1252', // 输血记录--标题时间早于输血结束时间
            1253 => 'rule1253', // 术前小结--术者未参加术前讨论
            1254 => 'rule1254', // 术前小结--审核签字的术者与小结中术者不一致
            1255 => 'rule1255', // 缺术后首程
            1256 => 'rule1256', // 术后首程--非参加手术医师书写
            1257 => 'rule1257', // 抢救记录--有抢救记录无抢救医嘱
            1258 => 'rule1258', // 术前小结--无术者审核签字
            1259 => 'rule1259', // 手术同意书签署时间晚于术前小结及术前讨论结论记录时间
            1265 => 'rule1265', // 抢救时间超长
            1266 => 'rule1266', // 手术记录报告时间应早于记录时间，操作时间晚于记录时间一天
            1267 => 'rule1267', // 现病史描述缺少诊疗过程，以及加重一周的症状和程度
            1268 => 'rule1268', // 首次病程记录诊断依据年龄错误
            1269 => 'rule1269', // 出院时间在入院时间之前
            1270 => 'rule1270', // 诊断续页未签名 bllb=999999999
            1271 => 'rule1271', // 手术风险评估表未签名 bllb=329
            1272 => 'rule1272', // 手术安全核查表未签名 bllb=329
            1273 => 'rule1273', // 死亡医学推断书未签名
            1274 => 'rule1274', //  四级手术术前多学科讨论记录参加科室少于3个，不符合多学科讨论要求
            1275 => 'rule1275', //  术后24小时未完成有创操作记录
            1276 => 'rule1276', // 【首次病程记录】首次签名时间超8小时
            1277 => 'rule1277', // 【首次病程记录】未签名
            1278 => 'rule1278', // 【入院记录】首次签名时间超24小时
            1279 => 'rule1279', // 【入院记录】未签名
            1280 => 'rule1280', // 患者自动出院或转院同意书，患者未签字
            1281 => 'rule1281', // 【首次病程记录】鉴别诊断和依据书写过于简单
            1282 => 'rule1282', // 病危或病重患者，首次病程记录后2小时内无上级医师查房记录
            1283 => 'rule1283', // 【自动出院或转院同意书】，患者未签字
            1284 => 'rule1284', // 【首次病程记录】无诊断依据或过于简单
            1285 => 'rule1285', // 【首次病程记录】无诊疗计划
            1286 => 'rule1286', //病危患者无病危告知书
            1287 => 'rule1287', //病危告知书无患方签名
            1288 => 'rule1288', //为非执业医师签名
            1289 => 'rule1289', //  无输血前9项检测报告
            1290 => 'rule1290', //  无化学治疗知情同意书
            1291 => 'rule1291', //  化学治疗知情同意书无患方签字
            1292 => 'rule1292', //  化学治疗知情同意书无医师签字
            1293 => 'rule1293', //  无放射治疗知情同意书
            1294 => 'rule1294', //  放射治疗知情同意书无患方签字
            1295 => 'rule1295', //  放射治疗知情同意书无医师签字
            1296 => 'rule1296', //  抢救记录次数与医嘱不符
            1297 => 'rule1297', //  医嘱由非执业医师开具
            1298 => 'rule1298', //  患者死亡后，24小时内未完成【24小时入院死亡记录】
            1299 => 'rule1299', //  患者死亡后，【24小时入院死亡记录】超24小时
            1300 => 'rule1300', //  【24小时入院死亡记录】未签名
            1305 => 'rule1305', //  患者出院后 ，24小时内完成【24小时入出院记录】
            1301 => 'rule1301', //  患者出院后 ，24小时内完成【24小时入出院记录】超24小时 
            1302 => 'rule1302', //  患者入院48小时内无主任/主治医师查房记录
            1303 => 'rule1303', //  患者入院72小时内无主任（副主任）医师查房记录
            1304 => 'rule1304', //  【24小时入出院记录】未签名
            10342 => 'rule10342', //  手麻中有手术，首页中手术名称不能为空
            10344 => 'rule10344', //  【首次病程记录】无初步诊断；
            10345 => 'rule10345', //   入院记录的入院时间大于记录时间
            10349 => 'rule10349', //   死亡记录死亡时间和死亡讨论记录死亡时间不一致
            544 => 'rule544', //   手术前无手术医嘱
            10355 => 'rule10355', //   疑难病例讨论无讨论日期
            10356 => 'rule10356', //   疑难病例讨论无主持人姓名
            10357 => 'rule10357', //   疑难病例讨论无参加者姓名
            10358 => 'rule10358', //   疑难病例讨论无记录者签名
            10359 => 'rule10359', //   出院记录无入院诊断
        ];
        // 大模型质控，重新质控过程中不删除大模型的质控结果
        $appealRuleIds = [];
        $templates = BigModelTemplate::query()->get()->toArray();
        $bitModelRuleIds = array_column($templates, "rule_id");
        $appeal = Appeal::query()
            ->where(["quality_type" => 2, "type" => 2, "ZYH" => $ZYH])
            ->whereIn("status", ['1', '3'])
            ->get(["error_id"])
            ->toArray();
        if ($appeal) {
            $appealRuleIds = array_column($appeal, "error_id");
            $appealRuleIds = array_merge($appealRuleIds, $bitModelRuleIds);
        }
        // 删除历史质控数据
        $appealRuleIds = array_values(array_filter($appealRuleIds));

        foreach ($methodList as $ruleId => $method) {
            // 1、规则关闭则不进行质控
            // 2、申诉中或者审核通过的规则不进行质控
            // 3、事中质控如果规则没有打开同步事中质控，则不进行质控
            if (empty($caseRule[$ruleId]['status']) || in_array($ruleId, $appealRuleIds) || (($isSz == 1 || $isSz == 4) && $caseRule[$ruleId]['is_shizhong'] == 0)) {
                continue;
                //Log::info('ruleids---->', $ruleId);
            }

            DataSyncLog::addData(['zyh' => $ZYH, 'content' => '开始质控规则-' . $ruleId]);
            // 调用质控规则
            if (method_exists($this, $method)) {
                $this->$method($caseRule, $ruleId, $ZYH);
            }
        }

        $insertData = $this->insertData;
        $appealids = [];
        if (!empty($insertData)) {
            // 同步修改病例数据的缺陷状态
            foreach ($insertData as $v) {
                if (!isset($v['JZHM']) || !isset($v['rule_id'])) {
                    continue;
                }
                if ($isSz == 99 || $isSz == 991) {
                    CaseQualityZm::where('JZHM', $v['JZHM'])->where('rule_id', $v['rule_id'])->delete();
                    CaseQualityZm::addData($v);
                } else {
                    CaseQuality::where('JZHM', $v['JZHM'])->where('rule_id', $v['rule_id'])->delete();
                    $appeal1 = Appeal::where('ZYH', $v['JZHM'])->where('error_id', $v['rule_id'])->get()->toArray();
                    //Log::info('appeal1--ruleids-->', $appeal1);
                    if (!empty($appeal1)) {
                        $v['appeal_id'] = $appeal1[0]['id'];
                        $v['is_appeal'] = 1;
                        $appealids[] = $appeal1[0]['id'];
                    } else {
                        $v['appeal_id'] = 0;
                        $v['is_appeal'] = 0;
                    }
                    CaseQuality::addData($v);
                }
            }
        }

        $methodList = array_keys($methodList);
        if (empty($appealids)) {
            Appeal::where('ZYH', $ZYH)->whereIn('error_id', $methodList)->where('status', '!=', '1')->update(['status' => 3]);
        } else {
            Appeal::whereNotIn('id', is_array($appealids) ? $appealids : [$appealids])->where('ZYH', $ZYH)->whereIn('error_id', $methodList)->where('status', '!=', '1')->update(['status' => 3]);
        }
        return true;
    }

    /**
     * 出院记录无入院诊断
     */
    public function rule10359($caseRule = [], $ruleId, $ZYH = "")
    {

        $newData = Bllb1::query()->where("ZYH", $ZYH)->first();
        if (empty($newData)) {
            return [];
        }
        if (empty($newData->RYZD)) {
            $this->insertData[] = [
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => '',
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }
    }
    /**
     * 日常病程记录--出院当天或出院前一天无病程记录
     */
    public function rule462($caseRule = [], $ruleId, $ZYH = "")
    {
        $brry = ZY_BRRY::query()->where('ZYH', $ZYH)->get()->toArray();
        if (empty($brry)) {
            return false;
        }

        $AAB01 = $brry[0]['AAB01'];
        $AAC01 = $brry[0]['AAC01'];
        if (!$AAC01) {
            return [];
        }
        if (strtotime($AAC01) - strtotime($AAB01) < 86400) {
            return [];
        }

        $preTime = date('Y-m-d 00:00:00', strtotime($AAC01 . '-1 day'));
        $CYSJ = date('Y-m-d 23:59:59', strtotime($AAC01));

        // 检查出院当天或前一天是否有病程记录
        $bl01 = EMR_BL_BL01::query()->where('JZHM', $ZYH)
            ->where('BLLB', 294)
            ->whereBetween('ZXSJ', [$preTime, $CYSJ])
            ->get()->toArray();
        if (empty($bl01)) {
            $this->insertData[] = [
                'basis' => json_encode([['出院时间【' . $AAC01 . '】', '出院当天或出院前一天无病程记录']], 256),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => '',
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }
        return [];
    }

    /**
     * 死亡记录无主管医师签名
     */
    public function rule651($caseRule = [], $ruleId, $ZYH = "")
    {

        $bllb288 = Bllb288::query()->where('ZYH', $ZYH)->get()->toArray();
        if (empty($bllb288)) {
            return false;
        }

        $basisList = [];
        foreach ($bllb288 as $v) {
            $blsy = EMR_BL_BLSY::query()->where('BLBH', $v['BLBH'])->get()->toArray();
            if (!$blsy) {
                $basisList[] = ["病历名称：{$v['BLMC']}", "死亡记录无主管医师签名"];
                continue;
            }
        }

        if (!empty($basisList)) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, 256),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => '',
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }
        return true;
    }

    /**
     * 死亡记录无死亡时间
     */
    public function rule649($caseRule = [], $ruleId, $ZYH = "")
    {
        $bllb288 = Bllb288::query()->where('ZYH', $ZYH)->get()->toArray();
        if (empty($bllb288)) {
            return false;
        }

        $basisList = [];
        foreach ($bllb288 as $v) {
            if (!$v['SWSJ']) {
                $basisList[] = ["病历名称：{$v['BLMC']}", "死亡记录无死亡时间"];
                continue;
            }
        }

        if (!empty($basisList)) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, 256),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => '',
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }
        return true;
    }

    /**
     * 死亡记录无死亡诊断
     */
    public function rule648($caseRule = [], $ruleId, $ZYH = "")
    {
        $bllb288 = Bllb288::query()->where('ZYH', $ZYH)->get()->toArray();
        if (empty($bllb288)) {
            return false;
        }

        $basisList = [];
        foreach ($bllb288 as $v) {
            if (!$v['SWZD']) {
                $basisList[] = ["病历名称：{$v['BLMC']}", "死亡记录无死亡诊断"];
                continue;
            }
        }

        if (!empty($basisList)) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, 256),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => '',
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }
        return true;
    }

    /**
     * 死亡记录无入院诊断
     */
    public function rule642($caseRule = [], $ruleId, $ZYH = "")
    {
        $bllb288 = Bllb288::query()->where('ZYH', $ZYH)->get()->toArray();
        if (empty($bllb288)) {
            return false;
        }

        $basisList = [];
        foreach ($bllb288 as $v) {
            if (!$v['RYZD']) {
                $basisList[] = ["病历名称：{$v['BLMC']}", "死亡记录无入院诊断"];
                continue;
            }
        }

        if (!empty($basisList)) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, 256),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => '',
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }
        return true;
    }

    /**
     * 死亡记录无入院情况
     */
    public function rule641($caseRule = [], $ruleId, $ZYH = "")
    {
        $bllb288 = Bllb288::query()->where('ZYH', $ZYH)->get()->toArray();
        if (empty($bllb288)) {
            return false;
        }

        $basisList = [];
        foreach ($bllb288 as $v) {
            if (!$v['RYQK']) {
                $basisList[] = ["病历名称：{$v['BLMC']}", "死亡记录无入院情况"];
                continue;
            }
        }

        if (!empty($basisList)) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, 256),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => '',
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }
        return true;
    }


    /**
     * 手术记录内容无手术一般情况
     */
    public function rule594($caseRule = [], $ruleId, $ZYH = "")
    {
        $bllb303 = Bllb303::query()->where('ZYH', $ZYH)->get()->toArray();
        if (empty($bllb303)) {
            return false;
        }

        $basisList = [];
        foreach ($bllb303 as $v) {
            if (!$v['SSJG']) {
                $basisList[] = ["病历名称：{$v['BLMC']}", "手术记录内容【无手术一般情况】"];
                continue;
            }
        }

        if (!empty($basisList)) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, 256),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => '',
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }
        return true;
    }

    /**
     * 手术记录内容无手术经过
     */
    public function rule593($caseRule = [], $ruleId, $ZYH = "")
    {
        $bllb303 = Bllb303::query()->where('ZYH', $ZYH)->get()->toArray();
        if (empty($bllb303)) {
            return false;
        }

        $basisList = [];
        foreach ($bllb303 as $v) {
            if (!$v['SSJG']) {
                $basisList[] = ["病历名称：{$v['BLMC']}", "手术记录内容【无手术经过】"];
                continue;
            }
        }

        if (!empty($basisList)) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, 256),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => '',
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }
        return true;
    }

    /**
     * 手术记录内容无术中发现
     */
    public function rule592($caseRule = [], $ruleId, $ZYH = "")
    {
        $bllb303 = Bllb303::query()->where('ZYH', $ZYH)->get()->toArray();
        if (empty($bllb303)) {
            return false;
        }

        $basisList = [];
        foreach ($bllb303 as $v) {
            if (!$v['SSJG']) {
                $basisList[] = ["病历名称：{$v['BLMC']}", "手术记录内容【无术中发现】"];
                continue;
            }
        }

        if (!empty($basisList)) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, 256),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => '',
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }
        return true;
    }

    /**
     * 手术记录内容无麻醉方法
     */
    public function rule591($caseRule = [], $ruleId, $ZYH = "")
    {
        $bllb303 = Bllb303::query()->where('ZYH', $ZYH)->get()->toArray();
        if (empty($bllb303)) {
            return false;
        }

        $basisList = [];
        foreach ($bllb303 as $v) {
            if (!$v['MZFS']) {
                $basisList[] = ["病历名称：{$v['BLMC']}", "手术记录内容【无麻醉方法】"];
                continue;
            }
        }

        if (!empty($basisList)) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, 256),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => '',
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }
        return true;
    }

    /**
     * 手术记录内容无麻醉师姓名
     */
    public function rule590($caseRule = [], $ruleId, $ZYH = "")
    {
        $bllb303 = Bllb303::query()->where('ZYH', $ZYH)->get()->toArray();
        if (empty($bllb303)) {
            return false;
        }

        $basisList = [];
        foreach ($bllb303 as $v) {
            if (!$v['MZZ']) {
                $basisList[] = ["病历名称：{$v['BLMC']}", "手术记录内容【无麻醉师姓名】"];
                continue;
            }
        }

        if (!empty($basisList)) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, 256),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => '',
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }
        return true;
    }

    /**
     * 手术记录内容无助手姓名
     */
    public function rule589($caseRule = [], $ruleId, $ZYH = "")
    {
        $bllb303 = Bllb303::query()->where('ZYH', $ZYH)->get()->toArray();
        if (empty($bllb303)) {
            return false;
        }

        $basisList = [];
        foreach ($bllb303 as $v) {
            if (!$v['ZS']) {
                $basisList[] = ["病历名称：{$v['BLMC']}", "手术记录内容【无助手姓名】"];
                continue;
            }
        }

        if (!empty($basisList)) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, 256),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => '',
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }
        return true;
    }

    /**
     * 手术记录内容无术者姓名
     */
    public function rule588($caseRule = [], $ruleId, $ZYH = "")
    {
        $bllb303 = Bllb303::query()->where('ZYH', $ZYH)->get()->toArray();
        if (empty($bllb303)) {
            return false;
        }

        $basisList = [];
        foreach ($bllb303 as $v) {
            if (!$v['SSZ']) {
                $basisList[] = ["病历名称：{$v['BLMC']}", "手术记录内容【无术者姓名】"];
                continue;
            }
        }

        if (!empty($basisList)) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, 256),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => '',
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }
        return true;
    }


    /**
     * 手术记录内容无手术名称
     */
    public function rule587($caseRule = [], $ruleId, $ZYH = "")
    {
        $bllb303 = Bllb303::query()->where('ZYH', $ZYH)->get()->toArray();
        if (empty($bllb303)) {
            return false;
        }

        $basisList = [];
        foreach ($bllb303 as $v) {
            if (!$v['SSMC']) {
                $basisList[] = ["病历名称：{$v['BLMC']}", "手术记录内容【无手术名称】"];
                continue;
            }
        }

        if (!empty($basisList)) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, 256),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => '',
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }
        return true;
    }


    /**
     * 手术记录内容无术后诊断
     */
    public function rule586($caseRule = [], $ruleId, $ZYH = "")
    {
        $bllb303 = Bllb303::query()->where('ZYH', $ZYH)->get()->toArray();
        if (empty($bllb303)) {
            return false;
        }

        $basisList = [];
        foreach ($bllb303 as $v) {
            if (!$v['SHZD']) {
                $basisList[] = ["病历名称：{$v['BLMC']}", "手术记录内容【无术后诊断】"];
                continue;
            }
        }

        if (!empty($basisList)) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, 256),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => '',
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }
        return true;
    }


    /**
     * 手术记录内容无术前诊断
     */
    public function rule585($caseRule = [], $ruleId, $ZYH = "")
    {
        $bllb303 = Bllb303::query()->where('ZYH', $ZYH)->get()->toArray();
        if (empty($bllb303)) {
            return false;
        }

        $basisList = [];
        foreach ($bllb303 as $v) {
            if (!$v['SQZD']) {
                $basisList[] = ["病历名称：{$v['BLMC']}", "手术记录内容【无术前诊断】"];
                continue;
            }
        }

        if (!empty($basisList)) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, 256),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => '',
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }
        return true;
    }

    /**
     * 手术记录内容无手术日期
     */
    public function rule584($caseRule = [], $ruleId, $ZYH = "")
    {
        $bllb303 = Bllb303::query()->where('ZYH', $ZYH)->get()->toArray();
        if (empty($bllb303)) {
            return false;
        }

        $basisList = [];
        foreach ($bllb303 as $v) {
            if (!$v['SSRQ']) {
                $basisList[] = ["病历名称：{$v['BLMC']}", "手术记录内容【无手术日期】"];
                continue;
            }
        }

        if (!empty($basisList)) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, 256),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => '',
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }
        return true;
    }

    /**
     * 术前讨论无记录者签名
     */
    public function rule577($caseRule = [], $ruleId, $ZYH = "")
    {
        $keyword20011 = RuleWordMap::getArrayById(20011);
        $sqtlRecords = EMR_BL_BL01::query()
            ->where('JZHM', $ZYH)
            ->where('MBLB', 304)
            ->where(function ($query) use ($keyword20011) {
                foreach ($keyword20011 as $keyword) {
                    $query->orWhere('BLMC', 'like', '%' . $keyword . '%');
                }
            })
            ->get()->toArray();
        if (empty($sqtlRecords)) {
            return false;
        }

        $basisList = [];
        foreach ($sqtlRecords as $v) {
            $blsy = EMR_BL_BLSY::query()->where('BLBH', $v['BLBH'])->first();
            if (!$blsy) {
                $basisList[] = ["病历名称：{$v['BLMC']}", "术前讨论【无记录者签名】"];
                continue;
            }
        }

        if (!empty($basisList)) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, 256),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => '',
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }
        return true;
    }

    /**
     * 术前讨论记录无术前准备情况
     */
    public function rule571($caseRule = [], $ruleId, $ZYH = "")
    {

        $sqtlRecords = Mblb304::query()
            ->where('ZYH', $ZYH)
            ->get()->toArray();
        if (empty($sqtlRecords)) {
            return false;
        }

        $basisList = [];
        foreach ($sqtlRecords as $v) {
            $bl01 = EMR_BL_BL01::query()->where('BLBH', $v['BLBH'])->get()->toArray();
            if (empty($bl01)) {
                continue;
            }
            $bl01 = $bl01[0];
            if (empty($v['SQZBQK'])) {
                $basisList[] = ["病历名称：{$bl01['BLMC']}", "术前讨论【无术前准备情况】"];
                continue;
            }
        }

        if (!empty($basisList)) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, 256),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => '',
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }
        return true;
    }

    /**
     * 术前讨论记录无讨论日期
     */
    public function rule570($caseRule = [], $ruleId, $ZYH = "")
    {
        $sqtlRecords = Mblb304::query()
            ->where('ZYH', $ZYH)
            ->get()->toArray();
        if (empty($sqtlRecords)) {
            return false;
        }

        $basisList = [];
        foreach ($sqtlRecords as $v) {
            if (empty($v['TLSJ'])) {
                $bl01 = EMR_BL_BL01::query()->where('BLBH', $v['BLBH'])->get()->toArray();
                if (empty($bl01)) {
                    continue;
                }
                $basisList[] = ["病历名称：{$bl01[0]['BLMC']}【无讨论日期】"];
                continue;
            }
        }

        if (!empty($basisList)) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, 256),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => '',
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }
        return true;
    }

    /**
     * 三级以上手术无术前讨论记录，术前1周内进行讨论（急诊例外）
     */
    public function rule566($caseRule = [], $ruleId, $ZYH = "")
    {

        $patientInfo = PatientInfo::query()->where('MED_REC_ID', $ZYH)->get()->toArray();
        if (empty($patientInfo) || $patientInfo[0]['AAB06C'] == 1) {
            return false;
        }
        $patientInfo = $patientInfo[0];

        $level4Oparation = PatientInfoOperationV2::query()->where('ZYH', $ZYH)->whereIn('OPE_LEVEL', [3, 4])->get()->toArray();
        if (empty($level4Oparation)) {
            return false;
        }


        //获取8047,8048
        $excludeKeywords8047 = RuleWordMap::getArrayById(8047);
        $keyword20011 = RuleWordMap::getArrayById(20011);
        foreach ($level4Oparation as $l4) {
            //根据手术名称查询SSCZ的SSLB
            $ssCZ = SSCZ::query()->where('SSMC', $l4['ICD9_NAME'])->value('SSLB');
            if (empty($ssCZ)) {
                continue;
            }
            //检查是否是手术+介入
            if (!in_array($ssCZ, $excludeKeywords8047)) {
                continue;
            }
            $endDatetime = date('Y-m-d 00:00:00', strtotime($l4['OPE_DATE']));

            $startDatetime = date('Y-m-d H:i:s', strtotime($endDatetime) - 7 * 24 * 3600);
            $query = EMR_BL_BL01::query()
                ->where('JZHM', $ZYH)
                ->where(function ($query) use ($keyword20011) {
                    $query->orWhere('MBLB', '=', 304);
                    foreach ($keyword20011 as $keyword) {
                        $query->orWhere('BLMC', 'like', '%' . $keyword . '%');
                    }
                })
                ->where('ZXSJ', '>', $startDatetime)
                ->where('ZXSJ', '<', $endDatetime);
            $sqtlRecords = $query->get()->toArray();

            if (empty($sqtlRecords)) {
                $basisList[] = ["手术名称：{$l4['ICD9_NAME']}", "手术时间：{$l4['OPE_DATE']}", "术前讨论【无】"];
                continue;
            }
        }
        if (!empty($basisList)) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, 256),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => '',
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }

        return true;
    }


    /**
     * 术后首程复制手术记录
     */
    public function rule554($caseRule = [], $ruleId, $ZYH = "") {}

    /**
     * 术后首程无医师签名
     */
    public function rule565($caseRule = [], $ruleId, $ZYH = "")
    {
        $sc = Mblb42::query()->where("ZYH", $ZYH)->get()->toArray();
        if (empty($sc)) {
            return [];
        }
        $basisList = [];
        foreach ($sc as $item) {
            $bl01 = EMR_BL_BL01::query()->where("BLBH", $item['BLBH'])->get()->toArray();
            if (empty($bl01)) {
                continue;
            }
            $blsy = EMR_BL_BLSY::query()->where("BLBH", $item['BLBH'])->get()->toArray();
            if (empty($blsy)) {
                $basisList[] = ['病历名称【' . $bl01[0]['BLMC'] . '】'];
            }
        }
        if (!empty($basisList)) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, 256),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => '',
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }
    }

    /**
     * 术后首程无术后病情告知
     */
    public function rule563($caseRule = [], $ruleId, $ZYH = "")
    {
        $rule20055 = RuleWordMap::getArrayById(20055);
        $sc = Mblb42::query()->where("ZYH", $ZYH)->get()->toArray();
        if (empty($sc)) {
            return [];
        }
        $basisList = [];
        foreach ($sc as $item) {
            $bl01 = EMR_BL_BL01::query()->where("BLBH", $item['BLBH'])->get()->toArray();
            if (empty($bl01)) {
                continue;
            }
            $blxg = EMR_BL_BLXG::query()->where("BLBH", $item['BLBH'])->get()->toArray();
            if (empty($blxg)) {
                continue;
            }
            $hjnr = $blxg[0]['HJNR'];
            $flag = false;
            foreach ($rule20055 as $key => $value) {
                if (strpos($hjnr, $value) !== false) {
                    $flag = true;
                    break;
                }
            }
            if (!$flag) {
                $basisList[] = ['病历名称【' . $bl01[0]['BLMC'] . '】'];
            }
        }
        if (!empty($basisList)) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, 256),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => '',
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }
    }

    /**
     * 术后首程无手术起止时间
     */
    public function rule551($caseRule = [], $ruleId, $ZYH = "")
    {
        $bl01 = EMR_BL_BL01::query()
            ->leftJoin('EMR_BL_BLXG', 'EMR_BL_BL01.BLBH', '=', 'EMR_BL_BLXG.BLBH')
            ->where("EMR_BL_BL01.JZHM", $ZYH)
            ->where('EMR_BL_BL01.MBLB', 42)
            ->get(['EMR_BL_BL01.BLMC', 'EMR_BL_BLXG.HJNR'])->toArray();
        if (empty($bl01)) {
            return [];
        }
        $basisList = [];


        foreach ($bl01 as $item) {
            if (empty($bl01)) {
                continue;
            }
            $hjnr = $item['HJNR'];
            if (empty($hjnr)) {
                continue;
            }
            $hjnr = strstr($hjnr, '首次病程记录');

            if (!preg_match("/(\d{2}时\d{2}|\d{2}:\d{2})/", $hjnr, $matches1)) {
                $basisList[] = ['病历名称【' . $item['BLMC'] . '】中存在没有时分的时间'];
            }
        }
        if (!empty($basisList)) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, 256),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => '',
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }

        return [];
    }

    /**
     * 术后首程无麻醉方式
     */
    public function rule552($caseRule = [], $ruleId, $ZYH = "")
    {
        $rule20054 = RuleWordMap::getArrayById(20054);
        $sc = Mblb42::query()->where("ZYH", $ZYH)->get()->toArray();
        if (empty($sc)) {
            return [];
        }
        $basisList = [];
        foreach ($sc as $item) {
            $bl01 = EMR_BL_BL01::query()->where("BLBH", $item['BLBH'])->get()->toArray();
            if (empty($bl01)) {
                continue;
            }
            $blxg = EMR_BL_BLXG::query()->where("BLBH", $item['BLBH'])->get()->toArray();
            if (empty($blxg)) {
                continue;
            }
            $hjnr = $blxg[0]['HJNR'];
            $flag = false;
            foreach ($rule20054 as $key => $value) {
                if (strpos($hjnr, $value) !== false) {
                    $flag = true;
                    break;
                }
            }
            if (!$flag) {
                $basisList[] = ['病历名称【' . $bl01[0]['BLMC'] . '】'];
            }
        }
        if (!empty($basisList)) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, 256),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => '',
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }
    }


    /**
     * 术后首程无术后诊断
     */
    public function rule557($caseRule = [], $ruleId, $ZYH = "")
    {
        $sc = Mblb42::query()->where("ZYH", $ZYH)->get()->toArray();
        if (empty($sc)) {
            return [];
        }
        $basisList = [];
        foreach ($sc as $item) {
            $bl01 = EMR_BL_BL01::query()->where("BLBH", $item['BLBH'])->get()->toArray();
            if (empty($bl01)) {
                continue;
            }
            if (empty($item['SHZD'])) {
                $basisList[] = ['病历名称【' . $bl01[0]['BLMC'] . '】'];
            }
        }
        if (!empty($basisList)) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, 256),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => '',
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }
    }


    /**
     * 术后首程无手术方式
     */
    public function rule553($caseRule = [], $ruleId, $ZYH = "")
    {
        $sc = Mblb42::query()->where("ZYH", $ZYH)->get()->toArray();
        if (empty($sc)) {
            return [];
        }
        $basisList = [];
        foreach ($sc as $item) {
            $bl01 = EMR_BL_BL01::query()->where("BLBH", $item['BLBH'])->get()->toArray();
            if (empty($bl01)) {
                continue;
            }
            $blxg = EMR_BL_BLXG::query()->where("BLBH", $item['BLBH'])->get()->toArray();
            if (empty($blxg)) {
                continue;
            }
            $hjnr = $blxg[0]['HJNR'];
            preg_match('/行.*术/u', $hjnr, $matches);
            if (empty($item['SSFS']) && empty($matches[0]) && strpos($hjnr, '穿刺') === false) {
                $basisList[] = ['病历名称【' . $bl01[0]['BLMC'] . '】'];
            }
        }
        if (!empty($basisList)) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, 256),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => '',
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }
    }

    /**
     * 术前小结无手术者术前查看患者相关情况等
     */
    public function rule548($caseRule = [], $ruleId, $ZYH = "")
    {
        $rulewords20053 = RuleWordMap::getArrayById(20053);
        $bl01 = Mblb82::query()->where("ZYH", $ZYH)->get()->toArray();
        if (empty($bl01)) {
            return [];
        }

        foreach ($bl01 as $item) {
            $bl01 = EMR_BL_BL01::query()->where("BLBH", $item['BLBH'])->get()->toArray();
            if (empty($bl01)) {
                continue;
            }
            $flag = false;
            foreach ($rulewords20053 as $keyword) {
                if (strpos($item['SQZB'], $keyword) !== false) {
                    $flag = true;
                    break;
                }
            }

            // 检查是否有相关情况
            if (!$flag) {
                $this->insertData[] = [
                    'basis' => json_encode([['病历名称【' . $bl01[0]['BLMC'] . '】', '术前小结无手术者术前查看患者相关情况等']], 256),
                    'JZHM' => $ZYH,
                    'rule_id' => $ruleId,
                    'code' => '',
                    'error_field' => $caseRule[$ruleId]['title']
                ];
            }
        }
    }


    /**
     * 术前小结无拟施麻醉方式
     */
    public function rule542($caseRule = [], $ruleId, $ZYH = "")
    {
        $mblb82 = Mblb82::query()->where("ZYH", $ZYH)->get()->toArray();
        if (empty($mblb82)) {
            return [];
        }
        $basisList = [];
        foreach ($mblb82 as $item) {
            $bl01 = EMR_BL_BL01::query()->where("BLBH", $item['BLBH'])->get()->toArray();
            if (empty($bl01)) {
                continue;
            }
            if (empty($item['MZFS'])) {
                $basisList[] = ['病历名称【' . $bl01[0]['BLMC'] . '】'];
            }
        }
        if (!empty($basisList)) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, 256),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => '',
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }
        return [];
    }

    /**
     * 手术前无手术医嘱
     */
    public function rule544($caseRule = [], $ruleId, $ZYH = "")
    {
        // 1. 查询手术信息，获取手术开始时间
        $ssapData = SM_SSAP::query()->where('ZYH', '=', $ZYH)->orderBy('SSRQ', 'asc')->groupBy('SSRQ')->get()->toArray();
        if (empty($ssapData)) {
            return true; // 没有手术记录，不进行质控
        }
        //获取8047,8048
        $excludeKeywords8047 = RuleWordMap::getArrayById(8047);

        // 收集所有质控结果
        $basisList = [];

        // 找第一个手术医嘱
        $firstYzb = [];
        $yzb = Yzb::query()->where('ZYH', $ZYH)->get(['YZMC', 'KZSJ'])->toArray();
        foreach ($yzb as $v) {
            if (empty($v['KZSJ'])) {
                continue;
            }
            $yzb_name = $v['YZMC'];
            if (preg_match('/.*拟.*行.*术.*/u', $yzb_name, $matches)) {
                $firstYzb = $yzb;
                break;
            }
        }

        // 4. 处理每个手术记录
        foreach ($ssapData as $surgery) {
            // 获取手术开始时间
            $surgeryStartTime = $surgery['SSRQ'];
            //手术名称
            $surgeryName = $surgery['ICD9_SSCZMC'];
            if (empty($surgery['SSRQ']) || empty($surgery['ICD9_SSCZMC']) || empty($surgery['JSRQ']) || strpos($surgery['JSRQ'], '1970-01-01') !== false || $surgery['ICD9_SSCZMC'] == 'NULL') {
                continue;
            }
            //根据手术名称查询SSCZ的SSLB
            $ssCZ = SSCZ::query()->where('SSMC', $surgeryName)->value('SSLB');
            if (empty($ssCZ)) {
                continue;
            }
            //检查是否是手术+介入
            if (!in_array($ssCZ, $excludeKeywords8047)) {
                continue;
            }
            // 检查是否有手术医嘱
            $hasName = false;
            foreach ($yzb as $v) {
                $yzb_name = $v['YZMC'];
                if (strpos($yzb_name, $surgeryName) !== false) {
                    $hasName = true;
                    break;
                }
            }
            if ($hasName) {
                continue;
            }

            if (empty($firstYzb) || strtotime($firstYzb['KZSJ']) > strtotime($surgeryStartTime)) {
                $basisList[] = ['手术名称【' . $surgeryName . '】', '手术开始时间【' . $surgeryStartTime . '】前，无手术医嘱'];
            }
        }

        // 7. 将所有质控结果一次性插入到insertData
        if (!empty($basisList)) {
            $this->insertData[] = [
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title'],
                'basis' => json_encode($basisList, JSON_UNESCAPED_UNICODE)
            ];
        }

        return true;
    }

    /**
     * 死亡记录死亡时间和死亡讨论记录死亡时间不一致
     * 死亡记录 EMR_BL_BL01的MBLB=288
     * 死亡讨论记录 EMR_BL_BL01的MBLB=4302
     * 
     */
    public function rule10349($caseRule = [], $ruleId, $ZYH = "")
    {
        $blData = new BlDataFormatService();
        $bl01 = EMR_BL_BL01::query()->where("JZHM", $ZYH)->where('MBLB', 288)->get()->toArray();
        if (empty($bl01)) {
            return [];
        }
        $swtlBl01 = EMR_BL_BL01::query()->where("JZHM", $ZYH)->where('MBLB', 4302)->get()->toArray();
        if (empty($swtlBl01)) {
            return [];
        }

        $swHJNR = EMR_BL_BLXG::query()->where("BLBH", $bl01[0]['BLBH'])->get()->toArray();
        if (empty($swHJNR)) {
            return [];
        }

        $swtlHJNR = EMR_BL_BLXG::query()->where("BLBH", $swtlBl01[0]['BLBH'])->get()->toArray();
        if (empty($swtlHJNR)) {
            return [];
        }

        // 获取$swHJNR中的死亡时间
        $swDeathTime = '';
        if (preg_match('/死亡时间：\s*(\d{4}年\d{1,2}月\d{1,2}日 \d{1,2}:\d{2}|\d{4}-\d{1,2}-\d{1,2} \d{1,2}:\d{2})/i', $swHJNR[0]['HJNR'], $matches)) {
            $swDeathTime = $matches[1];
            $swDeathTime = $blData->formatTime($swDeathTime);
        }

        // 获取$swtlHJNR中的死亡时间
        $swtlDeathTime = '';
        if (preg_match('/死亡时间：\s*(\d{4}年\d{1,2}月\d{1,2}日 \d{1,2}:\d{2}|\d{4}-\d{1,2}-\d{1,2} \d{1,2}:\d{2})/i', $swtlHJNR[0]['HJNR'], $matches)) {
            $swtlDeathTime = $matches[1];
            $swtlDeathTime = $blData->formatTime($swtlDeathTime);
        }

        // 比较死亡时间是否一致
        if (strtotime($swDeathTime) != strtotime($swtlDeathTime)) {
            $this->insertData[] = [
                'basis' => json_encode([['死亡记录死亡时间【' . $swDeathTime . '】', '死亡讨论记录死亡时间【' . $swtlDeathTime . '】']], JSON_UNESCAPED_UNICODE),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => '',
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }
        return [];
    }

    /**
     * 疑难病例讨论无讨论日期
     */
    public function rule10355($caseRule = [], $ruleId, $ZYH = "")
    {
        $mblb44 = Mblb44::query()->where('ZYH', $ZYH)->get()->toArray();
        if (empty($mblb44)) {
            return [];
        }

        $basisList = [];
        foreach ($mblb44 as $item) {
            if (empty($item['TLSJ'])) {
                $bl01 = EMR_BL_BL01::query()->where('BLBH', $item['BLBH'])->get(['BLMC'])->toArray();
                $blxg = EMR_BL_BLXG::query()->where('BLBH', $item['BLBH'])->get(['HJNR'])->toArray();
                $blxgHJNR = $blxg[0]['HJNR'];

                $blxgHJNR = strstr($blxgHJNR, '疑难病例讨论结论记录');
                $timePattern = '/(\d{4}\/\d{2}\/\d{2}\s+\d{2}:\d{2}(?::\d{2})?|\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}(?::\d{2})?|\d{4}-\d{2}-\d{2}\s+\d{2}时\d{2}分(?:\d{2}秒)?|\d{4}年\d{2}月\d{2}日\s+\d{2}时\d{2}分(?:\d{2}秒)?)/';
                if (!preg_match($timePattern, $blxgHJNR)) {
                    $basisList[] = ['病历名称【' . $bl01[0]['BLMC'] . '】讨论时间【空】'];
                }
            }
        }

        if (!empty($basisList)) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, 256),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => '',
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }
        return [];
    }

    /**
     * 疑难病例讨论无主持人姓名
     */
    public function rule10356($caseRule = [], $ruleId, $ZYH = "")
    {
        $rule20057 = RuleWordMap::getArrayById(20057);
        $mblb44 = Mblb44::query()->where('ZYH', $ZYH)->get(['BLBH', 'ZCR'])->toArray();
        if (empty($mblb44)) {
            return [];
        }

        $basisList = [];
        foreach ($mblb44 as $item) {
            if (empty($item['ZCR'])) {
                $bl01 = EMR_BL_BL01::query()->where('BLBH', $item['BLBH'])->get(['BLMC'])->toArray();
                $blxg = EMR_BL_BLXG::query()->where('BLBH', $item['BLBH'])->get(['HJNR'])->toArray();
                $blxgHJNR = $blxg[0]['HJNR'];
                $flag = false;
                foreach ($rule20057 as $key => $value) {
                    if (strpos($blxgHJNR, $value) !== false) {
                        $flag = true;
                        break;
                    }
                }

                if (!$flag) {
                    $basisList[] = ['病历名称【' . $bl01[0]['BLMC'] . '】主持人【空】'];
                }
            }
        }

        if (!empty($basisList)) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, 256),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => '',
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }
        return [];
    }

    /**
     * 疑难病例讨论无参加者姓名
     */
    public function rule10357($caseRule = [], $ruleId, $ZYH = "")
    {
        $rule20058 = RuleWordMap::getArrayById(20058);
        $mblb44 = Mblb44::query()->where('ZYH', $ZYH)->get(['BLBH', 'CJTLRY'])->toArray();
        if (empty($mblb44)) {
            return [];
        }

        $basisList = [];
        foreach ($mblb44 as $item) {
            if (empty($item['CJTLRY'])) {
                $bl01 = EMR_BL_BL01::query()->where('BLBH', $item['BLBH'])->get(['BLMC'])->toArray();
                $blxg = EMR_BL_BLXG::query()->where('BLBH', $item['BLBH'])->get(['HJNR'])->toArray();
                $blxgHJNR = $blxg[0]['HJNR'];
                $flag = false;
                foreach ($rule20058 as $key => $value) {
                    if (strpos($blxgHJNR, $value) !== false) {
                        $flag = true;
                        break;
                    }
                }

                if (!$flag) {
                    $basisList[] = ['病历名称【' . $bl01[0]['BLMC'] . '】参加讨论人员【空】'];
                }
            }
        }

        if (!empty($basisList)) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, 256),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => '',
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }
        return [];
    }

    /**
     * 疑难病例讨论无记录者签名
     */
    public function rule10358($caseRule = [], $ruleId, $ZYH = "")
    {
        $bl01 = EMR_BL_BL01::query()
            ->leftJoin('EMR_BL_BLXG', 'EMR_BL_BL01.BLBH', '=', 'EMR_BL_BLXG.BLBH')
            ->where('EMR_BL_BL01.JZHM', $ZYH)
            ->where('EMR_BL_BL01.MBLB', 44)
            ->get(['EMR_BL_BL01.BLBH', 'EMR_BL_BL01.BLMC', 'EMR_BL_BLXG.HJNR'])->toArray();
        if (empty($bl01)) {
            return [];
        }

        $staff = Staff::query()->get(['code', 'name'])->toArray();
        $staffMap = array_column($staff, 'name', 'code');

        $basisList = [];
        foreach ($bl01 as $item) {
            $blxgHJNR = $item['HJNR'];
            $flag = false;
            $blsy = EMR_BL_BLSY::query()->where('BLBH', $item['BLBH'])->get(['SYYS'])->toArray();
            foreach ($blsy as $key => $value) {
                if (!empty($staffMap[$value['SYYS']]) && strpos($blxgHJNR, $staffMap[$value['SYYS']]) !== false) {
                    $flag = true;
                    break;
                }
            }

            if (!$flag) {
                $basisList[] = ['【' . $item['BLMC'] . '】无参与讨论人员签名'];
            }
        }

        if (!empty($basisList)) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, 256),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => '',
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }
        return [];
    }

    /**
     * 会诊记录--会诊申请单无会诊医师签名
     */
    public function rule522($caseRule = [], $ruleId, $ZYH = "")
    {
        $hzsq = YS_ZY_HZSQ::query()->where("JZHM", $ZYH)->get(['SQXH', 'SQSJ'])->toArray();
        if (empty($hzsq)) {
            return [];
        }
        $basisList = [];
        foreach ($hzsq as $item) {
            $hzyj = YS_ZY_HZYJ::query()->where("SQXH", $item['SQXH'])->get(['HZYJ', 'SQXH', 'QMYS'])->toArray();

            foreach ($hzyj as $item1) {
                if (empty($item1['QMYS'])) {
                    $basisList[] = [$item['SQSJ'] . ' 会诊记录单'];
                }
            }
        }
        if ($basisList) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, JSON_UNESCAPED_UNICODE),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => '',
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }
        return [];
    }

    /**
     * 会诊记录--普通会诊记录未在48小时内完成
     */
    public function rule513($caseRule = [], $ruleId, $ZYH = "")
    {

        // 紧急会诊标志值配置
        $ruleMap8040 = RuleWordMap::query()->where('id', '=', 8040)->value('keyword');
        $ruleMap8040 = !empty($ruleMap8040) ? $ruleMap8040 : '2';

        // 1. 查询会诊申请表中的普通会诊申请（非紧急且未作废）
        $consultRequests = YS_ZY_HZSQ::query()
            ->where('JZHM', $ZYH)
            ->where('JJBZ', '!=', $ruleMap8040) // 非紧急会诊
            ->where('ZFBZ', 0)                  // 未作废
            ->get()->toArray();

        if (empty($consultRequests)) {
            return [];
        }

        $keyword8091 = RuleWordMap::getArrayById(8091);

        foreach ($consultRequests as $v) {
            if (empty($v['HZSJ'])) {
                continue;
            }
            $hzSJ = date('Y-m-d H:i:s', strtotime($v['HZSJ']));
            $bl01 = EMR_BL_BL01::query()->where('JZHM', $ZYH)
                ->whereBetween('ZXSJ', [$hzSJ, date('Y-m-d H:i:s', strtotime($hzSJ . ' + 2 days'))])
                ->where(function ($query) use ($keyword8091) {
                    foreach ($keyword8091 as $v) {
                        $query->where('BLMC', 'like', '%' . $v . '%');
                    }
                })->first();
            if (empty($bl01)) {
                $this->insertData[] = [
                    'basis' => json_encode([['会诊申请时间【' . $hzSJ . '】']], JSON_UNESCAPED_UNICODE),
                    'JZHM' => $ZYH,
                    'rule_id' => $ruleId,
                    'code' => '',
                    'error_field' => $caseRule[$ruleId]['title']
                ];
            }
        }
    }


    /**
     * 会诊记录--无申请会诊医师姓名
     */
    public function rule519($caseRule = [], $ruleId, $ZYH = "")
    {
        $consultRequests = YS_ZY_HZSQ::query()
            ->where('JZHM', $ZYH)
            ->where('ZFBZ', 0)                  // 未作废
            ->get()->toArray();

        if (empty($consultRequests)) {
            return [];
        }


        foreach ($consultRequests as $v) {
            if (empty($v['SQYS'])) {
                $this->insertData[] = [
                    'basis' => json_encode([['会诊申请时间【' . $v['HZSJ'] . '】']], JSON_UNESCAPED_UNICODE),
                    'JZHM' => $ZYH,
                    'rule_id' => $ruleId,
                    'code' => '',
                    'error_field' => $caseRule[$ruleId]['title']
                ];
            }
        }
    }

    /**
     * 有创操作记录--操作记录无操作时间
     */
    public function rule502($caseRule = [], $ruleId, $ZYH = "")
    {
        $mblb30304 =  Mblb30304::query()->where('ZYH', $ZYH)->get()->toArray();
        if (empty($mblb30304)) {
            return [];
        }

        foreach ($mblb30304 as $v) {
            if (empty($v['CZSJ'])) {
                $bl01 = EMR_BL_BL01::query()->where('BLBH', $v['BLBH'])->first();
                if (empty($bl01)) {
                    continue;
                }
                $this->insertData[] = [
                    'basis' => json_encode([['病历名称【' . $bl01->BLMC . '】']], JSON_UNESCAPED_UNICODE),
                    'JZHM' => $ZYH,
                    'rule_id' => $ruleId,
                    'code' => '',
                    'error_field' => $caseRule[$ruleId]['title']
                ];
            }
        }
    }

    /**
     * 择期手术后3天内无术者查房记录
     */
    public function rule434($caseRule = [], $ruleId, $ZYH = "")
    {
        // 查询四级手术（使用病案首页数据）
        $mainoperation = MainOperation::query()->where('AAA28', '=', $ZYH)->where('OPE_LEVEL', '=', '4')->get()->toArray();
        $secondaryoperation = SecondaryOperation::query()->where('AAA28', '=', $ZYH)->where('OPE_LEVEL', '=', '4')->get()->toArray();
        $results = array_merge($mainoperation, $secondaryoperation);
        if (empty($results)) {
            return [];
        }

        // 检查$results中是否存在SSLX为"择期"的手术
        $staff = Staff::query()->get()->toArray();
        $staff = array_column($staff, null, 'code');
        $basisList = [];
        foreach ($results as $item) {
            if ($item['SSLX_MC'] != '择期') {
                continue;
            }

            $SSKJSJ = $item['OPE_DATE'] ?? 0;
            $SZ = $item['OPE_MAN_NAME'] ?? 0;
            if (empty($SSKJSJ) || empty($SZ)) {
                continue;
            }

            $bl01 = EMR_BL_BL01::query()->where('JZHM', $ZYH)
                ->leftJoin('EMR_BL_BLXG', 'EMR_BL_BL01.BLBH', '=', 'EMR_BL_BLXG.BLBH')
                ->where('EMR_BL_BL01.BLLB', 294)
                ->whereBetween('EMR_BL_BL01.ZXSJ', [$SSKJSJ, date('Y-m-d H:i:s', strtotime($SSKJSJ . ' + 3 days'))])
                ->get(['EMR_BL_BL01.BLMC', 'EMR_BL_BL01.BLBH', 'EMR_BL_BLXG.HJNR'])->toArray();
            if (empty($bl01)) {
                $basisList[] = ['术者【' . $SZ . '】', '手术时间【' . $SSKJSJ . '】后3天内无术者查房记录'];
                continue;
            }

            $hasSz = false;
            foreach ($bl01 as $v) {
                if (strpos($v['BLMC'], $SZ) !== false || strpos($v['HJNR'], $SZ) !== false) {
                    $hasSz = true;
                }
                if (!$hasSz) {
                    $blsy = EMR_BL_BLSY::query()->where('BLBH', $v['BLBH'])->get()->toArray();
                    if (!empty($blsy)) {
                        foreach ($blsy as $bsy) {
                            if (!empty($staff[$bsy['SYYS']]) && strpos($staff[$bsy['SYYS']]['name'], $SZ) !== false) {
                                $hasSz = true;
                                break;
                            }
                        }
                    }
                }
            }
            if (!$hasSz) {
                $basisList[] = ['术者【' . $SZ . '】', '手术时间【' . $SSKJSJ . '】后3天内无术者查房记录'];
            }
        }
        if ($basisList) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, JSON_UNESCAPED_UNICODE),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => '',
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }
    }

    /**
     * 手术后3天未连续记录病程记录
     */
    public function rule435($caseRule = [], $ruleId, $ZYH = "")
    {
        $brry = ZY_BRRY::query()->where('ZYH', $ZYH)->first();
        if (empty($brry)) {
            return [];
        }
        $AAC01 = $brry->AAC01 ?? 0;
        $ssjl = Bllb303::query()->where('ZYH', $ZYH)->get()->toArray();
        if (empty($ssjl)) {
            return [];
        }

        $basisList = [];
        foreach ($ssjl as $s) {

            $ssrq = $s['SSKSSJ'] ?? 0; // 手术日期或创建时间
            if (empty($ssrq)) {
                continue;
            }
            for ($i = 0; $i < 3; $i++) {
                $zkEndTime = date('Y-m-d H:i:s', strtotime($ssrq . ' +1 days'));
                if (strtotime($zkEndTime) > strtotime($AAC01)) {
                    break;
                }
                $bingchengRecords = EMR_BL_BL01::query()
                    ->where('JZHM', $ZYH)
                    ->where('BLLB', 294)
                    ->whereBetween('ZXSJ', [$ssrq, $zkEndTime])
                    ->get()->toArray();
                $ssrq = $zkEndTime;
                if (empty($bingchengRecords)) {
                    $basisList[] = ['手术时间【' . $ssrq . '】后3天内连续记录病程记录'];
                    break;
                }
            }
        }

        if ($basisList) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, JSON_UNESCAPED_UNICODE),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => '',
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }
    }

    /**
     * 手术前一天无病程
     */
    public function rule433($caseRule = [], $ruleId, $ZYH = "")
    {
        $brry = ZY_BRRY::query()->where('ZYH', $ZYH)->first();
        if (empty($brry)) {
            return [];
        }
        $AAB01 = $brry->AAB01;
        $AAC01 = $brry->AAC01;
        if (date('d', strtotime($AAC01)) - date('d', strtotime($AAB01)) <= 1) {
            return [];
        }

        $ssjl = Bllb303::query()->where('ZYH', $ZYH)->get()->toArray();
        if (empty($ssjl)) {
            return [];
        }

        $basisList = [];
        foreach ($ssjl as $s) {

            $ssrq = $s['SSKSSJ'] ?? 0; // 手术日期或创建时间
            if (empty($ssrq)) {
                continue;
            }
            $previousDay = date('Y-m-d 00:00:00', strtotime($ssrq . ' -1 day'));
            $bcjlCount = DB::table('EMR_BL_BL01')
                ->where('JZHM', $ZYH)
                ->where('BLLB', 294)
                ->whereBetween('ZXSJ', [$previousDay, $ssrq])
                ->count();
            if ($bcjlCount == 0) {
                $basisList[] = ['手术时间【' . $ssrq . '】前一天无病程'];
            }
        }

        if ($basisList) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, JSON_UNESCAPED_UNICODE),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => '',
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }
    }


    /**
     * 转科没有连记3天
     */
    public function rule430($caseRule = [], $ruleId, $ZYH = "")
    {
        $brry = ZY_BRRY::query()->where('ZYH', $ZYH)->first();
        if (empty($brry)) {
            return [];
        }

        $AAC01 = $brry->AAC01;
        $ruyuanTime = $brry->AAB01;
        // 检查住院时间是否超过3天
        if ($AAC01 && strtotime($AAC01) - strtotime($ruyuanTime) < 72 * 3600) {
            return [];
        }

        $nowTime = time();
        // 没有出院时间，则校验当前时间 - 入院时间是否超过72小时
        if (!$AAC01 && $nowTime - strtotime($ruyuanTime) < 72 * 3600) {
            return [];
        }

        $keyword20041 = RuleWordMap::getArrayById(20041);
        $yzb = Yzb::query()->where('ZYH', $ZYH)->where(function ($query) use ($keyword20041) {
            foreach ($keyword20041 as $v) {
                $query->where('YZMC', 'like', '%' . $v . '%');
            }
        })->first();
        if (empty($yzb)) {
            return [];
        }
        $ZKTime = date('Y-m-d 00:00:00', strtotime($yzb->KZSJ));

        $basisList = [];
        $flag = false;
        for ($i = 0; $i < 3; $i++) {
            $zkEndTime = date('Y-m-d H:i:s', strtotime($ZKTime . ' +1 days'));
            $bingchengRecords = EMR_BL_BL01::query()
                ->where('JZHM', $ZYH)
                ->where('BLLB', 294)
                ->whereBetween('ZXSJ', [$ZKTime, $zkEndTime])
                ->get()->toArray();
            if (empty($bingchengRecords)) {
                $flag = true;
                $basisList[] = ['【' . substr($ZKTime, 0, 10) . '】少一天病程'];
            }
            if (strtotime($zkEndTime) > strtotime($AAC01)) {
                break;
            }
            $ZKTime = $zkEndTime;
        }
        if ($flag) {
            // 在数组开头插入元素
            array_unshift($basisList, ['转科时间【' . $yzb->KZSJ . '】']);
            $this->insertData[] = [
                'basis' => json_encode($basisList, JSON_UNESCAPED_UNICODE),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => '',
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }
        return [];
    }

    /**
     * 新病人入院后没有连记3天
     */
    public function rule420($caseRule = [], $ruleId, $ZYH = "")
    {
        $brry = ZY_BRRY::query()->where('ZYH', $ZYH)->first();
        if (empty($brry)) {
            return [];
        }
        $AAC01 = $brry->AAC01;
        $ruyuanTime = $brry->AAB01;
        // 检查住院时间是否超过3天
        if ($AAC01 && strtotime($AAC01) - strtotime($ruyuanTime) < 72 * 3600) {
            return [];
        }

        $nowTime = time();
        // 没有出院时间，则校验当前时间 - 入院时间是否超过72小时
        if (!$AAC01 && $nowTime - strtotime($ruyuanTime) < 72 * 3600) {
            return [];
        }

        $basisList = [];
        $basisList2 = [];
        // 第一种质控从当天算
        $ZKTime = date('Y-m-d 00:00:00', strtotime($ruyuanTime));
        for ($i = 0; $i < 3; $i++) {
            $zkEndTime = date('Y-m-d H:i:s', strtotime($ZKTime . ' +1 days'));
            $bingchengRecords = EMR_BL_BL01::query()
                ->where('JZHM', $ZYH)
                ->where('BLLB', 294)
                ->whereBetween('ZXSJ', [$ZKTime, $zkEndTime])
                ->get()->toArray();
            if (empty($bingchengRecords)) {
                $basisList[] = ['【' . substr($ZKTime, 0, 10) . '】少一天病程'];
            }
            if (strtotime($zkEndTime) > strtotime($AAC01)) {
                break;
            }
            $ZKTime = $zkEndTime;
        }

        if (!empty($basisList)) {
            // 第二种质控从+8小时后算
            $ZKTime = date('Y-m-d 00:00:00', strtotime($ruyuanTime) + 8 * 60 * 60);
            for ($i = 0; $i < 3; $i++) {
                $zkEndTime = date('Y-m-d H:i:s', strtotime($ZKTime . ' +1 days'));
                $bingchengRecords = EMR_BL_BL01::query()
                    ->where('JZHM', $ZYH)
                    ->where('BLLB', 294)
                    ->whereBetween('ZXSJ', [$ZKTime, $zkEndTime])
                    ->get()->toArray();
                if (empty($bingchengRecords)) {
                    $basisList2[] = ['【' . substr($ZKTime, 0, 10) . '】少一天病程'];
                }
                if (strtotime($zkEndTime) > strtotime($AAC01)) {
                    break;
                }
                $ZKTime = $zkEndTime;
            }
        }

        if (!empty($basisList) && !empty($basisList2)) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, JSON_UNESCAPED_UNICODE),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => '',
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }
        return [];
    }

    public function rule10345($caseRule = [], $ruleId, $ZYH = "")
    {
        $bllb92 = Bllb292::query()->where('ZYH', $ZYH)->first();
        if (empty($bllb92)) {
            return [];
        }

        if (strtotime($bllb92->RYSJ) < strtotime($bllb92->JLSJ)) {
            $this->insertData[] = [
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => '',
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }

        return [];
    }

    public function rule10344($caseRule = [], $ruleId, $ZYH = "")
    {
        $bllb294_295 = Bllb294_295::query()->where('ZYH', $ZYH)->first();
        if (empty($bllb294_295)) {
            return [];
        }

        if (empty($bllb294_295->CBZD)) {
            $this->insertData[] = [
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => '',
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }

        return [];
    }

    /**
     * 手麻中有手术，首页中手术名称不能为空。
     */
    public function rule10342($caseRule = [], $ruleId, $ZYH = "")
    {
        $basisList = [];
        // 检查手麻表中是否有手术记录
        $smSsap = SM_SSAP::query()
            ->where('ZYH', $ZYH)
            ->whereNotNull('ICD9_SSCZMC')
            ->where('ICD9_SSCZMC', '!=', '')
            ->get()->toArray();

        if (empty($smSsap)) {
            return [];
        }

        // 检查首页手术表中手术名称是否为空
        $patientOperation = PatientInfoOperationV2::query()->where('ZYH', $ZYH)->get(['ICD9_NAME'])->toArray();
        $ssmc = array_column($patientOperation, 'ICD9_NAME');
        foreach ($smSsap as $sm) {
            if (!in_array($sm['ICD9_SSCZMC'], $ssmc)) {
                $basisList[] = ['手术名称：' . $sm['ICD9_SSCZMC'], '首页中手术名称【空】'];
            }
        }

        if (!empty($basisList)) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, JSON_UNESCAPED_UNICODE),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => '',
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }
        return [];
    }


    public function rule1304($caseRule = [], $ruleId, $ZYH = "")
    {
        $brry = ZY_BRRY::query()->where('ZYH', $ZYH)->get(['AAB01', 'AAC01'])->toArray();
        if (empty($brry) || empty($brry[0]['AAC01'])) {
            return [];
        }

        if (strtotime($brry[0]['AAC01']) - strtotime($brry[0]['AAB01']) > 86400) {
            return [];
        }

        // 有死亡记录则跳过
        $yzb = Yzb::query()->where('ZYH', $ZYH)->where('YZMC', 'LIKE', '%死亡%')->get()->toArray();
        if (!empty($yzb)) {
            return [];
        }

        $bl01 = EMR_BL_BL01::query()->where('JZHM', $ZYH)->whereIn('MBLB', [21, 20])->get()->toArray();
        if (!empty($bl01)) {
            $blsy = EMR_BL_BLSY::query()->where('BLBH', $bl01[0]['BLBH'])->get()->toArray();
            if (!$blsy) {
                $this->insertData[] = [
                    'basis' => json_encode([['24小时出入院记录【未签名】']], JSON_UNESCAPED_UNICODE),
                    'JZHM' => $ZYH,
                    'rule_id' => $ruleId,
                    'code' => '',
                    'error_field' => $caseRule[$ruleId]['title']
                ];
            }
            return [];
        }
    }

    public function rule1305($caseRule = [], $ruleId, $ZYH = "")
    {

        $brry = ZY_BRRY::query()->where('ZYH', $ZYH)->get(['AAB01', 'AAC01'])->toArray();
        if (empty($brry) || empty($brry[0]['AAC01'])) {
            return [];
        }

        if (strtotime($brry[0]['AAC01']) - strtotime($brry[0]['AAB01']) > 86400) {
            return [];
        }

        // 有死亡记录则跳过
        $yzb = Yzb::query()->where('ZYH', $ZYH)->where('YZMC', 'LIKE', '%死亡%')->get()->toArray();
        if (!empty($yzb)) {
            return [];
        }

        $bl01 = EMR_BL_BL01::query()->where('JZHM', $ZYH)->whereIn('MBLB', [20, 292, 18, 288, 21])->get()->toArray();
        if (empty($bl01)) {
            $this->insertData[] = [
                'basis' => json_encode([['出院时间：' . $brry[0]['AAC01'], '【24小时出入院记录】无']], JSON_UNESCAPED_UNICODE),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => '',
                'error_field' => $caseRule[$ruleId]['title']
            ];

            //发送预警
            $enterTimeEnd = strtotime($brry[0]['AAC01']) + 24 * 3600;
            $diffTime = $enterTimeEnd - time();
            if ($diffTime > 0 && $diffTime < 2 * 3600) {
                $res = remainderTime($diffTime);
                $content = '请在' . $res . '内24小时入出院记录';
                $msgYj = ['出院时间【' . $brry[0]['AAC01'] . '】', '24小时入出院记录【无】'];
                $this->caseService->sendMsg($ZYH, $content, 114, $msgYj);
            }
        }
        return [];
    }
    public function rule1301($caseRule = [], $ruleId, $ZYH = "")
    {
        $brry = ZY_BRRY::query()->where('ZYH', $ZYH)->get(['AAB01', 'AAC01'])->toArray();
        if (empty($brry) || empty($brry[0]['AAC01'])) {
            return [];
        }

        if (strtotime($brry[0]['AAC01']) - strtotime($brry[0]['AAB01']) > 86400) {
            return [];
        }

        // 有死亡记录则跳过
        $yzb = Yzb::query()->where('ZYH', $ZYH)->where('YZMC', 'LIKE', '%死亡%')->get()->toArray();
        if (!empty($yzb)) {
            return [];
        }

        $bl01 = EMR_BL_BL01::query()->where('JZHM', $ZYH)->whereIn('MBLB', [20, 292, 18, 288, 21])->get()->toArray();
        if (!empty($bl01)) {
            $first_blsy_time = $bl01[0]['first_blsy_time'];
            if (strtotime($first_blsy_time) > strtotime($brry[0]['AAC01']) + 86400) {
                $this->insertData[] = [
                    'basis' => json_encode([['出院时间：' . $brry[0]['AAC01'], '【24小时出入院记录】首次签名时间【' . $first_blsy_time . '】超24小时']], JSON_UNESCAPED_UNICODE),
                    'JZHM' => $ZYH,
                    'rule_id' => $ruleId,
                    'code' => '',
                    'error_field' => $caseRule[$ruleId]['title']
                ];
            }
            return [];
        }
    }

    public function rule1300($caseRule = [], $ruleId, $ZYH = "")
    {
        $brry = ZY_BRRY::query()->where('ZYH', $ZYH)->get(['AAB01', 'AAC01'])->toArray();
        if (empty($brry) || empty($brry[0]['AAC01'])) {
            return [];
        }

        if (strtotime($brry[0]['AAC01']) - strtotime($brry[0]['AAB01']) > 86400) {
            return [];
        }

        $yzb = Yzb::query()->where('ZYH', $ZYH)->where('YZMC', 'LIKE', '%死亡%')->get()->toArray();
        if (empty($yzb)) {
            return [];
        }

        $bl01 = EMR_BL_BL01::query()->where('JZHM', $ZYH)->whereIn('MBLB', [288, 21])->get()->toArray();
        if (!empty($bl01)) {
            $blsy = EMR_BL_BLSY::query()->where('BLBH', $bl01[0]['BLBH'])->get()->toArray();
            if (!$blsy) {
                $this->insertData[] = [
                    'basis' => json_encode([['24小时入院死亡记录【未签名】']], JSON_UNESCAPED_UNICODE),
                    'JZHM' => $ZYH,
                    'rule_id' => $ruleId,
                    'code' => '',
                    'error_field' => $caseRule[$ruleId]['title']
                ];
            }
            return [];
        }
    }


    public function rule1299($caseRule = [], $ruleId, $ZYH = "")
    {
        $brry = ZY_BRRY::query()->where('ZYH', $ZYH)->get(['AAB01', 'AAC01'])->toArray();
        if (empty($brry) || empty($brry[0]['AAC01'])) {
            return [];
        }

        if (strtotime($brry[0]['AAC01']) - strtotime($brry[0]['AAB01']) > 86400) {
            return [];
        }

        $keyword8003 = RuleWordMap::getArrayById(8003);
        $yzb = Yzb::query()->where('ZYH', $ZYH)->where(function ($query) use ($keyword8003) {
            foreach ($keyword8003 as $key => $v) {
                $query->orWhere('YZMC', 'LIKE', $v);
            }
        })->get()->toArray();
        if (empty($yzb)) {
            return [];
        }

        $keyword8007 = RuleWordMap::getArrayById(8007);
        $bl01 = EMR_BL_BL01::query()->where('JZHM', $ZYH)->whereIn('MBLB', $keyword8007)->get()->toArray();
        if (!empty($bl01)) {
            $bllb288 = Bllb288::query()->where('ZYH', $ZYH)->get()->toArray();
            if (empty($bllb288)) {
                return [];
            }
            $zxsj = $bl01[0]['ZXSJ'];
            $first_blsy_time = $bl01[0]['first_blsy_time'];
            if (strtotime($zxsj) <= strtotime($brry[0]['AAC01']) + 86400) {
                if (strtotime($first_blsy_time) > strtotime($brry[0]['AAC01']) + 86400) {
                    $this->insertData[] = [
                        'basis' => json_encode([['死亡时间：' . $bllb288[0]['SWSJ'], '【' . $bl01[0]['BLMC'] . '】', '首次签名时间【' . $first_blsy_time . '】超24小时']], JSON_UNESCAPED_UNICODE),
                        'JZHM' => $ZYH,
                        'rule_id' => $ruleId,
                        'code' => '',
                        'error_field' => $caseRule[$ruleId]['title']
                    ];
                }
            }
            return [];
        }
    }

    public function rule1298($caseRule = [], $ruleId, $ZYH = "")
    {
        $brry = ZY_BRRY::query()->where('ZYH', $ZYH)->get(['AAB01', 'AAC01'])->toArray();
        if (empty($brry) || empty($brry[0]['AAC01'])) {
            return [];
        }

        if (strtotime($brry[0]['AAC01']) - strtotime($brry[0]['AAB01']) > 86400) {
            return [];
        }

        $yzb = Yzb::query()->where('ZYH', $ZYH)->where('YZMC', 'LIKE', '%死亡%')->get()->toArray();
        if (empty($yzb)) {
            return [];
        }

        $bl01 = EMR_BL_BL01::query()->where('JZHM', $ZYH)->whereIn('MBLB', [288, 21])->get()->toArray();
        if (empty($bl01)) {
            $this->insertData[] = [
                'basis' => json_encode([['【24小时入院死亡记录】无']], JSON_UNESCAPED_UNICODE),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => '',
                'error_field' => $caseRule[$ruleId]['title']
            ];
            //发送预警
            $enterTimeEnd = strtotime($brry[0]['AAC01']) + 24 * 3600;
            $diffTime = $enterTimeEnd - time();
            if ($diffTime > 0 && $diffTime < 2 * 3600) {
                $res = remainderTime($diffTime);
                $content = '请在' . $res . '内24小时入院死亡记录';
                $msgYj = ['出院时间【' . $brry[0]['AAC01'] . '】', '24小时入院死亡记录【无】'];
                $this->caseService->sendMsg($ZYH, $content, 114, $msgYj);
            }
            return [];
        }
    }

    function rule1296($caseRule = [], $ruleId, $ZYH = "")
    {

        $keyword20036 = RuleWordMap::getArrayById(20036);
        $yzb = Yzb::query()->where('ZYH', $ZYH)->where(function ($query) use ($keyword20036) {
            foreach ($keyword20036 as $v) {
                $query->orWhere('YZMC', 'like', '%' . $v . '%');
            }
        })->get()->toArray();
        if (empty($yzb)) {
            return [];
        }
        $keyword20038 = RuleWordMap::getArrayById(20038);
        $bl01 = EMR_BL_BL01::query()->where('JZHM', $ZYH)->where('BLLB', 294)->where(function ($query) use ($keyword20038) {
            foreach ($keyword20038 as $v) {
                $query->orWhere('BLMC', 'like', '%' . $v . '%');
            }
        })->get()->toArray();
        if (count($bl01) != count($yzb)) {
            $yzbmc = [];
            foreach ($yzb as $k => $v) {
                $yzbmc[] = '【' . $v['YZMC'] . '】【' . $v['KZSJ'] . '】';
            }
            $basisList[] = array_merge(['抢救医嘱（共' . count($yzb) . '次）'], $yzbmc);

            $qjjl = [];
            foreach ($bl01 as $k => $v) {
                $blxg = EMR_BL_BLXG::query()->where('BLBH', $v['BLBH'])->get()->toArray();
                $hjnr = $blxg[0]['HJNR'];
                $rescueStart = '';
                if (preg_match('/抢救开始时间[：:]\s*([0-9\- :]{10,16})/u', $hjnr, $kzMatches)) {
                    $rescueStart = $kzMatches[1];
                }
                $qjjl[] = '【' . $v['BLMC'] . '】【' . $rescueStart . '】';
            }
            $basisList[] = array_merge(['抢救记录（共' . count($bl01) . '次）'], $qjjl);
            $this->insertData[] = [
                'basis' => json_encode($basisList, JSON_UNESCAPED_UNICODE),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => '',
                'error_field' => $caseRule[$ruleId]['title']
            ];
            return [];
        }
    }

    /**
     * 放射治疗知情同意书无医师签字
     */
    public function rule1295($caseRule = [], $ruleId, $ZYH = "")
    {

        $keyword20035 = RuleWordMap::getArrayById(20035);
        $tys = EMR_BL_BL01::query()->where('JZHM', $ZYH)->where('BLLB', 329)->where(function ($query) use ($keyword20035) {
            foreach ($keyword20035 as $v) {
                $query->orWhere('BLMC', 'like', '%' . $v . '%');
            }
        })->get()->toArray();
        if (empty($tys)) {
            return [];
        }

        $blsy1 = EMR_BL_BLSY::query()->where('BLBH', $tys[0]['BLBH'])->where('QMLX', 1)->get()->toArray();
        if (empty($blsy1)) {
            $basisList[] = ['【' . $tys[0]['BLMC'] . '】无医师签字'];
            $this->insertData[] = [
                'basis' => json_encode($basisList, JSON_UNESCAPED_UNICODE),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => '',
                'error_field' => $caseRule[$ruleId]['title']
            ];
            return [];
        }
    }

    /**
     * 放射治疗知情同意书无患方签字
     */
    public function rule1294($caseRule = [], $ruleId, $ZYH = "")
    {
        $keyword20035 = RuleWordMap::getArrayById(20035);
        $tys = EMR_BL_BL01::query()->where('JZHM', $ZYH)->where('BLLB', 329)->where(function ($query) use ($keyword20035) {
            foreach ($keyword20035 as $v) {
                $query->orWhere('BLMC', 'like', '%' . $v . '%');
            }
        })->get()->toArray();
        if (empty($tys)) {
            return [];
        }

        $blsy1 = EMR_BL_BLSY::query()->where('BLBH', $tys[0]['BLBH'])->where('QMLX', 2)->get()->toArray();
        if (empty($blsy1)) {
            $basisList[] = ['【' . $tys[0]['BLMC'] . '】无患方签字'];
            $this->insertData[] = [
                'basis' => json_encode($basisList, JSON_UNESCAPED_UNICODE),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => '',
                'error_field' => $caseRule[$ruleId]['title']
            ];
            return [];
        }
    }


    /**
     * 无放射治疗知情同意书
     */
    function rule1293($caseRule = [], $ruleId, $ZYH = "")
    {
        $yzb = Yzb::query()->where('ZYH', $ZYH)->where('is_fangliao', 1)->get()->toArray();
        // 若无此类医嘱记录，则不触发本规则。
        if (empty($yzb)) {
            return [];
        }
        $keyword20035 = RuleWordMap::getArrayById(20035);

        $tys = EMR_BL_BL01::query()->where('JZHM', $ZYH)->where('BLLB', 329)->where(function ($query) use ($keyword20035) {
            foreach ($keyword20035 as $v) {
                $query->orWhere('BLMC', 'like', '%' . $v . '%');
            }
        })->get()->toArray();
        if (empty($tys)) {
            $basisList[] = ['放疗医嘱【存在放疗医嘱（' . $yzb[0]['YZMC'] . '）】', '开嘱时间【' . $yzb[0]['KZSJ'] . '】，但无放射治疗知情同意书'];
            $this->insertData[] = [
                'basis' => json_encode($basisList, JSON_UNESCAPED_UNICODE),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => '',
                'error_field' => $caseRule[$ruleId]['title']
            ];
            return [];
        }
    }

    /**
     * 化学治疗知情同意书无医师签字
     */
    public function rule1292($caseRule = [], $ruleId, $ZYH = "")
    {

        $keyword20024 = RuleWordMap::getArrayById(20024);
        $tys = EMR_BL_BL01::query()->where('JZHM', $ZYH)->where('BLLB', 329)->where(function ($query) use ($keyword20024) {
            foreach ($keyword20024 as $v) {
                $query->orWhere('BLMC', 'like', '%' . $v . '%');
            }
        })->get()->toArray();
        if (empty($tys)) {
            return [];
        }

        $blsy1 = EMR_BL_BLSY::query()->where('BLBH', $tys[0]['BLBH'])->where('QMLX', 1)->get()->toArray();
        if (empty($blsy1)) {
            $basisList[] = ['【' . $tys[0]['BLMC'] . '】无医师签字'];
            $this->insertData[] = [
                'basis' => json_encode($basisList, JSON_UNESCAPED_UNICODE),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => '',
                'error_field' => $caseRule[$ruleId]['title']
            ];
            return [];
        }
    }

    /**
     * 化学治疗知情同意书无患方签字
     */
    public function rule1291($caseRule = [], $ruleId, $ZYH = "")
    {

        $keyword20024 = RuleWordMap::getArrayById(20024);
        $tys = EMR_BL_BL01::query()->where('JZHM', $ZYH)->where('BLLB', 329)->where(function ($query) use ($keyword20024) {
            foreach ($keyword20024 as $v) {
                $query->orWhere('BLMC', 'like', '%' . $v . '%');
            }
        })->get()->toArray();
        if (empty($tys)) {
            return [];
        }

        $blsy1 = EMR_BL_BLSY::query()->where('BLBH', $tys[0]['BLBH'])->where('QMLX', 2)->get()->toArray();
        if (empty($blsy1)) {
            $basisList[] = ['【' . $tys[0]['BLMC'] . '】无患方签字'];
            $this->insertData[] = [
                'basis' => json_encode($basisList, JSON_UNESCAPED_UNICODE),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => '',
                'error_field' => $caseRule[$ruleId]['title']
            ];
            return [];
        }
    }

    /**
     * 无化学治疗知情同意书
     */
    function rule1290($caseRule = [], $ruleId, $ZYH = "")
    {
        // 在医嘱表（yzb）中，取 is_has_hlyw=1（包含化疗药品）的记录。
        $yzb = Yzb::query()->where('ZYH', $ZYH)->where('is_has_hlyw', 1)->get()->toArray();
        // 若无此类医嘱记录，则不触发本规则。
        if (empty($yzb)) {
            return [];
        }
        // 2. 同意书校验
        // - 在病历表（bl01）中，取 bllb=329 且 blmc 包含id  化学治疗知情同意书的记录（基于字典配置）。
        // - 若不存在此类文书，则判定为缺陷
        $keyword20024 = RuleWordMap::getFirstById(20024);
        $tys = EMR_BL_BL01::query()->where('JZHM', $ZYH)->where('BLMC', 'like', '%' . $keyword20024['keyword'] . '%')->get()->toArray();
        if (empty($tys)) {
            $basisList[] = ['化疗医嘱【存在化疗医嘱（' . $yzb[0]['YZMC'] . '）】', '开嘱时间【' . $yzb[0]['KZSJ'] . '】，但无化学治疗知情同意书'];
            $this->insertData[] = [
                'basis' => json_encode($basisList, JSON_UNESCAPED_UNICODE),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => '',
                'error_field' => $caseRule[$ruleId]['title']
            ];
            return [];
        }
    }

    /**
     * 无输血前9项检测报告
     */
    public function rule1289($caseRule = [], $ruleId, $ZYH = "")
    {

        $ss = ZY_SS::query()->where('ZYH', $ZYH)->orderBy('KSSJ', 'asc')->limit(1)->get()->toArray();
        if (!$ss) {
            return [];
        }
        $kssj = $ss[0]['KSSJ'];
        $cfxKssj = '首次输血开始时间【' . $ss[0]['CFX'] . $ss[0]['KSSJ'] . '】';

        $basisList = [$cfxKssj];
        $keyword20023 = RuleWordMap::getFirstById(20023);
        $tys = EMR_BL_BL01::query()->where('JZHM', $ZYH)->where('BLLB', 329)->where(function ($query) use ($keyword20023) {
            if (isset($keyword20023['keyword'])) {
                $query->where('BLMC', 'like', '%' . $keyword20023['keyword'] . '%');
            } else {
                foreach ($keyword20023 as $item) {
                    $query->orWhere('BLMC', 'like', '%' . $item . '%');
                }
            }
        })->get()->toArray();
        if (empty($tys)) {
            $basisList[] = ['缺少【' . ($keyword20023['keyword'] ?? implode(',', $keyword20023)) . '】'];
            $this->insertData[] = [
                'basis' => json_encode($basisList, JSON_UNESCAPED_UNICODE),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => '',
                'error_field' => $caseRule[$ruleId]['title']
            ];
            return [];
        }

        $blsy1 = EMR_BL_BLSY::query()->where('BLBH', $tys[0]['BLBH'])->where('QMLX', 1)->get()->toArray();
        if (empty($blsy1)) {
            $basisList[] = '【' . $tys[0]['BLMC'] . '】医生未签名';
        } elseif (strtotime($blsy1[0]['JLSJ']) > strtotime($kssj)) {
            $basisList[] = '【' . $tys[0]['BLMC'] . '】医生签名时间【' . $blsy1[0]['JLSJ'] . '】晚于输血开始时间【' . $kssj . '】';
        }

        $blsy2 = EMR_BL_BLSY::query()->where('BLBH', $tys[0]['BLBH'])->where('QMLX', 2)->get()->toArray();
        if (empty($blsy2)) {
            $basisList[] = '【' . $tys[0]['BLMC'] . '】患者未签字';
        } elseif (strtotime($blsy2[0]['JLSJ']) > strtotime($kssj)) {
            $basisList[] = '【' . $tys[0]['BLMC'] . '】患者签字时间【' . $blsy2[0]['JLSJ'] . '】晚于输血开始时间【' . $kssj . '】';
        }

        // - 通过 blbh 关联病历内容表（blxg），取 hjnr（文书内容）。
        $blxg = EMR_BL_BLXG::query()->where('BLBH', $tys[0]['BLBH'])->first();
        $hjnr = '';
        if (!empty($blxg)) {
            $hjnr = $blxg->HJNR;
        }

        // - 若文书中不同时包含以下9项检测内容，则判定为缺陷.
        $flag = false;
        $keyword20025 = RuleWordMap::getArrayById(20025);
        foreach ($keyword20025 as $v) {
            if (stripos($hjnr, $v) !== false) {
                $flag = true;
                break;
            }
        }
        if ($flag == false) {
            $basisList[] = '缺少【' . ($keyword20025['keyword'] ?? implode(',', $keyword20025)) . '】';
        }

        $flag = false;
        $keyword20026 = RuleWordMap::getArrayById(20026);
        foreach ($keyword20026 as $v) {
            if (stripos($hjnr, $v) !== false) {
                $flag = true;
                break;
            }
        }
        if ($flag == false) {
            $basisList[] = '缺少【' . ($keyword20026['keyword'] ?? implode(',', $keyword20026)) . '】';
        }

        $flag = false;
        $keyword20027 = RuleWordMap::getArrayById(20027);
        foreach ($keyword20027 as $v) {
            if (stripos($hjnr, $v) !== false) {
                $flag = true;
                break;
            }
        }
        if ($flag == false) {
            $basisList[] = '缺少【' . ($keyword20027['keyword'] ?? implode(',', $keyword20027)) . '】';
        }

        $flag = false;
        $keyword20028 = RuleWordMap::getArrayById(20028);
        foreach ($keyword20028 as $v) {
            if (stripos($hjnr, $v) !== false) {
                $flag = true;
                break;
            }
        }
        if ($flag == false) {
            $basisList[] = '缺少【' . ($keyword20028['keyword'] ?? implode(',', $keyword20028)) . '】';
        }

        $flag = false;
        $keyword20029 = RuleWordMap::getArrayById(20029);
        foreach ($keyword20029 as $v) {
            if (stripos($hjnr, $v) !== false) {
                $flag = true;
                break;
            }
        }
        if ($flag == false) {
            $basisList[] = '缺少【' . ($keyword20029['keyword'] ?? implode(',', $keyword20029)) . '】';
        }
        $flag = false;
        $keyword20030 = RuleWordMap::getArrayById(20030);
        foreach ($keyword20030 as $v) {
            if (stripos($hjnr, $v) !== false) {
                $flag = true;
                break;
            }
        }
        if ($flag == false) {
            $basisList[] = '缺少【' . ($keyword20030['keyword'] ?? implode(',', $keyword20030)) . '】';
        }

        $flag = false;
        $keyword20031 = RuleWordMap::getArrayById(20031);
        foreach ($keyword20031 as $v) {
            if (stripos($hjnr, $v) !== false) {
                $flag = true;
                break;
            }
        }
        if ($flag == false) {
            $basisList[] = '缺少【' . ($keyword20031['keyword'] ?? implode(',', $keyword20031)) . '】';
        }

        $flag = false;
        $keyword20032 = RuleWordMap::getArrayById(20032);
        foreach ($keyword20032 as $v) {
            if (stripos($hjnr, $v) !== false) {
                $flag = true;
                break;
            }
        }
        if ($flag == false) {
            $basisList[] = '缺少【' . ($keyword20032['keyword'] ?? implode(',', $keyword20032)) . '】';
        }

        $flag = false;
        $keyword20033 = RuleWordMap::getArrayById(20033);
        foreach ($keyword20033 as $v) {
            if (stripos($hjnr, $v) !== false) {
                $flag = true;
                break;
            }
        }
        if ($flag == false) {
            $basisList[] = '缺少【' . ($keyword20033['keyword'] ?? implode(',', $keyword20033)) . '】';
        }

        if (!empty($basisList)) {
            $this->insertData[] = [
                'basis' => json_encode([$basisList], JSON_UNESCAPED_UNICODE),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => '',
                'error_field' => $caseRule[$ruleId]['title']
            ];
            return [];
        }
    }

    public function rule1282($caseRule = [], $ruleId, $ZYH = "")
    {
        $staff = Staff::query()->get()->toArray();
        $staff = array_column($staff, null, 'code');
        $keyword20018 = RuleWordMap::getFirstById(20018);
        $keyword20019 = RuleWordMap::getFirstById(20019);
        $yzb = Yzb::query()->where('ZYH', $ZYH)->get()->toArray();
        $hasBw = false;
        if (empty($yzb)) {
            return [];
        }

        $bwYzb = [];
        foreach ($yzb as $v) {
            foreach ($keyword20018 as $kw) {
                if (strpos($v['YZMC'], $kw) !== false) {
                    $bwYzb = $v;
                    $hasBw = true;
                    break;
                }
            }
        }

        if (empty($hasBw)) {
            return [];
        }

        $KZSJ = $bwYzb['KZSJ'] ?? "";
        if (empty($KZSJ)) {
            return [];
        }

        // 查看开嘱时间KZSJ是不是入院当天
        $brry = ZY_BRRY::query()->where('ZYH', $ZYH)->first();
        if (!$brry) {
            return [];
        }
        $AAB01 = $brry->AAB01 ?? "";
        if (date('Y-m-d', strtotime($KZSJ)) != date('Y-m-d', strtotime($AAB01))) {
            return [];
        }

        $cfsjStart = date("Y-m-d 00:00:00", strtotime($KZSJ));
        $cfsj = date("Y-m-d 23:59:59", strtotime($KZSJ));
        $cfbc = EMR_BL_BL01::query()->where('JZHM', $ZYH)->where('BLLB', 294)->whereBetween("ZXSJ", [$cfsjStart, $cfsj])->get()->toArray();
        $hasSj = false;
        foreach ($cfbc as $key => $cf) {
            foreach ($keyword20019 as $kw) {
                if (strpos($cf['BLMC'], $kw) !== false) {
                    $hasSj = true;
                    break;
                }
            }
            $blsy = EMR_BL_BLSY::query()->where('BLBH', $cf['BLBH'])->get()->toArray();
            if ($blsy) {
                foreach ($blsy as $bl) {
                    if (empty($staff[$bl['SYYS']])) {
                        continue;
                    }
                    foreach ($keyword20019 as $kw) {
                        if (strpos($staff[$bl['SYYS']]['ygjb_text'], $kw) !== false) {
                            $hasSj = true;
                            break;
                        }
                    }
                }
            }
        }

        if (empty($hasSj)) {
            $this->insertData[] = [
                'basis' => json_encode([['医嘱名称【' . $bwYzb['YZMC'] . '】', '开嘱时间【' . $KZSJ . '】，当天无上级医师查房']], JSON_UNESCAPED_UNICODE),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => '',
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }
    }

    /**
     * 【首次病程记录】鉴别诊断和依据书写过于简单
     */
    function rule1281($caseRule = [], $ruleId, $ZYH = "")
    {

        $keyword20016 = RuleWordMap::getFirstById(20016);
        $keyword20017 = RuleWordMap::getFirstById(20017);

        $bllb294295 = Bllb294_295::query()->where('ZYH', $ZYH)->get()->toArray();
        if (empty($bllb294295)) {
            return [];
        }

        $jbzd = $bllb294295[0]['JBZD'];
        if (empty($jbzd)) {
            return [];
        }

        if (strpos($jbzd, $keyword20016['keyword']) !== false) {
            return [];
        }

        if (mb_strlen($jbzd) < $keyword20017['keyword']) {
            $this->insertData[] = [
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => '',
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }

        return [];
    }

    /**
     * 术后24小时未完成有创操作记录
     * @param $caseRule
     * @param $ruleId
     * @param $ZYH
     * @return bool
     * @author lzh
     */
    function rule1275($caseRule = [], $ruleId, $ZYH = "")
    {

        // 1. 优先获取手麻系统（SM_SSAP）手术结束时间，匹配名称（ICD9_SSCZMC 与 IVD9_NAME 一致）
        $keyword20014 = RuleWordMap::getArrayById(20014);
        $ssmzRecord = SM_SSAP::query()
            ->where('ZYH', $ZYH)
            ->orderBy('JSRQ', 'asc')
            ->get()->toArray();

        $sscz = YCCZ::query()->get()->toArray();
        $ssczmc = array_column($sscz, 'name');
        // 若手麻系统无数据，则查病案首页：手术日期当天或次日有有创操作记录即可。
        $basisList = [];
        if (empty($ssmzRecord)) {
            $patientOp = PatientInfoOperationV2::query()
                ->where('ZYH', $ZYH)
                ->get()->toArray();
            if (empty($patientOp)) {
                return false;
            }
            foreach ($patientOp as $op) {
                if (!in_array($op['ICD9_NAME'], $ssczmc)) {
                    continue;
                }
                $startTime = $op['OPE_DATE'];
                $endTime = date('Y-m-d 23:59:59', strtotime($startTime) + 24 * 3600);
                $query = EMR_BL_BL01::query()
                    ->where('JZHM', $ZYH)
                    ->whereBetween('ZXSJ', [$startTime, $endTime]);
                if ($keyword20014) {
                    foreach ($keyword20014 as $keyword) {
                        $query = $query->where('BLMC', 'like', '%' . $keyword . '%');
                    }
                }
                $youChuangRecords = $query->get()->toArray();
                $basis = [
                    "（首页）手术名称：{$op['ICD9_NAME']}",
                    '（首页）手术日期:' . $op['OPE_DATE'],
                ];
                if (empty($youChuangRecords)) {

                    $query = EMR_BL_BL01::query()
                        ->where('JZHM', $ZYH)
                        ->where('BLLB', 303)
                        ->whereBetween('ZXSJ', [$startTime, $endTime]);
                    $ssjl = $query->get()->toArray();
                    if (empty($ssjl)) {
                        $basis[] = '有创操作记录无';
                        $basisList[] = $basis;
                    }
                } else {
                    $basis[] = '有创操作记录:' . ($youChuangRecords[0]['BLMC'] ?? '');
                    $basis[] = '标题时间:' . ($youChuangRecords[0]['ZXSJ'] ?? '');
                    if (strtotime($youChuangRecords[0]['first_blsy_time']) > strtotime($endTime)) {
                        $basis[] = '首次完成时间:【' . ($youChuangRecords[0]['first_blsy_time'] ?? '') . '】超时';
                        $basisList[] = $basis;
                    }
                }
            }
        } else {
            foreach ($ssmzRecord as $op) {
                // 手术名称与“手术操作名称”一致时，24小时内完成有创操作记录。
                if (in_array($op['ICD9_SSCZMC'], $ssczmc)) {
                    $startTime = $op['SSRQ'];
                    $endTime = date('Y-m-d 23:59:59', strtotime($startTime) + 24 * 3600);
                    $query = EMR_BL_BL01::query()
                        ->where('JZHM', $ZYH)
                        ->whereBetween('ZXSJ', [$startTime, $endTime]);
                    if ($keyword20014) {
                        foreach ($keyword20014 as $keyword) {
                            $query = $query->where('BLMC', 'like', '%' . $keyword . '%');
                        }
                    }
                    $youChuangRecords = $query->get()->toArray();
                    $basis = [
                        "（首页）手术名称：{$op['ICD9_SSCZMC']}",
                        '（首页）手术日期:' . $op['SSRQ'],
                    ];
                    if (empty($youChuangRecords)) {

                        $query = EMR_BL_BL01::query()
                            ->where('JZHM', $ZYH)
                            ->where('MBLB', 306)
                            ->whereBetween('ZXSJ', [$startTime, $endTime]);
                        $ssjl = $query->get()->toArray();
                        if (empty($ssjl)) {
                            $basis[] = '有创操作记录无';
                            $basisList[] = $basis;
                        }
                    } else {
                        $basis[] = '有创操作记录:' . ($youChuangRecords[0]['BLMC'] ?? '');
                        $basis[] = '标题时间:' . ($youChuangRecords[0]['ZXSJ'] ?? '');
                        if (strtotime($youChuangRecords[0]['first_blsy_time']) > strtotime($endTime)) {
                            $basis[] = '首次完成时间:【' . ($youChuangRecords[0]['first_blsy_time'] ?? '') . '】超时';
                            $basisList[] = $basis;
                        }
                    }
                }
            }
        }

        if (!empty($basisList)) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, 256),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => '',
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }

        return true;
    }

    /**
     *  四级手术术前多学科讨论记录参加科室少于3个，不符合多学科讨论要求
     */
    function rule1274($caseRule = [], $ruleId, $ZYH = "")
    {
        $level4Oparation = PatientInfoOperationV2::query()->where('ZYH', $ZYH)->where('OPE_LEVEL', 4)->get()->toArray();
        if (empty($level4Oparation)) {
            return false;
        }
        $keyword20011 = RuleWordMap::getArrayById(20011);
        $keyword20012 = RuleWordMap::getArrayById(20012);
        $keyword20013 = RuleWordMap::getFirstById(20013);

        foreach ($level4Oparation as $l4) {
            $endDatetime = date('Y-m-d', strtotime($l4['OPE_DATE']));

            // 获取$time之前24小时内的“术前讨论”记录
            $startDatetime = date('Y-m-d H:i:s', strtotime($endDatetime) - 24 * 3600);
            $query = EMR_BL_BL01::query()
                ->where('JZHM', $ZYH)
                ->where(function ($query) use ($keyword20011) {
                    foreach ($keyword20011 as $keyword) {
                        $query->orWhere('BLMC', 'like', '%' . $keyword . '%');
                    }
                })
                ->where('ZXSJ', '<', $startDatetime);
            $sqtlRecords = $query->get()->toArray();

            if (empty($sqtlRecords)) {
                // $basisList[] = ["手术名称：{$l4['ICD9_NAME']}", "手术时间：{$l4['OPE_DATE']}", "术前讨论【无】"];
                continue;
            }

            $blxg = EMR_BL_BLXG::query()->where('BLBH', $sqtlRecords[0]['BLBH'])->first();
            $blxgHjnr = $blxg->HJNR;
            // 用正则匹配$blxgHjnr中参会人员和讨论记录之间的全部内容
            $pattern = '/参会人员(.*?)(讨论记录|主持人总结)/su';
            $chry = '';
            if (preg_match($pattern, $blxgHjnr, $matches)) {
                $chry = trim($matches[1]);
            }
            $depNum = [];
            foreach ($keyword20012 as $keyword) {
                if (stripos($chry, $keyword) !== false) {
                    $depNum[] = $keyword;
                }
            }
            if (count($depNum) < $keyword20013['keyword']) {
                $basisList[] = ["手术名称：{$l4['ICD9_NAME']}", "术前讨论【{$sqtlRecords[0]['BLMC']}】，少于3个科室参与"];
            }
        }
        if (!empty($basisList)) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, 256),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => '',
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }

        return true;
    }

    /**
     * 诊断续页未签名 bllb=999999999
     */
    function rule1270($caseRule = [], $ruleId, $ZYH = "")
    {

        // 获取当前住院号对应的手术信息（四级手术）
        $bl01 = EMR_BL_BL01::query()->where('JZHM', '=', $ZYH)->get()->toArray();
        if (empty($bl01)) {
            return false;
        }
        $basisList = [];
        foreach ($bl01 as $item) {
            if (isset($item['BLMC']) && stripos($item['BLMC'], '诊断续页') !== false) {
                $blsy = EMR_BL_BLSY::query()->where('BLBH', $item['BLBH'])->count();
                if (empty($blsy)) {
                    $basisList[] = ["blmc含【{$item['BLMC']}】，blsy没有签名"];
                }
            }
        }

        if (!empty($basisList)) {
            $this->insertData[] = [
                'basis' => json_encode([], 256),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => '',
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }

        return true;
    }

    /**
     * 手术风险评估表未签名
     */
    function rule1271($caseRule = [], $ruleId, $ZYH = "")
    {

        // 获取当前住院号对应的手术信息（四级手术）
        $bl01 = EMR_BL_BL01::query()->where('JZHM', '=', $ZYH)->get()->toArray();
        if (empty($bl01)) {
            return false;
        }
        $basisList = [];
        foreach ($bl01 as $item) {
            if (isset($item['BLMC']) && stripos($item['BLMC'], '手术风险评估表') !== false) {
                $blsy = EMR_BL_BLSY::query()->where('BLBH', $item['BLBH'])->count();
                if (empty($blsy)) {
                    $basisList[] = ["blmc含【{$item['BLMC']}】，blsy没有签名"];
                }
            }
        }

        if (!empty($basisList)) {
            $this->insertData[] = [
                'basis' => json_encode([], 256),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => '',
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }

        return true;
    }

    /**
     * 手术安全核查表未签名
     */
    function rule1272($caseRule = [], $ruleId, $ZYH = "")
    {

        $bl01 = EMR_BL_BL01::query()->where('JZHM', '=', $ZYH)->get()->toArray();
        if (empty($bl01)) {
            return false;
        }
        $basisList = [];
        foreach ($bl01 as $item) {
            if (isset($item['BLMC']) && stripos($item['BLMC'], '手术安全核查表') !== false) {
                $blsy = EMR_BL_BLSY::query()->where('BLBH', $item['BLBH'])->count();
                if (empty($blsy)) {
                    $basisList[] = ["blmc含【{$item['BLMC']}】，blsy没有签名"];
                }
            }
        }

        if (!empty($basisList)) {
            $this->insertData[] = [
                'basis' => json_encode([], 256),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => '',
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }

        return true;
    }

    /**
     * 死亡医学推断书未签名
     */
    function rule1273($caseRule = [], $ruleId, $ZYH = "")
    {

        $bl01 = EMR_BL_BL01::query()->where('JZHM', '=', $ZYH)->get()->toArray();
        if (empty($bl01)) {
            return false;
        }
        $basisList = [];
        foreach ($bl01 as $item) {
            if (isset($item['BLMC']) && stripos($item['BLMC'], '死亡医学推断书') !== false) {
                $blsy = EMR_BL_BLSY::query()->where('BLBH', $item['BLBH'])->count();
                if (empty($blsy)) {
                    $basisList[] = ["blmc含【{$item['BLMC']}】，blsy没有签名"];
                }
            }
        }

        if (!empty($basisList)) {
            $this->insertData[] = [
                'basis' => json_encode([], 256),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => '',
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }

        return true;
    }

    function rule567($caseRule = [], $ruleId, $ZYH = "")
    {

        // 查询四级手术（使用病案首页数据）
        $mainoperation = MainOperation::query()->where('AAA28', '=', $ZYH)->where('OPE_LEVEL', '=', '4')->get()->toArray();
        $secondaryoperation = SecondaryOperation::query()->where('AAA28', '=', $ZYH)->where('OPE_LEVEL', '=', '4')->get()->toArray();
        $results = array_merge($mainoperation, $secondaryoperation);

        // 按照START_TIME分组去重
        $uniqueResults = [];
        foreach ($results as $item) {
            if (empty($item['START_TIME'])) {
                continue; // 没有START_TIME则跳过
            }
            $uniqueResults[$item['START_TIME']] = $item; // 用START_TIME作为key，自动去重
        }
        $results = array_values($uniqueResults);

        if (!$results) {
            return [];
        }

        $m = 1;
        $secsj = null;
        $basisList = [];
        foreach ($results as $surgeryGroup) {
            $basis = [];
            //获取手术开始时间
            $sskssj = $surgeryGroup['START_TIME'] ?? '';
            //获取手术结束时间
            $ssjssj = $surgeryGroup['END_TIME'] ?? '';

            if (empty($sskssj)) {
                $secsj = $ssjssj;
                $m++;
                continue;
            }

            $bl01Res = EMR_BL_BL01::query()->where('JZHM', $ZYH)
                ->where('BLLB', 294)
                ->where('BLMC', 'like', '%多学科讨论%')
                ->when($m == 1, function ($query) use ($sskssj) {
                    $query->where('ZXSJ', '<=', $sskssj);
                })
                ->when($m != 1, function ($query) use ($secsj, $sskssj) {
                    $query->where('ZXSJ', '>', $secsj)
                        ->where('ZXSJ', '<=', $sskssj);
                })
                ->get()->toArray();

            if (empty($bl01Res)) {
                $basis[] = "手术名称【" . ($surgeryGroup['ICD9_NAME'] ?? '') . "】";
                $basis[] = "手术开始时间【" . ($surgeryGroup['START_TIME'] ?? '') . "】";
                $basis[] = "手术结束时间【" . ($surgeryGroup['END_TIME'] ?? '') . "】";
                $basis[] = "术前多学科讨论【无】";
                $basisList[] = $basis;
            }
            $secsj = $ssjssj;
            $m++;
        }


        if (!empty($basisList)) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, 256),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => '',
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }

        return [];
    }

    /**
     * 现病史描述缺少诊疗过程，以及加重一周的症状和程度
     * @param
     */
    public function rule1267($caseRule = [], $ruleId, $ZYH = "")
    {
        $ryjl = bllb292::query()
            ->where('ZYH', $ZYH)
            ->first();
        if (empty($ryjl)) {
            return [];
        }

        $nl = $ryjl['NL'] ?? '';

        $rulewordmap8116 = RuleWordMap::query()->where('id', 8116)->first();
        if (!empty($rulewordmap8116)) {
            //包含逗号分隔
            if (strpos($rulewordmap8116['keyword'], ',') !== false) {
                $keywords = explode(',', $rulewordmap8116['keyword']);
            } else {
                $keywords = [$rulewordmap8116['keyword']];
            }

            //如果nl不包含就不质控
            $is_control = false;
            foreach ($keywords as $keyword) {
                if (strpos($nl, $keyword) !== false) {
                    $is_control = true;
                    break;
                }
            }
            if (!$is_control) {
                return [];
            }
        }

        $hjnr = $ryjl['XBS'] ?? '';
        if (empty($hjnr)) {
            return [];
        }

        // 判断$hjnr中是否包含口服什么药物，行什么术，进行/给予什么治疗等关键字，如果包含则跳过
        $keywords = [
            '就诊于',
            '未重视及诊治',
            '行.*?治疗',        // 行什么术
            '口服',
            '行.*?术',        // 行什么术
            '进行.*?治疗',    // 进行什么治疗
            '给予.*?治疗',    // 给予什么治疗
        ];

        foreach ($keywords as $pattern) {
            if (preg_match('/' . $pattern . '/u', $hjnr)) {
                // 如果包含则跳过
                return [];
            }
        }
        if (
            mb_strpos($hjnr, '未有诊治') === false &&
            mb_strpos($hjnr, '未予诊治') === false &&
            mb_strpos($hjnr, '未与重视') === false &&
            mb_strpos($hjnr, '未予重视') === false
        ) {
            $this->insertData[] = [
                'basis' => json_encode(['缺少诊疗过程'], 256),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => '',
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }
        return [];
    }

    /**
     * 手术记录报告时间应早于记录时间，操作时间晚于记录时间一天
     * @param $caseRule
     * @param $ruleId
     * @param $ZYH
     * @return array
     */
    public function rule1266($caseRule = [], $ruleId, $ZYH = "")
    {
        $bl01 = EMR_BL_BL01::query()->where('BLLB', 303)->where("JZHM", $ZYH)->get(["BLBH", 'BLMC', 'ZXSJ', 'CJSJ'])->toArray();
        if (empty($bl01)) {
            return [];
        }

        $basisList = [];
        foreach ($bl01 as $b) {
            $basis = [];
            $blbh = $b['BLBH'];
            $blmc = $b['BLMC'];
            $bgsj = strtotime($b['ZXSJ']); // 报告时间
            $jlsj = strtotime($b['ZXSJ']); // 记录时间
            $czsj = ''; // 操作时间
            $blxg = EMR_BL_BLXG::query()->where("BLBH", $blbh)->get(["HJNR"])->first();
            if (empty($blxg)) {
                continue;
            }

            if (preg_match('/操作时间[：:]\s*([0-9\- :]{10,16})/u', $blxg['HJNR'], $kzMatches)) {
                $czsj = $kzMatches[1];
                if (strlen($czsj) == 16) {
                    $czsj .= ':00'; // 只到分钟需补秒
                }
            }
            /* if ($bgsj > $jlsj) {
                $basis[] = '病历标题【' . $blmc . '】';
                $basis[] = '报告时间应早于记录时间';
                $basis[] = "报告时间: " . $b['ZXSJ'];
                $basis[] = "记录时间: " . $b['CJSJ'];
            } */
            $czsjInt = strtotime($czsj);
            //if ($czsjInt && $czsjInt < $jlsj + 24 * 3600) {
            if ($czsjInt && ($jlsj > $czsjInt + 24 * 3600 || $jlsj < $czsjInt)) {
                if (empty($basis)) {
                    $basis[] = '病历标题【' . $blmc . '】';
                }
                $basis[] = "操作时间: " . $czsj;
                $basis[] = "记录时间（标题时间）: " . $b['ZXSJ'];
            }
            if ($basis) {
                $basisList[] = $basis;
            }
        }

        if (!empty($basisList)) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, 256),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => '',
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }

        return [];
    }

    /**
     * @param $caseRule
     * @param $ruleId
     * @param $ZYH
     * @return array
     * 抢救时间超长
     */
    public function rule1265($caseRule = [], $ruleId, $ZYH = "")
    {
        $bl01 = EMR_BL_BL01::query()->where('BLLB', 294)->where("MBLB", 27)->where("JZHM", $ZYH)->get(["BLBH", 'BLMC'])->toArray();
        if (empty($bl01) || count($bl01) == 1) {
            return [];
        }

        $basisList = [];
        foreach ($bl01 as $b) {
            $blbh = $b['BLBH'];
            $blmc = $b['BLMC'];
            $blxg = EMR_BL_BLXG::query()->where("BLBH", $blbh)->get(["HJNR"])->first();
            if (empty($blxg)) {
                continue;
            }
            $hjnr = $blxg['HJNR'];
            $ssDate = EMR_BL_BLXG::analysisSsDateTime($hjnr);
            // 从抢救记录中提取开始时间和结束时间，并判断是否超过8小时
            // 抢救记录示例：【抢救记录，抢救开始时间：2026-01-23 08:15 抢救结束时间：2026-01-23 09:33病情变化情况。】
            $rescueStart = null;
            $rescueEnd = null;
            // 提取开始时间
            if (preg_match('/抢救开始时间[：:]\s*([0-9\- :]{10,16})/u', $hjnr, $kzMatches)) {
                $rescueStart = $kzMatches[1];
                if (strlen($rescueStart) == 16) {
                    $rescueStart .= ':00'; // 只到分钟需补秒
                }
            }
            // 提取结束时间
            if (preg_match('/抢救结束时间[：:]\s*([0-9\- :]{10,16})/u', $hjnr, $jsMatches)) {
                $rescueEnd = $jsMatches[1];
                if (strlen($rescueEnd) == 16) {
                    $rescueEnd .= ':00';
                }
            }
            if (!$rescueStart || !$rescueEnd) {
                continue;
            }
            $rescueStartTimestamp = strtotime($rescueStart);
            $rescueEndTimestamp = strtotime($rescueEnd);
            if ($rescueStartTimestamp === false || $rescueEndTimestamp === false) {
                continue;
            }
            $duration = $rescueEndTimestamp - $rescueStartTimestamp;
            // 超过8小时（28800秒）
            if ($duration > 28800) {
                $basisList[] = [
                    '病历标题【' . $blmc . '】',
                    "开始时间: " . $rescueStart,
                    "结束时间: " . $rescueEnd,
                    "时长: " . round($duration / 3600, 2) . '小时'
                ];
            }
        }
        if (!empty($basisList)) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, 256),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => '',
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }

        return [];
    }

    /**
     * @param $caseRule
     * @param $ruleId
     * @param $ZYH
     * @return array
     * 病程记录中的【业务时间】与【病历标题时间】前后顺序不一致
     */
    public function rule1053($caseRule = [], $ruleId, $ZYH = "")
    {
        $bl01 = EMR_BL_BL01::query()->where('BLLB', 294)->where("JZHM", $ZYH)->orderBy('YWSJ', 'asc')->get(["EMR_BL_BL01.BLMC"])->toArray();
        if (empty($bl01) || count($bl01) == 1) {
            return [];
        }

        // 清洗病程中所有标题中时间的时间戳
        foreach ($bl01 as $k => &$b) {
            preg_match("/(\d{4}年\d{2}月\d{2}日|\d{4}年\d{2}月\d{2}|\d{4}-\d{2}-\d{2})+\s+(\d{2}:\d{2}(:\d{2})?)/", $b['BLMC'], $timeMatches);
            if (empty($timeMatches[0])) {
                unset($bl01[$k]);
                continue;
            }

            $timeMatches[0] = str_replace('日', '', $timeMatches[0]);
            $timeStemp = strtotime(str_replace(['年', '月'], '-', $timeMatches[0]));
            $b['time_stemp'] = $timeStemp;
        }
        $basisList = [];
        $bl01 = array_values($bl01);

        foreach ($bl01 as $k => $item) {
            $basis = [];
            if (empty($k)) {
                if ($bl01[$k + 1]['time_stemp'] < $item['time_stemp']) {
                    $basis[] = $item['BLMC'];
                    $basis[] = '标题时间' . date('Y-m-d H:i:s', $item['time_stemp']);
                    $basis[] = '应在【-】与【' . $bl01[$k + 1]['BLMC'] . '】之间';
                    $basisList[] = $basis;
                }
            } elseif ($k == count($bl01) - 1) {
                // 如果是最后一条数据则判断是否在倒数第二条数据之前
                if ($bl01[$k - 1]['time_stemp'] > $item['time_stemp']) {
                    $basis[] = $item['BLMC'];
                    $basis[] = '标题时间' . date('Y-m-d H:i:s', $item['time_stemp']);
                    $basis[] = '应在【' . $bl01[$k - 1]['BLMC'] . '】与【-】之间';
                    $basisList[] = $basis;
                }
            } else {
                if ($bl01[$k - 1]['time_stemp'] > $item['time_stemp'] || $bl01[$k + 1]['time_stemp'] < $item['time_stemp']) {
                    $basis[] = $item['BLMC'];
                    $basis[] = '标题时间' . date('Y-m-d H:i:s', $item['time_stemp']);
                    $basis[] = '应在【' . $bl01[$k - 1]['BLMC'] . '】与【' . $bl01[$k + 1]['BLMC'] . '】之间';
                    $basisList[] = $basis;
                }
            }
        }
        if (!empty($basisList)) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, 256),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => '',
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }

        return [];
    }

    /**
     * @param $caseRule
     * @param $ruleId
     * @param $ZYH
     * @return array
     * 上级医师查房记录中的【上级医师】未签名
     */
    public function rule1052($caseRule = [], $ruleId, $ZYH = "")
    {
        $map9023 = RuleWordMap::getFirstById(9023);
        $bl01 = EMR_BL_BL01::query()->when(!empty($map9023), function ($query) use ($map9023) {
            if (!empty($map9023['keyword'])) {
                $query->where('MBLB', $map9023['keyword']);
            } elseif (!empty($map9023)) {
                $query->whereIn('MBLB', $map9023);
            }
        })->where("JZHM", $ZYH)->get(["BLMC", 'BLBH'])->toArray();
        if (empty($bl01)) {
            return [];
        }
        $basisList = [];
        foreach ($bl01 as $b) {
            $basis = [];
            preg_match("/(\d{4}年\d{2}月\d{2}日|\d{4}年\d{2}月\d{2}|\d{4}-\d{2}-\d{2})+\s+(\d{2}:\d{2}(:\d{2})?)\s+(.*?)(副主任|科主任|主治|主任)+/", $b['BLMC'], $timeMatches);
            if (empty($timeMatches[4])) {
                continue;
            }
            $staff = Staff::query()->where('name', $timeMatches[4])->get(["code"])->toArray();
            if (empty($staff)) {
                continue;
            }
            $basis[] = '病历标题【' . $b['BLMC'] . '】';
            //$basis[] = '上级医师【' . $timeMatches[4] . ' （' . $staff[0]['code'] . '）】';
            $yscode = '';
            if (!empty($staff)) {
                foreach ($staff as $s) {
                    $blsy = EMR_BL_BLSY::query()->where('BLBH', $b['BLBH'])->where('FG_ACTIVE', 1)->where('SYYS', $s['code'])->count();
                    if (!empty($blsy)) {
                        $yscode = $s['code'];
                        break;
                    }
                }
            }

            if (empty($yscode)) {
                $basis[] = '标题中医师姓名【' . $timeMatches[4] . '】，未签名';
                $basisList[] = $basis;
            }
        }

        if (!empty($basisList)) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, 256),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => '',
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }

        return [];
    }

    /**
     * @param array $newData
     * @param array $caseRule
     * @return array
     * 【首次病程记录 / 病例特点第2条】与【入院记录 / 现病史】 85%重复
     */
    public function rule1038($caseRule = [], $ruleId, $ZYH = "")
    {
        //首次病程记录的BLTD的第二条与XBS重复率超过85%
        $bltd = Bllb294_295::query()->where("ZYH", $ZYH)->get(["BLTD_2"])->toArray();
        $xbs = Bllb292::query()->where("ZYH", $ZYH)->get(["XBS"])->toArray();
        if (empty($bltd) || empty($xbs)) {
            return [];
        }

        $bltd = $bltd[0]['BLTD_2'];
        $xbs = $xbs[0]['XBS'];

        // 先获取bltd和xbs的字数（不包含标点符号）
        $bltdWordCount = mb_strlen(preg_replace('/[^\p{L}\p{N}]/u', '', $bltd));
        $xbsWordCount = mb_strlen(preg_replace('/[^\p{L}\p{N}]/u', '', $xbs));

        // 如果bltd字数/xbs字数小于0.85就直接返回
        if ($xbsWordCount > 0 && $bltdWordCount / $xbsWordCount < 0.85) {
            return [];
        }

        $bltdArray = preg_split('//u', $bltd, 0, PREG_SPLIT_NO_EMPTY);
        $xbsArray = preg_split('//u', $xbs, 0, PREG_SPLIT_NO_EMPTY);
        $res = array_intersect($bltdArray, $xbsArray);
        if (count($bltdArray) > 0 && count($res) / count($bltdArray) >= 0.85) {
            $this->insertData[] = [
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => '',
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }
        return [];
    }

    /**
     * @param array $caseRule
     * @param $ruleId
     * @param string $ZYH
     * @return array
     * 出院记录中"住院天数"与病案首页不一致
     */
    public function rule1039($caseRule = [], $ruleId, $ZYH = "")
    {
        $basis = [];
        $basisList = [];
        $newData = Bllb1::query()->where("ZYH", $ZYH)->first();
        if (empty($newData)) {
            return [];
        }
        $newData = $newData->toArray();
        $ZYTS = $newData["ZYTS"] ?? '';

        $patientInfo = PatientInfoV2::query()->where("ZYH", $ZYH)->first();
        if (empty($patientInfo)) {
            return [];
        }
        $patientInfo = $patientInfo->toArray();
        $AAC04 = $patientInfo['AAC04'] ?? '';

        // 如果两个字段都不为空且不一致，则记录
        if (!empty($ZYTS) && !empty($AAC04) && $ZYTS != $AAC04) {
            $basis[] = '出院记录/住院天数【' . $ZYTS . '】';
            $basis[] = '病案首页/住院天数【' . $AAC04 . '】';
        }

        if ($basis) {
            $basisList[] = $basis;
        }

        if (count($basisList)) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, 256),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => '',
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }

        return [];
    }

    /**
     * @param array $caseRule
     * @param $ruleId
     * @param string $ZYH
     * @return array
     * 术后首次病程中手术简要经过与手术记录内容重复≥90%
     */
    public function rule1040($caseRule = [], $ruleId, $ZYH = "")
    {
        // 获取出入院时间
        $enterTime = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->value('AAB01');
        $exitTime = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->value('AAC01');

        if (empty($exitTime)) {
            $exitTime = date('Y-m-d H:i:s');
        }

        // 如果没有出院时间或出院时间为默认值，使用当前时间
        if (empty($exitTime) || $exitTime == '1970-01-01 00:00:00' || $exitTime == '0000-00-00 00:00:00') {
            $exitTime = date('Y-m-d H:i:s');
        }

        // 计算出院时间-入院时间
        $enterDate = date('Y-m-d', strtotime($enterTime));
        $exitDate = date('Y-m-d', strtotime($exitTime));
        $diffDays = (strtotime($exitDate) - strtotime($enterDate)) / (24 * 3600);
        if ($diffDays <= 1) {
            return [];
        }

        // 从bllb303中查询手术记录
        $ssjl = Bllb303::query()->where('ZYH', $ZYH)->get()->toArray();
        if (empty($ssjl)) {
            return [];
        }

        // 获取术后首次病程记录的MBLB配置
        $ruleMap8074 = RuleWordMap::query()->where('id', 8074)->value('keyword');
        $ruleMap8074 = !empty($ruleMap8074) ? $ruleMap8074 : '42';
        if (strpos($ruleMap8074, ',') !== false) {
            $ruleMap8074 = explode(',', $ruleMap8074);
        } else {
            $ruleMap8074 = [$ruleMap8074];
        }

        // 获取执行时间字段名
        $ruleMap8011 = RuleWordMap::query()->where('id', '=', 8011)->value('keyword');
        $ruleMap8011 = !empty($ruleMap8011) ? $ruleMap8011 : 'ZXSJ'; // 执行时间字段名

        // 收集所有质控结果
        $allBasisGroups = [];

        // 对每条手术记录单独进行质控检查
        foreach ($ssjl as $surgery) {
            $ssmc = $surgery['SSMC'] ?? '';
            $ssjg = $surgery['SSJG'] ?? ''; // 手术简要经过
            $surgeryEndTime = $surgery['SSJSSJ'] ?? ''; // 手术结束时间

            if (empty($ssmc) || empty($ssjg)) {
                continue;
            }

            // 检查手术结束时间是否有效
            if (empty($surgeryEndTime) || $surgeryEndTime == '1970-01-01 00:00:00' || $surgeryEndTime == '0000-00-00 00:00:00') {
                continue;
            }

            // 计算手术结束后6小时的时间点
            $sixHoursAfterSurgery = date('Y-m-d H:i:s', strtotime($surgeryEndTime) + 6 * 3600);

            // 从bl01查询术后首次病程（添加时间范围限制）
            $bl01 = EMR_BL_BL01::query()
                ->where('JZHM', $ZYH)
                ->whereIn('MBLB', $ruleMap8074)
                ->where($ruleMap8011, '>=', $surgeryEndTime)
                ->where($ruleMap8011, '<=', $sixHoursAfterSurgery)
                ->orderBy($ruleMap8011, 'asc')
                ->get()->toArray();

            if (empty($bl01)) {
                $bl01 = EMR_BL_BL01::query()
                    ->where('JZHM', $ZYH)
                    ->where('BLLB', 294)
                    ->where('BLMC', 'like', '%术后首次病程%')
                    ->where($ruleMap8011, '>=', $surgeryEndTime)
                    ->where($ruleMap8011, '<=', $sixHoursAfterSurgery)
                    ->orderBy($ruleMap8011, 'asc')
                    ->get()->toArray();

                if (empty($bl01)) {
                    continue;
                }
            }

            //取第一条
            $record = $bl01[0];

            // 检查每条病程记录的HJNR字段与SSJG的重复率

            $blbh = $record['BLBH'] ?? '';
            if (empty($blbh)) {
                continue;
            }

            // 通过BLBH获取HJNR字段
            $blxgData = EMR_BL_BLXG::getById($blbh);
            $hjnr = $blxgData[0]['HJNR'] ?? '';

            if (empty($hjnr)) {
                continue;
            }

            // 计算重复率

            // 计算字符重复率
            $hjnrArray = preg_split('//u', $hjnr, 0, PREG_SPLIT_NO_EMPTY);
            $ssjgArray = preg_split('//u', $ssjg, 0, PREG_SPLIT_NO_EMPTY);
            $res = array_intersect($hjnrArray, $ssjgArray);

            if (count($hjnrArray) > 0 && count($res) / count($hjnrArray) >= 0.9) {
                $basis = [];
                $basis[] = "手术名称【" . $ssmc . "】";
                $basis[] = "术后首次病程【" . ($record['BLMC'] ?? '') . "】";
                $basis[] = "重复率【" . round((count($res) / count($hjnrArray)) * 100, 2) . "%】";
                $allBasisGroups[] = $basis;
            }
        }

        // 将所有质控结果一次性插入到insertData
        if (!empty($allBasisGroups)) {
            $this->insertData[] = [
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title'],
                'basis' => json_encode($allBasisGroups, JSON_UNESCAPED_UNICODE)
            ];
        }

        return [];
    }


    /**
     * @param array $caseRule
     * @param $ruleId
     * @param string $ZYH
     * @return array
     * 术后首次病程中手术简要经过与手术记录内容重复<30%
     */
    public function rule1041($caseRule = [], $ruleId, $ZYH = "")
    {
        // 获取出入院时间
        $enterTime = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->value('AAB01');
        $exitTime = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->value('AAC01');

        if (empty($exitTime)) {
            $exitTime = date('Y-m-d H:i:s');
        }

        // 如果没有出院时间或出院时间为默认值，使用当前时间
        if (empty($exitTime) || $exitTime == '1970-01-01 00:00:00' || $exitTime == '0000-00-00 00:00:00') {
            $exitTime = date('Y-m-d H:i:s');
        }

        // 计算出院时间-入院时间
        $enterDate = date('Y-m-d', strtotime($enterTime));
        $exitDate = date('Y-m-d', strtotime($exitTime));
        $diffDays = (strtotime($exitDate) - strtotime($enterDate)) / (24 * 3600);
        if ($diffDays <= 1) {
            return [];
        }

        // 从bllb303中查询手术记录
        $ssjl = Bllb303::query()->where('ZYH', $ZYH)->get()->toArray();
        if (empty($ssjl)) {
            return [];
        }

        // 获取术后首次病程记录的MBLB配置
        $ruleMap8074 = RuleWordMap::query()->where('id', 8074)->value('keyword');
        $ruleMap8074 = !empty($ruleMap8074) ? $ruleMap8074 : '42';
        if (strpos($ruleMap8074, ',') !== false) {
            $ruleMap8074 = explode(',', $ruleMap8074);
        } else {
            $ruleMap8074 = [$ruleMap8074];
        }

        // 获取执行时间字段名
        $ruleMap8011 = RuleWordMap::query()->where('id', '=', 8011)->value('keyword');
        $ruleMap8011 = !empty($ruleMap8011) ? $ruleMap8011 : 'ZXSJ'; // 执行时间字段名

        // 收集所有质控结果
        $allBasisGroups = [];

        // 对每条手术记录单独进行质控检查
        foreach ($ssjl as $surgery) {
            $ssmc = $surgery['SSMC'] ?? '';
            $ssjg = $surgery['SSJG'] ?? ''; // 手术简要经过
            $surgeryEndTime = $surgery['SSJSSJ'] ?? ''; // 手术结束时间

            if (empty($ssmc) || empty($ssjg)) {
                continue;
            }

            // 检查手术结束时间是否有效
            if (empty($surgeryEndTime) || $surgeryEndTime == '1970-01-01 00:00:00' || $surgeryEndTime == '0000-00-00 00:00:00') {
                continue;
            }

            // 计算手术结束后6小时的时间点
            $sixHoursAfterSurgery = date('Y-m-d H:i:s', strtotime($surgeryEndTime) + 6 * 3600);

            // 从bl01查询术后首次病程（添加时间范围限制）
            $bl01 = EMR_BL_BL01::query()
                ->where('JZHM', $ZYH)
                ->whereIn('MBLB', $ruleMap8074)
                ->where($ruleMap8011, '>=', $surgeryEndTime)
                ->where($ruleMap8011, '<=', $sixHoursAfterSurgery)
                ->orderBy($ruleMap8011, 'asc')
                ->get()->toArray();

            if (empty($bl01)) {
                $bl01 = EMR_BL_BL01::query()
                    ->where('JZHM', $ZYH)
                    ->where('BLLB', 294)
                    ->where('BLMC', 'like', '%术后首次病程%')
                    ->where($ruleMap8011, '>=', $surgeryEndTime)
                    ->where($ruleMap8011, '<=', $sixHoursAfterSurgery)
                    ->orderBy($ruleMap8011, 'asc')
                    ->get()->toArray();

                if (empty($bl01)) {
                    continue;
                }
            }

            //取第一条
            $record = $bl01[0];

            // 检查每条病程记录的HJNR字段与SSJG的重复率

            $blbh = $record['BLBH'] ?? '';
            if (empty($blbh)) {
                continue;
            }

            // 通过BLBH获取HJNR字段
            $blxgData = EMR_BL_BLXG::getById($blbh);
            $hjnr = $blxgData[0]['HJNR'] ?? '';

            if (empty($hjnr)) {
                continue;
            }

            // 如果hjnr中包含"详见手术记录"，则跳过此条记录
            if (strpos($hjnr, '详见手术记录') !== false) {
                $basis = [];
                $basis[] = "手术名称【" . $ssmc . "】";
                $basis[] = "术后首次病程【" . ($record['BLMC'] ?? '') . "】";
                $allBasisGroups[] = $basis;
                continue;
            }

            // 计算重复率


            // 计算字符重复率
            $hjnrArray = preg_split('//u', $hjnr, 0, PREG_SPLIT_NO_EMPTY);
            $ssjgArray = preg_split('//u', $ssjg, 0, PREG_SPLIT_NO_EMPTY);
            $res = array_intersect($hjnrArray, $ssjgArray);

            if (count($hjnrArray) > 0 && count($res) / count($hjnrArray) <= 0.3) {
                $basis = [];
                $basis[] = "手术名称【" . $ssmc . "】";
                $basis[] = "术后首次病程【" . ($record['BLMC'] ?? '') . "】";
                $basis[] = "重复率【" . round((count($res) / count($hjnrArray)) * 100, 2) . "%】";
                $allBasisGroups[] = $basis;
            }
        }

        // 将所有质控结果一次性插入到insertData
        if (!empty($allBasisGroups)) {
            $this->insertData[] = [
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title'],
                'basis' => json_encode($allBasisGroups, JSON_UNESCAPED_UNICODE)
            ];
        }

        return [];
    }

    /**
     * @param array $newData
     * @param array $caseRule
     * @return array
     * 既往史中药物过敏史与病案首页药物过敏史不一致
     */
    public function rule1037($caseRule = [], $ruleId, $ZYH = "")
    {
        $bllb292 = Bllb292::query()->where("ZYH", $ZYH)->get(["JWS"])->toArray();
        if (empty($newData)) {
            return [];
        }

        $rule8088 = RuleWordMap::query()->where("id", 8088)->value("keyword");
        $rule8088 = !empty($rule8088) ? $rule8088 : '否认过敏史,过敏史补充描述,否认药物等过敏史,否认其他等过敏史,否认食物过敏史,否认食物、药物过敏史';

        if (strpos($rule8088, ',') !== false) {
            $rule8088 = explode(',', $rule8088);
        } else {
            $rule8088 = [$rule8088];
        }

        $jws = $bllb292[0]['JWS'];

        //删除8088
        foreach ($rule8088 as $item) {
            $jws = str_replace($item, '', $jws);
        }

        if (strpos($jws, "过敏") === false) {
            return [];
        }

        $ptmi = PatientInfoV2::query()->where("ZYH", $ZYH)->get()->toArray();
        if (empty($ptmi) || $ptmi[0]["AEB02C"] != 2) {
            $this->insertData[] = [
                'basis' => json_encode([[""]], 256),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => '',
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }

        return [];
    }

    /**
     * @param array $newData
     * @param array $caseRule
     * @return array
     * 抢救记录未记录参加抢救的医护人员
     */
    public function rule1035($caseRule = [], $ruleId, $ZYH = "")
    {
        $basisList = [];
        $newData = EMR_BL_BL01::query()->where("JZHM", $ZYH)->where("BLLB", 294)->where("MBLB", 27)->get(["BLBH", "BLMC"])->toArray();
        if (empty($newData)) {
            return [];
        }

        foreach ($newData as $b) {
            $isHas = EMR_BL_BLSY::query()
                ->join("staff", "EMR_BL_BLSY.SYYS", "=", "staff.code")
                ->where("EMR_BL_BLSY.BLBH", $b["BLBH"])
                ->where("EMR_BL_BLSY.FG_ACTIVE", 1)
                //->whereIn("staff.ygjb_text", ["医师", "护士", "护师", "主任", "主治", "主任护师", "主管护师", "副主任", "副主任护师"])
                ->count();
            if (empty($isHas)) {
                $basis[] = $b["BLMC"];
                $basisList[] = $basis;
            }
        }
        if ($basisList) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, 256),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => '',
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }

        return [];
    }

    /**
     * @param array $newData
     * @param array $caseRule
     * @return array
     * 抢救记录中描述的关键时间节点未精确到分
     */
    public function rule1034($caseRule = [], $ruleId, $ZYH = "")
    {
        $basisList = [];

        $newData = EMR_BL_BL01::query()
            ->leftJoin("EMR_BL_BLXG", "EMR_BL_BL01.BLBH", "=", "EMR_BL_BLXG.BLBH")
            ->where("EMR_BL_BL01.JZHM", $ZYH)
            ->where("EMR_BL_BL01.MBLB", 27)
            ->get(['EMR_BL_BLXG.HJNR', 'EMR_BL_BL01.BLMC'])->toArray();
        if (empty($newData)) {
            return [];
        }

        foreach ($newData as $item) {
            $basis = [];
            $hjnr = $item["HJNR"];
            if (empty($hjnr)) {
                continue;
            }
            $match_time_str = '';
            if ($hjnr) {
                // 匹配各种抢救结束的时间格式

                if (preg_match('/于(\d{1,2})\s*时\s*(\d{1,2})\s*分抢救成功/', $hjnr, $matches)) {
                    $match_time_str = $matches[1] . ':' . $matches[2];
                } elseif (preg_match('/于.*?(\d{1,2})时(\d{1,2})分抢救成功/', $hjnr, $matches)) {
                    // 确保小时和分钟是两位数格式
                    $hour = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
                    $minute = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
                    $match_time_str = "$hour:$minute";
                    //echo $match_time_str; // 输出 22:05
                } elseif (preg_match('/于(\d{1,2})\s*时\s*(\d{1,2})\s*分病情/', $hjnr, $matches)) {
                    $match_time_str = $matches[1] . ':' . $matches[2];
                } elseif (preg_match('/于(\d{1,2})\s*时\s*(\d{1,2})\s*分/', $hjnr, $matches)) {
                    $match_time_str = $matches[1] . ':' . $matches[2];
                } elseif (preg_match('/于(\d{1,2})\s*时\s*(\d{1,2})\s*分血压/', $hjnr, $matches)) {
                    $match_time_str = $matches[1] . ':' . $matches[2];
                } elseif (preg_match('/于(\d{1,2})\s*时\s*(\d{1,2})\s*分测血压/', $hjnr, $matches)) {
                    $match_time_str = $matches[1] . ':' . $matches[2];
                } elseif (preg_match('/于(\d{1,2})\s*时\s*(\d{1,2})\s*分使用/', $hjnr, $matches)) {
                    $match_time_str = $matches[1] . ':' . $matches[2];
                } elseif (preg_match('/于(\d{1,2})\s*时\s*(\d{1,2})\s*分氧/', $hjnr, $matches)) {
                    $match_time_str = $matches[1] . ':' . $matches[2];
                } elseif (preg_match('/于(\d{1,2})\s*时\s*(\d{1,2})\s*分仍无自主呼吸心跳/', $hjnr, $matches)) {
                    $match_time_str = $matches[1] . ':' . $matches[2];
                } elseif (preg_match('/于(\d{1,2})\s*时\s*(\d{1,2})\s*分心电图示直线/', $hjnr, $matches)) {
                    $match_time_str = $matches[1] . ':' . $matches[2];
                } elseif (preg_match('/于(\d{1,2})\s*时\s*(\d{1,2})\s*分临床死亡/', $hjnr, $matches)) {
                    $match_time_str = $matches[1] . ':' . $matches[2];
                } elseif (preg_match('/于(\d{1,2})\s*时\s*(\d{1,2})\s*分宣布/', $hjnr, $matches)) {
                    $match_time_str = $matches[1] . ':' . $matches[2];
                } elseif (preg_match('/于(\d{1,2})\s*时\s*(\d{1,2})\s*分转入/', $hjnr, $matches)) {
                    $match_time_str = $matches[1] . ':' . $matches[2];
                } elseif (preg_match('/于(\d{1,2})\s*时\s*(\d{1,2})\s*分死亡/', $hjnr, $matches)) {
                    $match_time_str = $matches[1] . ':' . $matches[2];
                } elseif (preg_match('/于(\d{1,2})\s*时\s*(\d{1,2})\s*分出现意识不清/', $hjnr, $matches)) {
                    $match_time_str = $matches[1] . ':' . $matches[2];
                } elseif (preg_match('/于(\d{4}-\d{2}-\d{2} (\d{2}:\d{2}))宣布/', $hjnr, $matches)) {
                    //提取后面的时间到分
                    $match_time_str = $matches[2];
                } elseif (preg_match('/于(\d{1,2})\s*时\s*(\d{1,2})\s*分抢救成功/', $hjnr, $matches)) {
                    $match_time_str = $matches[1] . ':' . $matches[2];
                } elseif (preg_match('/于(\d{1,2})\s*时\s*(\d{1,2})\s*分抢救成功/', $hjnr, $matches)) {
                    $match_time_str = $matches[1] . ':' . $matches[2];
                } elseif (preg_match('/于(\d{1,2})\s*时\s*(\d{1,2})\s*分心电图示无心电/', $hjnr, $matches)) {
                    $match_time_str = $matches[1] . ':' . $matches[2];
                } elseif (preg_match('/于(\d{1,2})\s*时\s*(\d{1,2})\s*分出现意识不清/', $hjnr, $matches)) {
                    $match_time_str = $matches[1] . ':' . $matches[2];
                } elseif (preg_match('/于\((\d{1,2})\s*时\s*(\d{1,2})\s*分\)心电图示无心电/', $hjnr, $matches)) { // 修正括号匹配
                    $match_time_str = $matches[1] . ':' . $matches[2];
                } elseif (preg_match('/于(\d{1,2})\s*时\s*(\d{1,2})\s*分呼吸及血压/', $hjnr, $matches)) {
                    $match_time_str = $matches[1] . ':' . $matches[2];
                } elseif (preg_match('/于(\d{1,2})\s*时\s*(\d{1,2})\s*分呼吸及血压/', $hjnr, $matches)) { // 修正括号匹配
                    $match_time_str = $matches[1] . ':' . $matches[2];
                } elseif (preg_match('/抢救结束时间：\s*(\d{4}-\d{1,2}-\d{1,2}\s+(\d{1,2}:\d{1,2}))/', $hjnr, $matches)) {
                    // 新增匹配 抢救结束时间：2025-10-23 00:23 取00:23 (全角冒号)
                    $match_time_str = $matches[2];
                } elseif (preg_match('/抢救结束时间:\s*(\d{4}-\d{1,2}-\d{1,2}\s+(\d{1,2}:\d{1,2}))/', $hjnr, $matches)) {
                    // 新增匹配 抢救结束时间:2025-10-23 00:23 取00:23 (半角冒号)
                    $match_time_str = $matches[2];
                }
            }
            //preg_match($preg, $HJNR, $dateTime);
            $dateTime = $match_time_str;

            if (empty($dateTime)) {
                return [];
            }

            //判断是否到分
            if ($dateTime && !preg_match('/\d{1,2}:\d{1,2}/', $dateTime)) {
                $basis[] = $item["BLMC"];
                $basis[] = '抢救时间：【' . $dateTime . '】未精确到分';
                $basisList[] = $basis;
            }
        }
        if ($basisList) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, 256),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => '',
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }
        return [];
    }

    /**
     * @param array $newData
     * @param array $caseRule
     * @return array
     * 手术记录中术者签名与手术记录-术者不一致
     */
    public function rule1033($caseRule = [], $ruleId, $ZYH = "")
    {
        $basisList = [];
        $newData = Bllb303::query()->where("ZYH", $ZYH)->get()->toArray();
        if (empty($newData)) {
            return [];
        }


        foreach ($newData as $item) {
            $blsy = EMR_BL_BLSY::query()->where("BLBH", $item["BLBH"])->where("FG_ACTIVE", 1)->get()->toArray();
            //如果一个签名都没有，提示无签名
            if (empty($blsy)) {
                continue;
            }
            $basis = [];
            $ssz = $item["SSZ"];
            //去除空格
            //$ssz = str_replace(' ', '', $ssz);
            if (empty($ssz)) {
                continue;
            }
            $isHas = 0;
            foreach ($blsy as $blsyItem) {
                $name = Staff::query()->where("code", $blsyItem["SYYS"])->value("name");
                if (empty($name)) {
                    continue;
                }
                if (strpos($name, $ssz) !== false || strpos($ssz, $name) !== false) {
                    $isHas = 1;
                    break;
                }
            }
            if (!$isHas) {
                //如果一个签名都没有，提示无签名

                $basis[] = $item["BLMC"];
                $basis[] = '术者：' . $ssz;


                $basisList[] = $basis;
            }
        }
        if ($basisList) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, 256),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => '',
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }
        return [];
    }

    /**
     * @param array $newData
     * @param array $caseRule
     * @return array
     * 会诊记录中缺少会诊目的
     */
    public function rule1032($caseRule = [], $ruleId, $ZYH = "")
    {
        $hzsq = YS_ZY_HZSQ::query()->where("JZHM", $ZYH)->get(['HZMD'])->toArray();
        if (empty($hzsq)) {
            return [];
        }

        $basisList = [];
        foreach ($hzsq as $item) {
            $basis = [];
            if (empty($item['HZMD'])) {
                continue;
            }
            preg_match_all("/^([\d\.]+)/", $item['HZMD'], $matches1);
            preg_match_all("/^([a-zA-Z]+)/", $item['HZMD'], $matches2);
            if (empty($matches1[0]) && empty($matches2[0]) && strlen($item['HZMD']) > 3) {
                continue;
            }
            if ($matches1[0] == $item['HZMD'] || $matches2[0] == $item['HZMD'] || strlen($item['HZMD']) <= 3) {
                $basis[] = $item["HZMD"] ?: "【空】";
                $basisList[] = $basis;
            }
        }
        if ($basisList) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, 256),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => '',
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }
        return [];
    }

    /**
     * @param array $newData
     * @param array $caseRule
     * @return array
     * 会诊记录中缺少会诊意见
     */
    public function rule1036($caseRule = [], $ruleId, $ZYH = "")
    {
        $hzsq = YS_ZY_HZSQ::query()->where("JZHM", $ZYH)->get(['SQXH'])->toArray();
        if (empty($hzsq)) {
            return [];
        }
        $SQXH = array_column($hzsq, 'SQXH');
        $hzyj = YS_ZY_HZYJ::query()->whereIn("SQXH", $SQXH)->get(['HZYJ', 'SQXH'])->toArray();

        $basisList = [];
        foreach ($hzyj as $item) {
            $basis = [];
            //去除空格
            $item['HZYJ'] = str_replace(' ', '', $item['HZYJ']);
            if (empty($item['HZYJ'])) {
                continue;
            }
            preg_match_all("/^([\d\.]+)/", $item['HZYJ'], $matches1);
            preg_match_all("/^([a-zA-Z]+)/", $item['HZYJ'], $matches2);
            if (empty($matches1[0]) && empty($matches2[0]) && strlen($item['HZYJ']) > 3) {
                continue;
            }
            if ($matches1[0] == $item['HZYJ'] || $matches2[0] == $item['HZYJ'] || strlen($item['HZYJ']) <= 3) {
                //申请时间
                $SQSJ = YS_ZY_HZSQ::query()->where("SQXH", $item['SQXH'])->value('SQSJ');
                $basis[] = "申请时间：【" . $SQSJ . "】";
                $basis[] = "会诊意见：【" . $item["HZYJ"] . "】";
                $basisList[] = $basis;
            }
        }
        if ($basisList) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, 256),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => '',
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }
        return [];
    }

    /**
     * @param array $newData
     * @param array $caseRule
     * @return array
     * 手术记录与手麻系统的手术时间不一致
     */
    public function rule1031($caseRule = [], $ruleId, $ZYH = "")
    {
        $basisList = [];
        $newData = Bllb303::query()->where("ZYH", $ZYH)->get()->toArray();
        if (empty($newData)) {
            return [];
        }

        $patientInfo = PatientInfo::query()->where("MED_REC_ID", $ZYH)->first();
        if (empty($patientInfo)) {
            return [];
        }
        $patientInfo = $patientInfo->toArray();

        // 获取手麻信息
        $ssap = SM_SSAP::query()->where("ZYH", $ZYH)->get(["JSRQ"])->toArray();
        if (empty($ssap)) {
            return [];
        }

        foreach ($newData as $item) {
            $basis = [];
            $flag = 0;
            if (empty($item["SSJSSJ"]) || strpos($item["SSJSSJ"], '1970-01-01') !== false) {
                continue;
            }
            $basis[] = $item["BLMC"];
            $basis[] = '（手术记录）手术结束时间：' . $item["SSJSSJ"];
            $isday = false;
            $day = date("Y-m-d", strtotime($item["SSJSSJ"]));
            $ssDay = date("Y-m-d H:i", strtotime($item["SSJSSJ"]));
            if (empty($ssDay) || $ssDay == '1970-01-01 00:00') {
                $basis[] = $item["BLMC"];
                $basis[] = '手术结束时间：【无】';
                $basisList[] = $basis;
                continue;
            }
            foreach ($ssap as $s) {
                if (empty($s["JSRQ"]) || strpos($s["JSRQ"], '1970-01-01') !== false) {
                    continue;
                }
                $ssapDay = date("Y-m-d", strtotime($s["JSRQ"]));
                if ($ssapDay == $day) {
                    $isday = true;
                    $SSRQ = date("Y-m-d H:i", strtotime($s["JSRQ"]));
                    if ($ssDay == $SSRQ) {
                        $flag = 1;
                        break;
                    } else {
                        $basis[] = '（手麻系统）手术结束时间：' . $s["JSRQ"];
                    }
                    //break;
                } else {
                    continue;
                }
            }
            if (!$flag && $isday) {
                $basisList[] = $basis;
            }
        }
        if ($basisList) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, 256),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => '',
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }
        return [];
    }

    /**
     * @param array $newData
     * @param array $caseRule
     * @return array
     * 入院记录【主诉】与【现病史】中，症状不一致
     */
    public function rule1030($caseRule = [], $ruleId, $ZYH = "")
    {
        $newData = Bllb292::query()->where("ZYH", $ZYH)->first();
        if (empty($newData)) {
            return [];
        }
        $newData = $newData->toArray();
        $symptom = Symptom::query()->get()->toArray();


        $zhusu = $newData["ZHS"];
        $zhusuSymptom = [];
        foreach ($symptom as $v) {
            if (strpos($zhusu, $v['content']) !== false) {
                $zhusuSymptom[] = $v['content'];
            }
        }

        $xbs = $newData["XBS"];
        $xbsSymptom = [];
        foreach ($symptom as $v) {
            if (strpos($xbs, $v['content']) !== false) {
                $xbsSymptom[] = $v['content'];
            } else {
                $alias = $v['alias'] ?? "";
                if (!empty($alias)) {
                    if (strpos($alias, ',')) {
                        $alias = explode(',', $alias);
                    } else {
                        $alias = [$alias];
                    }
                    foreach ($alias as $a) {
                        if (strpos($xbs, $a) !== false) {
                            $xbsSymptom[] = $v['content'];
                            break;
                        }
                    }
                }
            }
        }


        $basis = [];
        foreach ($zhusuSymptom as $item) {
            if (!in_array($item, $xbsSymptom)) {
                $basis[] = "主诉症状【{$item}】，现病史中未记录";
            }
        }
        //        $intersectSymptom = array_intersect($zhusuSymptom, $xbsSymptom);
        if ($basis) {
            $basis["BLBH"] = $newData['BLBH'];
            $this->insertData[] = [
                'basis' => json_encode([$basis], 256),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => '',
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }
        return [];
    }

    /**
     * @param array $caseRule
     * @param $ruleId
     * @param string $ZYH
     * @return array
     * 入院记录中入院日期与病案首页中的不一致
     */
    public function rule1029($caseRule = [], $ruleId, $ZYH = "")
    {
        $basis = [];
        $basisList = [];
        $newData = Bllb292::query()->where("ZYH", $ZYH)->first();
        if (empty($newData)) {
            return [];
        }
        $newData = $newData->toArray();
        $RYSJ = date("Y-m-d H:i", strtotime($newData["RYSJ"]));

        $patientInfo = PatientInfo::query()->where("MED_REC_ID", $ZYH)->first();
        if (empty($patientInfo)) {
            return [];
        }
        $patientInfo = $patientInfo->toArray();
        if (empty($patientInfo["AAB01"])) {
            return [];
        }
        $AAB01 = $patientInfo['AAB01'] ?? '';
        // 如果$AAB01不为空
        if (!empty($AAB01)) {
            // 若长度大于19，截取前19位
            if (strlen($AAB01) > 19) {
                $AAB01 = substr($AAB01, 0, 19);
            }
            // 格式化时间为Y-m-d H:i:s格式
            $AAB01 = date('Y-m-d H:i:s', strtotime($AAB01));
        }
        $AAB01 = date("Y-m-d", strtotime($AAB01));
        $RYSJ = date("Y-m-d", strtotime($RYSJ));

        if ($AAB01 == "1970-01-01" || empty($AAB01) || $RYSJ == "1970-01-01" || empty($RYSJ)) {
            return true;
        }


        if ($RYSJ != $AAB01) {
            $basis[] = '入院记录/入院日期【' . $RYSJ . '】';
            $basis[] = '病案首页/入院日期【' . $AAB01 . '】';
        }
        if ($basis) {
            $basisList[] = $basis;
        }

        if (count($basisList)) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, 256),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => '',
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }

        return [];
    }

    /**
     * @param array $caseRule
     * @param $ruleId
     * @param string $ZYH
     * @return array
     * 出院记录中出院日期与病案首页中的不一致
     */
    public function rule1028($caseRule = [], $ruleId, $ZYH = "")
    {
        $basis = [];
        $basisList = [];
        $newData = Bllb1::query()->where("ZYH", $ZYH)->first();
        if (empty($newData)) {
            return [];
        }
        $newData = $newData->toArray();
        if (empty($newData["CYRQ"])) {
            return [];
        }
        $CYRQ = date("Y-m-d H:i", strtotime($newData["CYRQ"]));

        $patientInfo = PatientInfoV2::query()->where("ZYH", $ZYH)->first();
        if (empty($patientInfo)) {
            return [];
        }
        $patientInfo = $patientInfo->toArray();

        $AAC01 = $patientInfo['AAC01'] ?? '';
        if (empty($AAC01)) {
            return [];
        }
        // 如果$AAC01不为空
        if (!empty($AAC01)) {
            // 若长度大于19，截取前19位
            if (strlen($AAC01) > 19) {
                $AAC01 = substr($AAC01, 0, 19);
            }
            // 格式化时间为Y-m-d H:i:s格式
            $AAC01 = date('Y-m-d H:i:s', strtotime($AAC01));
        }
        $AAC01 = date("Y-m-d H:i", strtotime($AAC01));

        if (env('APP_NAME') == 'ningxia') {
            $AAC01 = date("Y-m-d", strtotime($AAC01));
            $CYRQ = date("Y-m-d", strtotime($CYRQ));
        }

        if (empty($AAC01)) {
            return true;
        }
        if ($CYRQ != $AAC01) {
            $basis[] = '出院记录/出院日期【' . $CYRQ . '】';
            $basis[] = '病案首页/出院日期【' . $AAC01 . '】';
        }
        if ($basis) {
            $basisList[] = $basis;
        }

        if (count($basisList)) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, 256),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => '',
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }

        return [];
    }

    /**
     * @param array $caseRule
     * @param $ruleId
     * @param string $ZYH
     * @return array
     * 出院记录中入院日期与病案首页中的不一致
     */
    public function rule1027($caseRule = [], $ruleId, $ZYH = "")
    {
        $basis = [];
        $basisList = [];
        $newData = Bllb1::query()->where("ZYH", $ZYH)->first();
        if (empty($newData)) {
            return [];
        }
        $newData = $newData->toArray();
        $RYRQ = date("Y-m-d H:i", strtotime($newData["RYRQ"]));

        $patientInfo = PatientInfo::query()->where("MED_REC_ID", $ZYH)->first();
        if (empty($patientInfo)) {
            return [];
        }
        $patientInfo = $patientInfo->toArray();
        $AAB01 = $patientInfo['AAB01'] ?? '';
        if (empty($AAB01)) {
            return [];
        }
        // 如果$AAB01不为空
        if (!empty($AAB01)) {
            // 若长度大于19，截取前19位
            if (strlen($AAB01) > 19) {
                $AAB01 = substr($AAB01, 0, 19);
            }
            // 格式化时间为Y-m-d H:i:s格式
            $AAB01 = date('Y-m-d H:i', strtotime($AAB01));
        }


        $AAB01 = date("Y-m-d", strtotime($AAB01));
        if ($AAB01 == "1970-01-01" || empty($AAB01) || $RYRQ == "1970-01-01" || empty($RYRQ)) {
            return true;
        }
        $RYRQ = date("Y-m-d", strtotime($RYRQ));

        if ($RYRQ != $AAB01) {
            $basis[] = '出院记录/入院日期【' . $RYRQ . '】';
            $basis[] = '病案首页/入院日期【' . $AAB01 . '】';
        }
        if ($basis) {
            $basisList[] = $basis;
        }

        if (count($basisList)) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, 256),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => '',
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }

        return [];
    }

    /**
     * @param array $caseRule
     * @param $ruleId
     * @param string $ZYH
     * @return array
     * 年龄<2岁，入院记录|体格检查 头围在20-55cm之间
     */
    public function rule219($caseRule = [], $ruleId, $ZYH = "")
    {
        $basis = [];
        $basisList = [];
        $newData = Bllb292::query()->where("ZYH", $ZYH)->first();
        if (empty($newData)) {
            return [];
        }
        $newData = $newData->toArray();
        $age = trim($newData["NL"]);
        $tgjc = $newData["TGJC"];
        // 如果年龄是天为单位，那么转换成年
        if (strpos($age, '天') !== false || strpos($age, '分钟') !== false) {
            $age = 1;
        }
        if (intval($age) > 2) {
            return [];
        }
        preg_match_all("/头围:([\d\.]+)CM/", $tgjc, $res);
        if (empty($res[1])) {
            return [];
        }
        $touwei = !empty($res) && !empty($res[1]) ? $res[1][0] : 0;
        if ($touwei < 20 || $touwei > 55) {
            $basis[] = '年龄【≤2岁】';
            $basis[] = '头围:' . $touwei . 'CM（与头围标准不符）';
            $basis["BLBH"] = $newData["BLBH"];
        }
        if ($basis) {
            $basisList[] = $basis;
        }

        if (count($basisList)) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, 256),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => '',
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }

        return [];
    }

    /**
     * @param array $newData
     * @param string $blxgId
     * @return array
     * 8、规则：月经   性别是女的有，男的没有
     */
    public function rule80($caseRule = [], $ruleId, $ZYH = "")
    {
        $errorNotice = [];
        $newData = Bllb292::query()->where("ZYH", $ZYH)->first();
        if (empty($newData)) {
            return [];
        }
        $newData = $newData->toArray();
        $xbData = $newData["XB"];
        $age = trim($newData["NL"]);
        // 如果年龄是天为单位，那么转换成年
        if (strpos($age, '天') !== false || strpos($age, '分钟') !== false) {
            $age = 1;
        }
        if (intval($age) > 60 || $xbData == '男') {
            return [];
        }
        $YJCXSJ = $newData["TGJC_YJCXSJ"] ?? '';
        if (empty($YJCXSJ)) {
            return [];
        }

        $map9019 = RuleWordMap::getFirstById(9019);
        $map9020 = RuleWordMap::getFirstById(9020);
        if (strpos($YJCXSJ, '-')) {
            $YJCXSJArr = explode('-', $YJCXSJ);
            if ($YJCXSJArr[0] < $map9019 || $YJCXSJArr[0] > $map9020 || $YJCXSJArr[1] < $map9019 || $YJCXSJArr[1] < $map9020) {
                $this->insertData[] = [
                    'basis' => json_encode([["经期持续时间{$YJCXSJ}"]], 256),
                    'JZHM' => $ZYH,
                    'rule_id' => $ruleId,
                    'code' => '',
                    'error_field' => $caseRule[$ruleId]['title']
                ];
            }
        } else {
            if ($YJCXSJ < $map9019 || $YJCXSJ > $map9020) {
                $this->insertData[] = [
                    'basis' => json_encode([["经期持续时间{$YJCXSJ}"]], 256),
                    'JZHM' => $ZYH,
                    'rule_id' => $ruleId,
                    'code' => '',
                    'error_field' => $caseRule[$ruleId]['title']
                ];
            }
        }
        return [];
    }


    /**
     * @param array $caseRule
     * @param $ruleId
     * @param string $ZYH
     * @return array
     * 出院记录【入院情况】与入院记录【体格检查】中的T（体温）值不一致
     */
    public function rule1024($caseRule = [], $ruleId, $ZYH = "")
    {
        $basis = [];
        $basisList = [];

        $bl01 = Bllb1::query()->where("ZYH", $ZYH)->first();
        if (empty($bl01)) {
            return [];
        }
        $bl01 = $bl01->toArray();

        $text = $bl01["RYQK"];
        $matches1 = regexTgjc($text, 1);
        if (empty($matches1)) {
            return [];
        }

        $bllb292 = Bllb292::query()->where("ZYH", $ZYH)->get()->toArray();
        if (empty($bllb292)) {
            return [];
        }

        $matches2 = regexTgjc($bllb292[0]["TGJC"], 1);
        if (empty($matches2) || empty($matches1[1])) {
            return [];
        } elseif ($matches1[1] != $matches2[1]) {
            $basis[] = '入院记录/体格检查/T【' . $matches2[1] . '】';
            $basis[] = '出院记录/入院情况/T【' . $matches1[1] . '】';
        }

        if ($basis) {
            $basisList[] = $basis;
        }

        if (count($basisList)) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, 256),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => '',
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }

        return [];
    }
    /**
     * @param array $caseRule
     * @param $ruleId
     * @param string $ZYH
     * @return array
     * 出院记录【入院情况】与入院记录【体格检查】中的BP（血压）值不一致
     */
    public function rule1057($caseRule = [], $ruleId, $ZYH = "")
    {
        $basis = [];
        $basisList = [];

        $bl01 = Bllb1::query()->where("ZYH", $ZYH)->first();
        if (empty($bl01)) {
            return [];
        }
        $bl01 = $bl01->toArray();

        $text = $bl01["RYQK"];
        $matches1 = regexTgjc($text, 4);
        if (empty($matches1)) {
            return [];
        }

        $bllb292 = Bllb292::query()->where("ZYH", $ZYH)->get()->toArray();
        if (empty($bllb292)) {
            return [];
        }

        $matches2 = regexTgjc($bllb292[0]["TGJC"], 4);
        if (empty($matches2) || empty($matches1[1])) {
            return [];
        } elseif ($matches1[1] != $matches2[1]) {
            $basis[] = '入院记录/体格检查/BP【' . $matches2[1] . '】';
            $basis[] = '出院记录/入院情况/BP【' . $matches1[1] . '】';
        }

        if ($basis) {
            $basisList[] = $basis;
        }

        if (count($basisList)) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, 256),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => '',
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }

        return [];
    }
    /**
     * @param array $caseRule
     * @param $ruleId
     * @param string $ZYH
     * @return array
     * 出院记录【入院情况】与入院记录【体格检查】中的R（呼吸）值不一致
     */
    public function rule1058($caseRule = [], $ruleId, $ZYH = "")
    {
        $basis = [];
        $basisList = [];

        $bl01 = Bllb1::query()->where("ZYH", $ZYH)->first();
        if (empty($bl01)) {
            return [];
        }
        $bl01 = $bl01->toArray();

        $text = $bl01["RYQK"];
        $matches1 = regexTgjc($text, 3);
        if (empty($matches1)) {
            return [];
        }

        $bllb292 = Bllb292::query()->where("ZYH", $ZYH)->get()->toArray();
        if (empty($bllb292)) {
            return [];
        }

        $matches2 = regexTgjc($bllb292[0]["TGJC"], 3);
        if (empty($matches2) || empty($matches1[1])) {
            return [];
        } elseif ($matches1[1] != $matches2[1]) {
            $basis[] = '入院记录/体格检查/R【' . $matches2[1] . '】';
            $basis[] = '出院记录/入院情况/R【' . $matches1[1] . '】';
        }

        if ($basis) {
            $basisList[] = $basis;
        }

        if (count($basisList)) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, 256),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => '',
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }

        return [];
    }
    /**
     * @param array $caseRule
     * @param $ruleId
     * @param string $ZYH
     * @return array
     * 出院记录【入院情况】与入院记录【体格检查】中的P（脉搏）值不一致
     */
    public function rule1059($caseRule = [], $ruleId, $ZYH = "")
    {
        $basis = [];
        $basisList = [];

        $bl01 = Bllb1::query()->where("ZYH", $ZYH)->first();
        if (empty($bl01)) {
            return [];
        }
        $bl01 = $bl01->toArray();

        $text = $bl01["RYQK"];
        $matches1 = regexTgjc($text, 2);
        if (empty($matches1)) {
            return [];
        }

        $bllb292 = Bllb292::query()->where("ZYH", $ZYH)->get()->toArray();
        if (empty($bllb292)) {
            return [];
        }

        $matches2 = regexTgjc($bllb292[0]["TGJC"], 2);
        if (empty($matches2) || empty($matches1[1])) {
            return [];
        } elseif ($matches1[1] != $matches2[1]) {
            $basis[] = '入院记录/体格检查/P【' . $matches2[1] . '】';
            $basis[] = '出院记录/入院情况/P【' . $matches1[1] . '】';
        }

        if ($basis) {
            $basisList[] = $basis;
        }

        if (count($basisList)) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, 256),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => '',
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }

        return [];
    }

    /**
     * @param array $caseRule
     * @param $ruleId
     * @param string $ZYH
     * @return array
     * 首次病程【病例特点】与入院记录【体格检查】中的P（脉搏）值不一致
     */
    public function rule1056($caseRule = [], $ruleId, $ZYH = "")
    {
        $basis = [];
        $basisList = [];

        $bl01 = Bllb294_295::query()->where("ZYH", $ZYH)->first();
        if (empty($bl01)) {
            return [];
        }
        $bl01 = $bl01->toArray();

        $text = $bl01["BLTD"];
        $matches1 = regexTgjc($text, 2);
        if (empty($matches1)) {
            return [];
        }

        $bllb292 = Bllb292::query()->where("ZYH", $ZYH)->get()->toArray();
        if (empty($bllb292)) {
            return [];
        }

        $matches2 = regexTgjc($bllb292[0]["TGJC"], 2);
        if (empty($matches2) || empty($matches1[1])) {
            return [];
        } elseif ($matches1[1] != $matches2[1]) {
            $basis[] = '入院记录/体格检查/P【' . $matches2[1] . '】';
            $basis[] = '首次病程记录/病例特点/P【' . $matches1[1] . '】';
        }

        if ($basis) {
            $basisList[] = $basis;
        }

        if (count($basisList)) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, 256),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => '',
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }
    }

    /**
     * @param array $caseRule
     * @param $ruleId
     * @param string $ZYH
     * @return array
     * 首次病程【病例特点】与入院记录【体格检查】中的R（呼吸）值不一致
     */
    public function rule1055($caseRule = [], $ruleId, $ZYH = "")
    {
        $basis = [];
        $basisList = [];

        $bl01 = Bllb294_295::query()->where("ZYH", $ZYH)->first();
        if (empty($bl01)) {
            return [];
        }
        $bl01 = $bl01->toArray();

        $text = $bl01["BLTD"];
        $matches1 = regexTgjc($text, 3);
        if (empty($matches1)) {
            return [];
        }

        $bllb292 = Bllb292::query()->where("ZYH", $ZYH)->get()->toArray();
        if (empty($bllb292)) {
            return [];
        }

        $matches2 = regexTgjc($bllb292[0]["TGJC"], 3);
        if (empty($matches2) || empty($matches1[1])) {
            return [];
        } elseif ($matches1[1] != $matches2[1]) {
            $basis[] = '入院记录/体格检查/R【' . $matches2[1] . '】';
            $basis[] = '首次病程记录/病例特点/R【' . $matches1[1] . '】';
        }

        if ($basis) {
            $basisList[] = $basis;
        }

        if (count($basisList)) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, 256),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => '',
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }
    }


    /**
     * @param array $caseRule
     * @param $ruleId
     * @param string $ZYH
     * @return array
     * 首次病程【病例特点】与入院记录【体格检查】中的BP（血压）值不一致
     */
    public function rule1054($caseRule = [], $ruleId, $ZYH = "")
    {
        $basis = [];
        $basisList = [];

        $bl01 = Bllb294_295::query()->where("ZYH", $ZYH)->first();
        if (empty($bl01)) {
            return [];
        }
        $bl01 = $bl01->toArray();

        $text = $bl01["BLTD"];
        $matches1 = regexTgjc($text, 4);
        if (empty($matches1)) {
            return [];
        }

        $bllb292 = Bllb292::query()->where("ZYH", $ZYH)->get()->toArray();
        if (empty($bllb292)) {
            return [];
        }

        $matches2 = regexTgjc($bllb292[0]["TGJC"], 4);
        if (empty($matches2) || empty($matches1[1])) {
            return [];
        } elseif ($matches1[1] != $matches2[1]) {
            $basis[] = '入院记录/体格检查/BP【' . $matches2[1] . '】';
            $basis[] = '首次病程记录/病例特点/BP【' . $matches1[1] . '】';
        }

        if ($basis) {
            $basisList[] = $basis;
        }

        if (count($basisList)) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, 256),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => '',
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }
    }

    /**
     * @param array $caseRule
     * @param $ruleId
     * @param string $ZYH
     * @return array
     * 首次病程【病例特点】与入院记录【体格检查】中的T（体温）值不一致
     */
    public function rule1023($caseRule = [], $ruleId, $ZYH = "")
    {
        $basis = [];
        $basisList = [];

        $bl01 = Bllb294_295::query()->where("ZYH", $ZYH)->first();
        if (empty($bl01)) {
            return [];
        }
        $bl01 = $bl01->toArray();

        $text = $bl01["BLTD"];
        $matches1 = regexTgjc($text, 1);
        if (empty($matches1)) {
            return [];
        }

        $bllb292 = Bllb292::query()->where("ZYH", $ZYH)->get()->toArray();
        if (empty($bllb292)) {
            return [];
        }

        $matches2 = regexTgjc($bllb292[0]["TGJC"], 1);
        if (empty($matches2) || empty($matches1[1])) {
            return [];
        } elseif ($matches1[1] != $matches2[1]) {
            $basis[] = '入院记录/体格检查/T【' . $matches2[1] . '】';
            $basis[] = '首次病程记录/病例特点/T【' . $matches1[1] . '】';
        }

        if ($basis) {
            $basisList[] = $basis;
        }

        if (count($basisList)) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, 256),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => '',
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }
    }

    /*
     * 术前讨论时间早于手术同意书签署（医师签字时间）且 术前讨论时间早于手术医嘱开嘱时间
     */
    public function rule261($caseRule, $ruleId, $ZYH)
    {
        $basisList = [];
        $map9011 = RuleWordMap::getFirstById(9011);

        $yzb = \App\Model\Yzb::query()->where("ZYH", $ZYH)->get(["YZMC", "KZSJ"])->toArray();

        $pacsStartTime = 0;
        foreach ($yzb as $y) {
            $basis = [];
            $flag = true;
            $containsKeyword = false;
            // 判断是否包含关键字
            foreach ($map9011 as $keyword) {
                if (strpos($y["YZMC"], $keyword) !== false) {
                    $containsKeyword = true;
                    break;
                }
            }
            if (!$containsKeyword) {
                continue;
            }
            // 检查开嘱时间是否在报告单时间之前
            $basis[] = '医嘱名称【' . $y['YZMC'] . '】';
            $basis[] = '开嘱时间【' . $y['KZSJ'] . '】';

            // 查询手术同意书
            $resData = EMR_BL_BL01::query()
                ->where('JZHM', $ZYH)
                ->where('MBLB', 30308)
                ->where('ZXSJ', '<', $y['KZSJ'])
                ->where('BLZT', '<>', 9)
                ->select(['BLBH', 'CJSJ', 'BLMC', 'BLLB', 'first_blsy_time'])
                ->get()->toArray();
            // 病程记录无
            if (empty($resData)) {
                $basis[] = '手术同意书【无】';
            } else {
                $basis[] = '手术同意书【' . $resData[0]['BLMC'] . '】';
            }

            // 查询术前小结
            $resData1 = EMR_BL_BL01::query()
                ->where('JZHM', $ZYH)
                ->where('MBLB', 82)
                ->where('operation_time', '<', date('Y-m-d H:i:s', strtotime($y['KZSJ'])))
                ->where('BLZT', '<>', 9)
                ->select(['BLBH', 'CJSJ', 'BLMC', 'BLLB', 'first_blsy_time'])
                ->get()->toArray();
            // 病程记录无
            if (empty($resData1)) {
                $basis[] = '术前小结【无】';
                $flag = false;
            } else {
                $basis[] = '手术同意书【' . $resData1[0]['BLMC'] . '】';

                // 检查首次签名时间是否超时
                if (strtotime($resData1[0]["first_blsy_time"]) > strtotime($y['KZSJ'])) {
                    $basis[] = '【未在手术医嘱之前】';
                    $basis["BLBH"] = $resData1[0]["BLBH"];
                    $flag = false;
                }
                // 检查首次签名时间是否超时
                if (!empty($resData) && strtotime($resData1[0]["first_blsy_time"]) > strtotime($resData[0]["first_blsy_time"])) {
                    $basis[] = '【未在手术同意书之前】';
                    $basis["BLBH"] = $resData1[0]["BLBH"];
                    $flag = false;
                }
            }
            if (!$flag) {
                $basisList[] = $basis;
            }
        }
        if (count($basisList)) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, 256),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'jcbgd',
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }

        return [];
    }

    /*
     * 【术前小结及术前讨论结论记录】在【手术医嘱】之前
     */
    public function rule260($caseRule, $ruleId, $ZYH)
    {
        $basisList = [];
        $map9011 = RuleWordMap::getFirstById(9011);

        $yzb = \App\Model\Yzb::query()->where("ZYH", $ZYH)->get(["YZMC", "KZSJ"])->toArray();

        $pacsStartTime = 0;
        foreach ($yzb as $y) {
            $basis = [];
            $containsKeyword = false;
            // 判断是否包含关键字
            foreach ($map9011 as $keyword) {
                if (strpos($y["YZMC"], $keyword) !== false) {
                    $containsKeyword = true;
                    break;
                }
            }
            if (!$containsKeyword) {
                continue;
            }
            // 检查开嘱时间是否在报告单时间之前
            $basis[] = '医嘱名称【' . $y['YZMC'] . '】';
            $basis[] = '开嘱时间【' . $y['KZSJ'] . '】';

            // 查询手术同意书
            $resData = EMR_BL_BL01::query()
                ->where('JZHM', $ZYH)
                ->where('MBLB', 30308)
                ->where('ZXSJ', '<', $y['KZSJ'])
                ->where('BLZT', '<>', 9)
                ->select(['BLBH', 'CJSJ', 'BLMC', 'BLLB', 'first_blsy_time'])
                ->get()->toArray();
            // 病程记录无
            if (empty($resData)) {
                $basis[] = '手术同意书【无】';
                $basisList[] = $basis;
            } else {
                $basis[] = '手术同意书【' . $resData[0]['BLMC'] . '】';

                // 检查首次签名时间是否超时
                if (strtotime($resData[0]['first_blsy_time']) > strtotime($y['KZSJ'])) {
                    $basis[] = '首次签名时间【' . $resData[0]["first_blsy_time"] . ' （超时）】';
                    $basis["BLBH"] = $resData[0]["BLBH"];
                    $basisList[] = $basis;
                } else {
                    continue;
                }
            }
        }
        if (count($basisList)) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, 256),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'jcbgd',
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }

        return [];
    }

    /**
     * 检查报告单有ct（OCT除外）结果，24小时内要写病程记录
     * 或 24小时出入院记录  ,且   含 关键字“CT”（OCT除外）
     */
    public function rule102($caseRule, $ruleId, $ZYH, $isYj = 0)
    {
        $basisList = [];
        //替换ct、oct 字典
        //$ctFirst = RuleWordMap::getFirstById(26);
        //$octFirst = RuleWordMap::getFirstById(27);
        $ctFirst = RuleWordMap::query()->where('id', 26)->value('keyword');
        //是否包含逗号
        if (strpos($ctFirst, ',') !== false) {
            $ctFirst = explode(',', $ctFirst);
        } else {
            $ctFirst = [$ctFirst];
        }
        $octFirst = RuleWordMap::query()->where('id', 27)->value('keyword');
        if (strpos($octFirst, ',') !== false) {
            $octFirst = explode(',', $octFirst);
        } else {
            $octFirst = [$octFirst];
        }
        $map9009 = RuleWordMap::query()->where('id', 8068)->value('keyword');
        if (strpos($map9009, ',') !== false) {
            $map9009 = explode(',', $map9009);
        } else {
            $map9009 = [$map9009];
        }
        $map9010 = RuleWordMap::getFirstById(9010);

        $map9015 = RuleWordMap::getFirstById(9015);

        $pacs = \App\Model\PACS::query()->where("ZYH", $ZYH)->get(["JCMC", "BGSJ"])->toArray();
        $yzb = \App\Model\Yzb::query()->where("ZYH", $ZYH)->orderBy('KZSJ', 'desc')->get(["YZMC", "KZSJ"])->toArray(); //按开嘱时间倒序
        $isnum = 1;
        //$pacsStartTime = 0;
        foreach ($pacs as $p) {
            $basis = [];
            /* if (strpos($p["JCMC"], $ctFirst['keyword']) === false) {
                continue;
            } */
            $isct = false;
            $isoct = false;
            foreach ($ctFirst as $item) {
                if (strpos($p["JCMC"], $item) === false) {
                    $isct = true;
                    break;
                }
            }
            if (empty($p['BGSJ'])) {
                continue;
            }
            /* if (strpos($p["JCMC"], $octFirst['keyword']) !== false) {
                continue;
            } */
            foreach ($octFirst as $item) {
                if (strpos($p["JCMC"], $item) !== false) {
                    $isoct = true;
                    break;
                }
            }

            if ($isct || $isoct) {
                continue;
            }

            $basis[] = '检查名称【' . $p['JCMC'] . '】';
            $basis[] = '报告时间【' . $p['BGSJ'] . '】';
            //获取当前时间
            $currentDate = Carbon::now()->toDateTimeString();
            $oneDayAfterReport = date('Y-m-d H:i:s', strtotime($p['BGSJ']) + 72 * 3600);
            //如果当前时间小于报告时间+72小时就跳过
            if (strtotime($currentDate) < strtotime($oneDayAfterReport) && $isYj == 0) {
                continue;
            }
            // 检查开嘱时间
            $hasYzb = true;
            //如果是第一条isnum = 1，重新查询医嘱，kzsj正序
            if ($isnum == 1) {
                $yzb1 = \App\Model\Yzb::query()->where("ZYH", $ZYH)->orderBy('KZSJ', 'asc')->get(["YZMC", "KZSJ"])->toArray();
                foreach ($yzb1 as $y) {
                    // 不包含CT或者包含的是OCT则跳过
                    /* if (strpos($y["YZMC"], $ctFirst['keyword']) === false || strpos($y["YZMC"], $octFirst['keyword']) !== false) {
                        continue;
                    } */
                    $isct = false;
                    $isoct = false;
                    foreach ($ctFirst as $item) {
                        if (strpos($y["YZMC"], $item) === false) {
                            $isct = true;
                            break;
                        }
                    }

                    foreach ($octFirst as $item) {
                        if (strpos($y["YZMC"], $item) !== false) {
                            $isoct = true;
                            break;
                        }
                    }

                    if ($isct || $isoct) {
                        continue;
                    }
                    // 检查开嘱时间是否在报告单时间之前48小时到报告单时间
                    //yzmc去除关键字中文括号左括号到右括号及中间的内容
                    $yzmc = preg_replace('/\（.*?\）/', '', $y['YZMC']);
                    $yzmc = preg_replace('/\(.*?\)/', '', $yzmc);
                    if (strtotime($y['KZSJ']) > strtotime($p['BGSJ']) - 48 * 3600 && strtotime($y['KZSJ']) < strtotime($p['BGSJ'])) {
                        //对比yzmc和jcmc,看jcmc中是否包含yzmc,如果包含则认为有开嘱
                        if (strpos($p['JCMC'], $yzmc) !== false) {
                            $basis[] = '医嘱名称【' . $y['YZMC'] . '】';
                            $basis[] = '开嘱时间【' . $y['KZSJ'] . '】';
                            $hasYzb = true;
                            break;
                        }
                    }
                }
                $isnum = 2;
            } else {
                foreach ($yzb as $y) {
                    // 不包含CT或者包含的是OCT则跳过
                    /* if (strpos($y["YZMC"], $ctFirst['keyword']) === false || strpos($y["YZMC"], $octFirst['keyword']) !== false) {
                        continue;
                    } */
                    $isct = false;
                    $isoct = false;
                    foreach ($ctFirst as $item) {
                        if (strpos($y["YZMC"], $item) === false) {
                            $isct = true;
                            break;
                        }
                    }

                    foreach ($octFirst as $item) {
                        if (strpos($y["YZMC"], $item) !== false) {
                            $isoct = true;
                            break;
                        }
                    }

                    if ($isct || $isoct) {
                        continue;
                    }
                    // 检查开嘱时间是否在报告单时间之前48小时到报告单时间
                    //yzmc去除关键字中文括号左括号到右括号及中间的内容
                    $yzmc = preg_replace('/\（.*?\）/', '', $y['YZMC']);
                    $yzmc = preg_replace('/\(.*?\)/', '', $yzmc);
                    if (strtotime($y['KZSJ']) > strtotime($p['BGSJ']) - 48 * 3600 && strtotime($y['KZSJ']) < strtotime($p['BGSJ'])) {
                        //对比yzmc和jcmc,看jcmc中是否包含yzmc,如果包含则认为有开嘱
                        if (strpos($p['JCMC'], $yzmc) !== false) {
                            $basis[] = '医嘱名称【' . $y['YZMC'] . '】';
                            $basis[] = '开嘱时间【' . $y['KZSJ'] . '】';
                            $hasYzb = true;
                            break;
                        }
                    }
                }
            }
            // 记录本次的报告时间，作为下次报告的开始时间
            //$pacsStartTime = strtotime($p['BGSJ']);

            // 有开嘱数据
            if (!$hasYzb) {
                $basis[] = '医嘱名称【无】';
                $basis[] = '开嘱时间【无】';
                $basisList[] = $basis;
            } else {

                // 检查病程信息
                $bgsj = date("Y-m-d H:i:s", strtotime($p['BGSJ']) + intval($map9010['keyword']) * 3600);
                // 将字符串数组转为整数数组
                $map9009 = array_map('intval', $map9009);
                // 验证转换后的值（确保没有非数字字符串）
                $map9009 = array_filter($map9009, function ($val) {
                    return is_int($val) && $val >= 0;
                });
                // 病程记录
                $resData = EMR_BL_BL01::query()
                    ->leftJoin('EMR_BL_BLXG', 'EMR_BL_BL01.BLBH', '=', 'EMR_BL_BLXG.BLBH')
                    ->where('JZHM', $ZYH)
                    ->whereIn('BLLB', $map9009)
                    ->where('ZXSJ', '>', $p['BGSJ'])
                    ->where('ZXSJ', '<', $bgsj)
                    ->select(['EMR_BL_BL01.BLBH', 'EMR_BL_BL01.CJSJ', 'EMR_BL_BL01.BLMC', 'EMR_BL_BL01.BLLB', 'EMR_BL_BL01.first_blsy_time', 'EMR_BL_BLXG.HJNR'])
                    ->get()->toArray();

                // 过滤包含CT关键字的记录，排除OCT
                $filteredData = [];
                foreach ($resData as $record) {
                    $containsCT = false;
                    $containsOCT = false;
                    foreach ($ctFirst as $item) {
                        if (strpos($record['HJNR'], $item) !== false) {
                            $containsCT = true;
                            break;
                        }
                    }
                    if (!$containsCT) {
                        continue;
                    }
                    foreach ($octFirst as $item) {
                        if (strpos($record['HJNR'], $item) !== false) {
                            $containsOCT = true;
                            break;
                        }
                    }
                    if (!$containsOCT) {
                        $filteredData[] = $record;
                    }
                }
                $resData = $filteredData;

                // 病程记录无
                if (empty($resData)) {
                    $basis[] = '病程记录【无】';
                    $basisList[] = $basis;
                    $exitTimeEnd = $bgsj;
                    //先转换时间戳
                    $diffTime = intval(strtotime($exitTimeEnd) - time());
                    if ($diffTime > 0 && $diffTime < 2 * 3600) {
                        $res = remainderTime($diffTime);
                        $content = '请在' . $res . '内完成CT相关病程记录';
                        $msgYj = ['报告时间【' . $p['BGSJ'] . '】', 'CT相关病程记录【无】'];
                        $this->caseService->sendMsg($ZYH, $content, $ruleId, $msgYj);
                    }
                } else {
                    $isZk = false;
                    /* $basis[] = '病程记录【' . $resData[0][0]['BLMC'] . '】';

                    $firstBlsyTime = strtotime($resData[0][0]["first_blsy_time"]);
                    // 检查首次签名时间是否超时
                    if ($firstBlsyTime < strtotime($p['BGSJ']) || $firstBlsyTime > strtotime($bgsj)) {
                        $basis[] = '首次签名时间【' . $resData[0][0]["first_blsy_time"] . ' （超时）】';
                        $basis["BLBH"] =  $resData[0][0]["BLBH"];
                        $basisList[] = $basis;
                    } else {
                        continue;
                    } */

                    foreach ($resData as $key => $item) {
                        $blmc = $item["BLMC"];
                        $hjnr = $item["HJNR"];
                        if (strpos($blmc, '会诊') !== false) {
                            //把痕迹内容中  “会诊意见给予”之后的内容全部删除
                            if (strpos($hjnr, '会诊意见给予') !== false) {
                                $hjnr = substr($hjnr, 0, strpos($hjnr, '会诊意见给予'));
                            }
                            //是否包含ctFirst，不包含oct
                            $isct = false;
                            foreach ($ctFirst as $item) {
                                if (strpos($hjnr, $item) !== false) {
                                    //不包含oct
                                    foreach ($octFirst as $item) {
                                        if (strpos($hjnr, $item) === false) {
                                            $isct = true;
                                            break;
                                        }
                                    }
                                }
                            }
                            if (!$isct) {
                                //移除这条
                                unset($resData[$key]);
                            }
                        }
                    }
                    //移除空元素
                    $resData = array_values($resData);

                    if (empty($resData)) {
                        $basis[] = '病程记录【无】';
                        $basisList[] = $basis;
                        $exitTimeEnd = $bgsj;
                        //先转换时间戳
                        $diffTime = intval(strtotime($exitTimeEnd) - time());
                        if ($diffTime > 0 && $diffTime < 2 * 3600) {
                            $res = remainderTime($diffTime);
                            $content = '请在' . $res . '内完成CT相关病程记录';
                            $msgYj = ['报告时间【' . $p['BGSJ'] . '】', 'CT相关病程记录【无】'];
                            $this->caseService->sendMsg($ZYH, $content, $ruleId, $msgYj);
                        }
                    } else {
                        $BLBH = '';
                        foreach ($resData as $item) {
                            if (empty($item["first_blsy_time"])) {
                                continue;
                            }
                            $firstBlsyTime = strtotime($item["first_blsy_time"]);
                            // 检查首次签名时间是否超时
                            if ($firstBlsyTime < strtotime($p['BGSJ']) || $firstBlsyTime > strtotime($bgsj)) {
                                $basis[] = '病程记录【' . $item["BLMC"] . '】' . '首次签名时间【' . $item["first_blsy_time"] . ' （超时）】';
                                $BLBH = $item["BLBH"];
                            } else {
                                $isZk = true;
                                break;
                            }
                        }
                        if (!$isZk && !empty($BLBH)) {
                            $basis["BLBH"] = $BLBH;
                            $basisList[] = $basis;
                        }
                    }
                }
            }
        }
        if (count($basisList)) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, 256),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'jcbgd',
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }

        return [];
    }

    /*
     * 检查报告单有mr结果，24小时内要写病程记录，或 24小时内出入院记录：关键字 "MR” 或 “磁共振”
     */
    public function rule103($caseRule, $ruleId, $ZYH, $isYj = 0)
    {
        $basisList = [];
        //替换ct、oct 字典
        $map25 = RuleWordMap::getFirstById(25);
        $map9009 = RuleWordMap::query()->where('id', 8068)->value('keyword');
        if (strpos($map9009, ',') !== false) {
            $map9009 = explode(',', $map9009);
        } else {
            $map9009 = [$map9009];
        }
        $map9010 = RuleWordMap::getFirstById(9010);
        $map9015 = RuleWordMap::getFirstById(9015);

        $pacs = \App\Model\PACS::query()->where("ZYH", $ZYH)->get(["JCMC", "BGSJ"])->toArray();
        $yzb = \App\Model\Yzb::query()->where("ZYH", $ZYH)->orderBy('KZSJ', 'desc')->get(["YZMC", "KZSJ"])->toArray(); //按开嘱时间倒序
        $isnum = 1;
        $pacsStartTime = 0;
        foreach ($pacs as $p) {
            $basis = [];
            $containsKeyword = false;
            // 判断是否包含关键字
            foreach ($map25 as $keyword) {
                if (strpos($p["JCMC"], $keyword) !== false) {
                    $containsKeyword = true;
                    break;
                }
            }
            if (!$containsKeyword) {
                continue;
            }
            // 报告时间为空则不质控
            if (empty($p['BGSJ'])) {
                continue;
            }

            $basis[] = '检查名称【' . $p['JCMC'] . '】';
            $basis[] = '报告时间【' . $p['BGSJ'] . '】';
            //获取当前时间
            $currentDate = Carbon::now()->toDateTimeString();
            $oneDayAfterReport = date('Y-m-d H:i:s', strtotime($p['BGSJ']) + 72 * 3600);
            //如果当前时间小于报告时间+72小时就跳过
            if (strtotime($currentDate) < strtotime($oneDayAfterReport) && $isYj == 0) {
                continue;
            }
            // 检查开嘱时间
            $hasYzb = true;
            //如果是第一条isnum = 1，重新查询医嘱，kzsj正序
            /* if ($isnum == 1) {
                $yzb1 = \App\Model\Yzb::query()->where("ZYH", $ZYH)->orderBy('KZSJ', 'asc')->get(["YZMC", "KZSJ"])->toArray();
                foreach ($yzb1 as $y) {


                    // 检查开嘱时间是否在报告单时间之前48小时到报告单时间
                    //yzmc去除关键字中文括号左括号到右括号及中间的内容
                    $yzmc = preg_replace('/\（.*?\）/', '', $y['YZMC']);
                    $yzmc = preg_replace('/\(.*?\)/', '', $yzmc);
                    if (strtotime($y['KZSJ']) > strtotime($p['BGSJ']) - 48 * 3600 && strtotime($y['KZSJ']) < strtotime($p['BGSJ'])) {
                        //对比yzmc和jcmc,看jcmc中是否包含yzmc,如果包含则认为有开嘱
                        if (strpos($p['JCMC'], $yzmc) !== false) {
                            $basis[] = '医嘱名称【' . $y['YZMC'] . '】';
                            $basis[] = '开嘱时间【' . $y['KZSJ'] . '】';
                            $hasYzb = true;
                            break;
                        }
                    }
                }
                $isnum = 2;
            } else {
                foreach ($yzb as $y) {

                    // 检查开嘱时间是否在报告单时间之前48小时到报告单时间
                    //yzmc去除关键字中文括号左括号到右括号及中间的内容
                    $yzmc = preg_replace('/\（.*?\）/', '', $y['YZMC']);
                    $yzmc = preg_replace('/\(.*?\)/', '', $yzmc);
                    if (strtotime($y['KZSJ']) > strtotime($p['BGSJ']) - 48 * 3600 && strtotime($y['KZSJ']) < strtotime($p['BGSJ'])) {
                        //对比yzmc和jcmc,看jcmc中是否包含yzmc,如果包含则认为有开嘱
                        if (strpos($p['JCMC'], $yzmc) !== false) {
                            $basis[] = '医嘱名称【' . $y['YZMC'] . '】';
                            $basis[] = '开嘱时间【' . $y['KZSJ'] . '】';
                            $hasYzb = true;
                            break;
                        }
                    }
                }
            } */
            /* if (!$hasYzb) {
                foreach ($yzb as $y) {
                    $containsKeyword = false;
                    // 判断是否包含关键字
                    foreach ($map25 as $keyword) {
                        if (strpos($y["YZMC"], $keyword) !== false) {
                            $containsKeyword = true;
                            break;
                        }
                    }
                    if (!$containsKeyword) {
                        continue;
                    }
                    // 检查开嘱时间是否在报告单时间之前
                    if (strtotime($y['KZSJ']) > $pacsStartTime && strtotime($y['KZSJ']) < strtotime($p['BGSJ'])) {
                        $basis[] = '医嘱名称【' . $y['YZMC'] . '】';
                        $basis[] = '开嘱时间【' . $y['KZSJ'] . '】';
                        $hasYzb = true;
                    }
                }
            }

            $pacsStartTime = strtotime($p['BGSJ']); */
            // 有开嘱数据
            if (!$hasYzb) {
                $basis[] = '医嘱名称【无】';
                $basis[] = '开嘱时间【无】';
                $basisList[] = $basis;
            } else {

                // 检查病程信息
                $bgsj = date("Y-m-d H:i:s", strtotime($p['BGSJ']) + intval($map9010['keyword']) * 3600);
                // 病程记录
                // 从MySQL查询病程记录
                $resData = EMR_BL_BL01::query()
                    ->leftJoin('EMR_BL_BLXG', 'EMR_BL_BL01.BLBH', '=', 'EMR_BL_BLXG.BLBH')
                    ->where('JZHM', $ZYH)
                    ->whereIn('BLLB', $map9009)
                    ->where('ZXSJ', '>=', $p['BGSJ'])
                    ->where('ZXSJ', '<=', $bgsj)
                    ->select(['EMR_BL_BL01.BLBH', 'EMR_BL_BL01.CJSJ', 'EMR_BL_BL01.BLMC', 'EMR_BL_BL01.BLLB', 'EMR_BL_BL01.first_blsy_time', 'EMR_BL_BLXG.HJNR'])
                    ->get()->toArray();

                // 过滤HJNR包含任一关键字的记录
                $filteredData = [];
                foreach ($resData as $record) {
                    foreach ($map25 as $item) {
                        if (strpos($record['HJNR'], $item) !== false) {
                            $filteredData[] = $record;
                            break;
                        }
                    }
                }
                $resData = $filteredData;

                // 病程记录无
                //$basis = null;
                if (empty($resData)) {
                    $basis[] = '病程记录【无】';
                    $basisList[] = $basis;
                    $exitTimeEnd = $bgsj;
                    //先转换时间戳
                    $diffTime = intval(strtotime($exitTimeEnd) - time());
                    if ($diffTime > 0 && $diffTime < 2 * 3600) {
                        $res = remainderTime($diffTime);
                        $content = '请在' . $res . '内完成' . $p['JCMC'] . '相关病程记录';
                        $msgYj = ['报告时间【' . $p['BGSJ'] . '】', $p['JCMC'] . '相关病程记录【无】'];
                        $this->caseService->sendMsg($ZYH, $content, $ruleId, $msgYj);
                    }
                } else {
                    $isZk = false;
                    /* $basis[] = '病程记录【' . $resData[0][0]['BLMC'] . '】';

                    $firstBlsyTime = strtotime($resData[0][0]["first_blsy_time"]);
                    // 检查首次签名时间是否超时
                    if ($firstBlsyTime < strtotime($p['BGSJ']) || $firstBlsyTime > strtotime($bgsj)) {
                        $basis[] = '首次签名时间【' . $resData[0][0]["first_blsy_time"] . ' （超时）】';
                        $basis["BLBH"] =  $resData[0][0]["BLBH"];
                        $basisList[] = $basis;
                    } else {
                        continue;
                    } */


                    foreach ($resData as $key => $item) {
                        $blmc = $item["BLMC"];
                        $hjnr = $item["HJNR"];

                        if (strpos($blmc, '会诊') !== false) {
                            //把痕迹内容中  “会诊意见给予”之后的内容全部删除
                            if (strpos($hjnr, '会诊意见给予') !== false) {
                                $hjnr = substr($hjnr, 0, strpos($hjnr, '会诊意见给予'));
                            }
                            //是否包含rule25中的任意一个
                            $ismr = false;
                            foreach ($map25 as $item) {
                                if (strpos($hjnr, $item) !== false) {
                                    $ismr = true;
                                    break;
                                }
                            }

                            if (!$ismr) {
                                //移除这条
                                unset($resData[$key]);
                            }
                        }
                    }
                    //移除空元素
                    $resData = array_values($resData);
                    //是否为空
                    if (empty($resData)) {
                        $basis[] = '病程记录【无】';
                        $basisList[] = $basis;
                        $exitTimeEnd = $bgsj;
                        //先转换时间戳
                        $diffTime = intval(strtotime($exitTimeEnd) - time());
                        if ($diffTime > 0 && $diffTime < 2 * 3600) {
                            $res = remainderTime($diffTime);
                            $content = '请在' . $res . '内完成' . $p['JCMC'] . '相关病程记录';
                            $msgYj = ['报告时间【' . $p['BGSJ'] . '】', $p['JCMC'] . '相关病程记录【无】'];
                            $this->caseService->sendMsg($ZYH, $content, $ruleId, $msgYj);
                        }
                    } else {
                        foreach ($resData as $item) {
                            if (empty($item["first_blsy_time"])) {
                                continue;
                            }
                            $firstBlsyTime = strtotime($item["first_blsy_time"]);
                            // 检查首次签名时间是否超时
                            if ($firstBlsyTime < strtotime($p['BGSJ']) || $firstBlsyTime > strtotime($bgsj)) {
                                $basis[] = '病程记录【' . $item["BLMC"] . '】' . '首次签名时间【' . $item["first_blsy_time"] . ' （超时）】';

                                $basis["BLBH"] = $item["BLBH"];
                            } else {
                                $basis[] = '病程记录【' . $item["BLMC"] . '】';
                                $basis[] = '首次签名时间【' . $item["first_blsy_time"] . ' （符合）】';
                                $isZk = true;
                            }
                        }
                        if (!$isZk) {
                            $basisList[] = $basis;
                        }
                    }
                }
            }
        }
        if (count($basisList)) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, 256),
                'JZHM' => $ZYH,
                'rule_id' => 103,
                'code' => 'jcbgd',
                'error_field' => $caseRule[103]['title']
            ];
        }

        return [];
    }

    /**
     * 抗菌药开嘱时间前24小时至开嘱后72小时，病程中搜索抗菌药（抗菌药物别名 或 id2053 ）且不包含（id2055）
     * 先查ZXSJ是否符合，再看首次签名时间是否符合
     */
    public function rule109($caseRule, $ruleId, $ZYH, $isYj = 0): array
    {

        // 2. 获取患者科室
        $brks = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->value('BRKS');
        if (empty($brks)) {
            return []; // 没有科室信息
        }

        // 4. 判断是否为产科且需要质控
        //获取产科代码id8025
        $id8025 = RuleWordMap::query()->where('id', '=', 8025)->value('keyword');
        //是否包含逗号
        if (strpos($id8025, ',') !== false) {
            $id8025 = explode(',', $id8025);
        } else {
            $id8025 = [$id8025];
        }
        //是否包含产科代码
        if (in_array($brks, $id8025)) {
            return []; // 是产科，不进行质控
        }
        $errorNotice = [];
        $basisList = [];

        $resData = Yzb::query()
            ->select(['YZMC', 'JLDW', 'SYPC', 'YCJL', 'KZSJ', 'kjyw_name', 'YZBXH', 'YZQX'])
            ->where('ZYH', '=', (string)$ZYH)
            ->where('YZZT', '!=', 3)
            ->where('PSBZ', '!=', 1)
            ->where('is_has_kjyw', '=', 1)
            ->get()->toArray();

        $map2053 = RuleWordMap::getFirstById(2053);
        $map2055 = RuleWordMap::getFirstById(2055);
        $map9009 = RuleWordMap::query()->where('id', 8068)->value('keyword');
        if (strpos($map9009, ',') !== false) {
            $map9009 = explode(',', $map9009);
        } else {
            $map9009 = [$map9009];
        }
        $map9015 = RuleWordMap::getFirstById(9015);

        if (!empty($resData)) {

            foreach ($resData as $v) {
                //抗菌药名称
                $kjywName = $v['kjyw_name'];
                if (strpos($v['YZMC'], '皮试') !== false) {
                    continue;
                }
                $startTime = date("Y-m-d H:i:s", strtotime($v['KZSJ']) - 24 * 3600);
                $endTime = date("Y-m-d H:i:s", strtotime($v['KZSJ']) + 24 * 3600);

                //获取当前时间
                $currentDate = Carbon::now()->toDateTimeString();
                //$oneDayAfterReport = date('Y-m-d H:i:s', strtotime($v['KZSJ']) + 72 * 3600);
                //如果当前时间小于开嘱时间+72小时就跳过
                if (strtotime($currentDate) < strtotime($endTime) && $isYj == 0) {
                    continue;
                }

                //$shouldList = [['match_phrase_prefix' => ['HJNR' => $v['kjyw_name']]]];
                $shouldList = [];
                //从数据表获取抗菌药名称，和2053中的满足一项即可表medicinal_info  且 type=1
                //$kjywName = MedicinalInfo::query()->where('type', 1)->get()->toArray();
                /* foreach ($kjywName as $item) {
                    $shouldList[] = ['match_phrase_prefix' => ['HJNR' => $item['name']]];
                } */
                $kjyalias =  MedicinalInfo::query()->where('name', '=', $kjywName)->get()->toArray();
                if (!empty($kjyalias)) {
                    foreach ($kjyalias as $alias) {
                        //是否存在
                        if (!empty($alias['alias'])) {
                            if (strpos($alias['alias'], ',') !== false) {
                                $alias['alias'] = explode(',', $alias['alias']);
                            } else {
                                $alias['alias'] = [$alias['alias']];
                            }
                            foreach ($alias['alias'] as $item) {
                                $shouldList[] = ['match_phrase_prefix' => ['HJNR' => $item]];
                            }
                        }
                    }
                }
                foreach ($map2053 as $item) {
                    $shouldList[] = ['match_phrase_prefix' => ['HJNR' => $item]];
                }
                $shouldListNot = [];
                //foreach ($map2055 as $item) {
                //    $shouldListNot[] = ['match_phrase_prefix' => ['HJNR' => $item]];
                //}
                // 从MySQL查询24小时内的病程记录
                $resData1 = EMR_BL_BL01::query()
                    ->leftJoin('EMR_BL_BLXG', 'EMR_BL_BL01.BLBH', '=', 'EMR_BL_BLXG.BLBH')
                    ->where('JZHM', $ZYH)
                    ->whereIn('BLLB', $map9009)
                    ->where('ZXSJ', '>=', $startTime)
                    ->where('ZXSJ', '<=', $endTime)
                    ->select(['EMR_BL_BL01.BLBH', 'EMR_BL_BL01.CJSJ', 'EMR_BL_BL01.BLMC', 'EMR_BL_BL01.BLLB', 'EMR_BL_BL01.first_blsy_time', 'EMR_BL_BLXG.HJNR'])
                    ->get()->toArray();

                // 过滤HJNR包含抗菌药名称的记录
                $filteredData = [];
                foreach ($resData1 as $record) {
                    if (strpos($record['HJNR'], $kjywName) !== false) {
                        $filteredData[] = $record;
                    }
                }
                $resData1 = $filteredData;

                $yzqx = $v['YZQX'];
                if ($yzqx == 1) {
                    $yzqx = '长期';
                } else {
                    $yzqx = '临时';
                }
                $basis = [];
                $basis[] = '医嘱名称【' . $v['YZMC'] . '】' . '【' . $yzqx . '】';
                $basis[] = '开嘱时间【' . $v['KZSJ'] . '】';

                $basis[] = '抗菌药名称【' . $kjywName . '】';

                if (empty($resData1[1])) {

                    $basis[] = '病程记录【无】';
                    // 检查是否举例完成时间小于两小时
                    $bgsj = strtotime($v['KZSJ']);
                    $diffTime = $bgsj - time();
                    if ($diffTime < $this->diffHoure * 3600 && $diffTime > 0) {
                        $res = remainderTime($diffTime);
                        $content = '请在' . $res . '内在记录' . $kjywName . '相关病程记录';
                        $msgYj = ['开嘱时间【' . $v['KZSJ'] . '】', '医嘱名称【' . $v['YZMC'] . '】', '使用频次【' . $v['SYPC'] . '】', '使用剂量【' . $v['YCJL'] . '】', '24小时内的病程中无记录'];
                        $this->caseService->sendMsg($ZYH, $content, 109, $msgYj, $v['YZBXH'], '医嘱');
                    }
                    $basisList[] = $basis;
                } else {
                    /* $firstBlsyTime = strtotime($resData1[0][0]["first_blsy_time"]);
                    if ($firstBlsyTime < strtotime($startTime) || $firstBlsyTime > strtotime($endTime)) {
                        $basis[] = '病程记录【' . $resData1[0][0]["BLMC"] . '】';
                        $basis[] = '首次签名时间【' . $resData1[0][0]["first_blsy_time"] . ' （超时）】';
                        $basisList[] = $basis;
                    } */

                    $isZk = false;

                    foreach ($resData1 as $key => $item) {
                        $blmc = $item["BLMC"];
                        $hjnr = $item["HJNR"];
                        if (strpos($blmc, '会诊') !== false) {
                            //把痕迹内容中  “会诊意见给予”之后的内容全部删除
                            /* if (strpos($hjnr, '会诊意见给予') !== false) {
                                $hjnr = substr($hjnr, 0, strpos($hjnr, '会诊意见给予'));
                            } */
                            //是否包含抗菌药名称
                            if (strpos($hjnr, $kjywName) === false) {
                                //移除这条
                                unset($resData1[$key]);
                            }
                        }
                    }
                    //移除空元素
                    $resData1 = array_values($resData1);
                    if (empty($resData1)) {
                        $basis[] = '病程记录【无】';
                        $basisList[] = $basis;
                        $bgsj = strtotime($v['KZSJ']);
                        $diffTime = $bgsj - time();
                        if ($diffTime < $this->diffHoure * 3600 && $diffTime > 0) {
                            $res = remainderTime($diffTime);
                            $content = '请在' . $res . '内在记录' . $kjywName . '相关病程记录';
                            $msgYj = ['开嘱时间【' . $v['KZSJ'] . '】', '医嘱名称【' . $v['YZMC'] . '】', '使用频次【' . $v['SYPC'] . '】', '使用剂量【' . $v['YCJL'] . '】', '24小时内的病程中无记录'];
                            $this->caseService->sendMsg($ZYH, $content, 109, $msgYj, $v['YZBXH'], '医嘱');
                        }
                    } else {
                        foreach ($resData1 as $item) {
                            if (empty($item["first_blsy_time"])) {
                                $basis[] = '病程记录【' . $item["BLMC"] . '】' . '首次签名时间【无】';
                            } else {
                                $firstBlsyTime = strtotime($item["first_blsy_time"]);
                                if ($firstBlsyTime < strtotime($startTime) || $firstBlsyTime > strtotime($endTime)) {
                                    $basis[] = '病程记录【' . $item["BLMC"] . '】' . '首次签名时间【' . $item["first_blsy_time"] . ' （超时）】';
                                    $basis["BLBH"] = $item["BLBH"];
                                } else {
                                    $basis[] = '病程记录【' . $item["BLMC"] . '】' . '首次签名时间【' . $item["first_blsy_time"] . ' 】';
                                    $isZk = true;
                                }
                            }
                        }
                        if (!$isZk) {
                            $basisList[] = $basis;
                        }
                    }


                    $this->caseService->setSendLogStatus($ZYH, 109, 1);
                }
            }

            if ($basisList) {
                $this->insertData[] = [
                    'basis' => json_encode($basisList, 256),
                    'JZHM' => $ZYH,
                    'rule_id' => 109,
                    'code' => 'kjy',
                    'error_field' => $caseRule[109]['title']
                ];
            }
        }

        return [];
    }

    /**
     * 抗菌药开嘱时间前24小时至开嘱后72小时，病程中搜索抗菌药（抗菌药物别名 或 id2053 ）且不包含（id2055）
     * 先查ZXSJ是否符合，再看首次签名时间是否符合
     */
    public function rule110($caseRule, $ruleId, $ZYH, $isYj = 0): array
    {
        $errorNotice = [];
        $basisList = [];
        $map9009 = RuleWordMap::query()->where('id', 8068)->value('keyword');
        if (strpos($map9009, ',') !== false) {
            $map9009 = explode(',', $map9009);
        } else {
            $map9009 = [$map9009];
        }
        $map9015 = RuleWordMap::getFirstById(9015);
        //按hlyw_name分组,kzsj最早
        $resData = Yzb::query()
            ->select(['YZMC', 'JLDW', 'SYPC', 'YCJL', 'KZSJ', 'hlyw_name', 'YZBXH', 'YZQX'])
            ->where('ZYH', '=', (string)$ZYH)
            ->where('YZZT', '!=', 3)
            ->where('is_has_hlyw', '=', 1)
            ->groupBy('hlyw_name')
            ->orderBy('KZSJ', 'asc')
            ->get()->toArray();

        if (!empty($resData)) {
            foreach ($resData as $v) {
                $hlywName = $v['hlyw_name'];
                $startTime = date("Y-m-d H:i:s", strtotime($v['KZSJ']) - 24 * 3600);
                $endTime = date("Y-m-d H:i:s", strtotime($v['KZSJ']) + 24 * 3600);
                //获取当前时间
                $currentDate = Carbon::now()->toDateTimeString();
                //如果当前时间小于开嘱时间+72小时就跳过
                if (strtotime($currentDate) < strtotime($endTime) && $isYj == 0) {
                    continue;
                }
                // 从MySQL查询24小时内的病程记录
                $resData1 = EMR_BL_BL01::query()
                    ->leftJoin('EMR_BL_BLXG', 'EMR_BL_BL01.BLBH', '=', 'EMR_BL_BLXG.BLBH')
                    ->where('JZHM', $ZYH)
                    ->whereIn('BLLB', $map9009)
                    ->where('ZXSJ', '>=', $startTime)
                    ->where('ZXSJ', '<=', $endTime)
                    ->select(['EMR_BL_BL01.BLBH', 'EMR_BL_BL01.CJSJ', 'EMR_BL_BL01.BLMC', 'EMR_BL_BL01.BLLB', 'EMR_BL_BL01.first_blsy_time', 'EMR_BL_BLXG.HJNR'])
                    ->get()->toArray();

                // 过滤HJNR包含化疗药名称或关键字的记录
                $map9016 = RuleWordMap::query()->where('id', '=', 9016)->value('keyword');
                if (strpos($map9016, ',') !== false) {
                    $map9016 = explode(',', $map9016);
                } else {
                    $map9016 = [$map9016];
                }
                $filteredData = [];
                foreach ($resData1 as $record) {
                    if (strpos($record['HJNR'], $hlywName) !== false) {
                        $filteredData[] = $record;
                        continue;
                    }
                    foreach ($map9016 as $item) {
                        if (strpos($record['HJNR'], $item) !== false) {
                            $filteredData[] = $record;
                            break;
                        }
                    }
                }
                $resData1 = $filteredData;

                $yzqx = $v['YZQX'];
                if ($yzqx == 1) {
                    $yzqx = '长期';
                } else {
                    $yzqx = '临时';
                }
                $basis = [];
                $basis[] = '医嘱名称【' . $v['YZMC'] . '】' . '【' . $yzqx . '】';
                $basis[] = '开嘱时间【' . $v['KZSJ'] . '】';
                $basis[] = '化疗药名称【' . $hlywName . '】';
                if (empty($resData1)) {

                    $basis[] = '病程记录【无】';
                    // 检查是否举例完成时间小于两小时
                    $bgsj = strtotime($v['KZSJ']);
                    $diffTime = $bgsj - time();
                    if ($diffTime < $this->diffHoure * 3600 && $diffTime > 0) {
                        $res = remainderTime($diffTime);
                        $content = '请在' . $res . '内在记录' . $hlywName . '相关病程记录';
                        $msgYj = ['开嘱时间【' . $v['KZSJ'] . '】', '医嘱名称【' . $v['YZMC'] . '】', '使用频次【' . $v['SYPC'] . '】', '使用剂量【' . $v['YCJL'] . '】', '24小时内的病程中无记录'];
                        $this->caseService->sendMsg($ZYH, $content, 110, $msgYj, $v['YZBXH'], '医嘱');
                    }
                    $basisList[] = $basis;
                } else {
                    /* $firstBlsyTime = strtotime($resData1[0][0]["first_blsy_time"]);
                    if ($firstBlsyTime < strtotime($startTime) || $firstBlsyTime > strtotime($endTime)) {
                        $basis[] = '病程记录【' . $resData1[0][0]["BLMC"] . '】';
                        $basis[] = '首次签名时间【' . $resData1[0][0]["first_blsy_time"] . ' （超时）】';
                        $basisList[] = $basis;
                    } */
                    $isZk = false;
                    foreach ($resData1 as $key => $item) {
                        $blmc = $item["BLMC"];
                        $hjnr = $item["HJNR"];
                        if (strpos($blmc, '会诊') !== false) {
                            //把痕迹内容中  “会诊意见给予”之后的内容全部删除
                            if (strpos($hjnr, '会诊意见给予') !== false) {
                                $hjnr = substr($hjnr, 0, strpos($hjnr, '会诊意见给予'));
                            }
                            //是否包含化疗药名称
                            if (strpos($hjnr, $hlywName) === false) {
                                //移除这条
                                unset($resData1[$key]);
                            }
                        }
                    }
                    //移除空元素
                    $resData1 = array_values($resData1);
                    if (empty($resData1)) {
                        $basis[] = '病程记录【无】';
                        $basisList[] = $basis;
                        $bgsj = strtotime($v['KZSJ']);
                        $diffTime = $bgsj - time();
                        if ($diffTime < $this->diffHoure * 3600 && $diffTime > 0) {
                            $res = remainderTime($diffTime);
                            $content = '请在' . $res . '内在记录' . $hlywName . '相关病程记录';
                            $msgYj = ['开嘱时间【' . $v['KZSJ'] . '】', '医嘱名称【' . $v['YZMC'] . '】', '使用频次【' . $v['SYPC'] . '】', '使用剂量【' . $v['YCJL'] . '】', '24小时内的病程中无记录'];
                            $this->caseService->sendMsg($ZYH, $content, 110, $msgYj, $v['YZBXH'], '医嘱');
                        }
                    } else {
                        foreach ($resData1 as $item) {
                            if (empty($item["first_blsy_time"])) {
                                $basis[] = '病程记录【' . $item["BLMC"] . '】' . '首次签名时间【无】';
                            } else {
                                $firstBlsyTime = strtotime($item["first_blsy_time"]);
                                if ($firstBlsyTime < strtotime($startTime) || $firstBlsyTime > strtotime($endTime)) {
                                    $basis[] = '病程记录【' . $item["BLMC"] . '】' . '首次签名时间【' . $item["first_blsy_time"] . ' （超时）】';
                                    $basis["BLBH"] = $item["BLBH"];
                                } else {
                                    $basis[] = '病程记录【' . $item["BLMC"] . '】' . '首次签名时间【' . $item["first_blsy_time"] . ' 】';
                                    $isZk = true;
                                }
                            }
                        }
                        if (!$isZk) {
                            $basisList[] = $basis;
                        }
                    }

                    $this->caseService->setSendLogStatus($ZYH, 110, 1);
                }
            }

            if ($basisList) {
                $this->insertData[] = [
                    'basis' => json_encode($basisList, 256),
                    'JZHM' => $ZYH,
                    'rule_id' => 110,
                    'code' => 'hly',
                    'error_field' => $caseRule[110]['title']
                ];
            }
        }

        return [];
    }


    /**
     * 病案首页规则验证
     * @param $caseRule
     * @param $ZYH
     * @return true
     */
    public function rule95($caseRule, $ruleId, $ZYH, $isYj = 0)
    {

        // 1. 获取出院/离院医嘱配置项
        $ruleMap8000 = RuleWordMap::query()->where('id', '=', 8000)->value('keyword');
        $ruleMap8000 = !empty($ruleMap8000) ? $ruleMap8000 : "出院,离院";  // 医嘱名称包含的关键字
        //判断是否包含逗号
        if (strpos($ruleMap8000, ',') !== false) {
            $exitKeywords = explode(',', $ruleMap8000);
        } else {
            $exitKeywords = [$ruleMap8000];
        }

        // 2. 查询医嘱是否包含出院/离院关键字
        $yzbQuery = Yzb::query()->where('ZYH', $ZYH)->get()->toArray();

        $yzbData = [];
        foreach ($yzbQuery as $item) {
            if (strpos($item['YZMC'], '死亡') !== false) {
                return false;
            }
            foreach ($exitKeywords as $keyword) {
                if (strpos($item['YZMC'], trim($keyword)) !== false) {
                    $yzbData[] = $item;
                }
            }
        }

        // 如果没有出院/离院医嘱，则不进行质控
        if (empty($yzbData)) {
            return true;
        }

        // 获取患者信息，使用ZY_BRRY表
        $patientInfo = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->first();
        if (empty($patientInfo)) {
            return true;
        }

        // 获取出院时间
        $exitTime = $patientInfo['AAC01'];
        if (empty($exitTime) || $exitTime == '1970-01-01 00:00:00' || $exitTime == '0000-00-00 00:00:00') {
            return true; // 没有有效的出院时间
        }

        //获取当前时间
        $currentDate = Carbon::now()->toDateTimeString();

        // 计算出院后24小时的时间点
        $exitTimeEnd = strtotime($exitTime) + 24 * 3600;

        //如果当前时间小于出院时间+24小时就跳过
        if ((strtotime($currentDate) < $exitTimeEnd) && $isYj == 0) {
            return true;
        }

        // 3. 获取病案首页配置
        $ruleMap8001 = RuleWordMap::query()->where('id', '=', 8001)->value('keyword');
        $ruleMap8001 = !empty($ruleMap8001) ? $ruleMap8001 : 2000001;  // 病案首页BLLB

        //判断是否包含逗号
        if (strpos($ruleMap8001, ',') !== false) {
            $exitKeywords = explode(',', $ruleMap8001);
        } else {
            $exitKeywords = [$ruleMap8001];
        }

        // 4. 查询病案首页数据，注意病案首页BLLB可能包含多个值
        $bl01Data = EMR_BL_BL01::query()->where(['JZHM' => $ZYH])->whereIn('BLLB', $exitKeywords)->get()->toArray();

        $basis = [];

        // 出院时间依据
        $basis[] = "出院时间【" . $exitTime . "】";

        // 如果没有找到病案首页
        if (empty($bl01Data)) {
            /* $basis[] = "病案首页首次签名时间【不足6人签名】";

            $this->insertData[] = [
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title'],
                'basis' => json_encode([$basis], JSON_UNESCAPED_UNICODE)
            ];
            //发送预警
            // 检查出院时间+8小时-当前之间是否大于0小于2小时
            $exitTimeEnd = strtotime($exitTime) + 24 * 3600;
            $diffTime = $exitTimeEnd - time();
            if ($diffTime > 0 && $diffTime < 2 * 3600) {
                $res = remainderTime($diffTime);
                $content = '请在' . $res . '内完成病案首页首次签名';
                $msgYj = ['出院时间【' . $exitTime . '】', '病案首页【未创建】'];
                $this->caseService->sendMsg($ZYH, $content, 95, $msgYj);
            } */

            return true;
        }

        // 5. 获取病案首页签名记录
        $blbh = $bl01Data[0]['BLBH'];

        // 查询签名记录
        $blsyGroups = EMR_BL_BLSY::query()
            ->where('BLBH', '=', $blbh)
            ->where('FG_ACTIVE', 1)
            ->get()
            ->toArray();

        // 检查是否有足够的签名组数
        //$ruleMap8002 = RuleWordMap::query()->where('id', '=', 8002)->value('keyword');
        $requiredSignCount = 6;  // 要求的签名人数

        // 如果签名组数不足
        if (count($blsyGroups) < $requiredSignCount) {
            $basis[] = "病案首页首次签名时间【不足6人签名】";
            //加入质控的病案首页的BLBH
            $basis["BLBH"] = $bl01Data[0]['BLBH'];
            $this->insertData[] = [
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title'],
                'basis' => json_encode([$basis], JSON_UNESCAPED_UNICODE)
            ];
            //发送预警
            // 检查出院时间+8小时-当前之间是否大于0小于2小时
            $exitTimeEnd = strtotime($exitTime) + 24 * 3600;
            $diffTime = $exitTimeEnd - time();
            if ($diffTime > 0 && $diffTime < 2 * 3600) {
                $res = remainderTime($diffTime);
                $content = '请在' . $res . '内完成病案首页首次签名';
                $msgYj = ['出院时间【' . $exitTime . '】', '病案首页【已创建，已有' . count($blsyGroups) . '人签名（不足6人）】'];
                $this->caseService->sendMsg($ZYH, $content, 95, $msgYj);
            }
            return true;
        }

        // 6. 处理签名时间
        $earliestSignTimes = [];

        $blsyGroupsBySyys = EMR_BL_BLSY::query()
            ->where('BLBH', '=', $blbh)
            ->where('FG_ACTIVE', 1)
            ->get()
            ->groupBy('SYYS')
            ->toArray();

        foreach ($blsyGroupsBySyys as $syys => $records) {
            // 获取每组签名记录中最早的签名时间
            $earliestTime = null;
            foreach ($records as $record) {
                $jlsj = strtotime($record['JLSJ']);
                if (is_null($earliestTime) || $jlsj < $earliestTime) {
                    $earliestTime = $jlsj;
                }
            }

            if (!is_null($earliestTime)) {
                $earliestSignTimes[] = $earliestTime;
            }
        }

        // 获取所有最早签名中的最晚时间点
        $latestOfEarliest = !empty($earliestSignTimes) ? max($earliestSignTimes) : null;

        // 如果无法确定签名时间
        if (is_null($latestOfEarliest)) {
            $basis[] = "病案首页首次签名时间【不足6人签名】";
            //加入质控的病案首页的BLBH
            $basis["BLBH"] = $bl01Data[0]['BLBH'];
            $this->insertData[] = [
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title'],
                'basis' => json_encode([$basis], JSON_UNESCAPED_UNICODE)
            ];
            return true;
        }

        // 将最晚的首次签名时间格式化，并更新到病案首页记录
        $firstBlsyTime = date('Y-m-d H:i:s', $latestOfEarliest);
        $firstBlsyTimeField = RuleWordMap::query()->where('id', '=', 8002)->value('keyword');
        EMR_BL_BL01::updateById($blbh, [$firstBlsyTimeField => $firstBlsyTime]);

        // 7. 检查签名时间是否在出院后24小时内
        if ($latestOfEarliest > $exitTimeEnd) {
            // 签名时间超过出院后24小时
            $basis[] = "病案首页首次签名时间【" . $firstBlsyTime . "（超24小时）】";
            $basis["BLBH"] = $bl01Data[0]['BLBH'];
            $this->insertData[] = [
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title'],
                'basis' => json_encode([$basis], JSON_UNESCAPED_UNICODE)
            ];
        }

        return true;
    }

    /**
     * 出院24小时内未创建出院记录
     * @param $caseRule
     * @param $ZYH
     * @return true
     */
    public function rule96($caseRule, $ruleId, $ZYH)
    {

        // 1. 获取出院时间和入院时间AAB01，如果没有取当前时间，用这个时间-入院时间如果小与24小时，则不质控
        $exitTime = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->value('AAC01');
        if (empty($exitTime)) {
            $exitTime = Carbon::now()->toDateTimeString();
        }

        //如果当前时间-出院时间小于24小时，则不质控
        $diffTime = time() - strtotime($exitTime);
        if ($diffTime < 24 * 3600) {
            return true;
        }

        // 2. 获取入院时间AAB01
        $enterTime = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->value('AAB01');
        if (empty($enterTime)) {
            return true;
        }


        // 3. 计算出院时间-入院时间
        $enterDate = date('Y-m-d', strtotime($enterTime));
        $exitDate = date('Y-m-d', strtotime($exitTime));
        $diffDays = (strtotime($exitDate) - strtotime($enterDate)) / (24 * 3600);
        //查询出院记录bllb = 1
        $cyjl = EMR_BL_BL01::query()->where('JZHM', '=', $ZYH)->where('BLLB', '=', 1)->get()->toArray();
        if ($diffDays <= 1 && empty($cyjl)) {
            return true;
        }

        //获取当前时间
        $currentDate = Carbon::now()->toDateTimeString();
        $exitTimeEnd = strtotime($exitTime) + 24 * 3600;
        //如果当前时间小于出院时间+24小时就跳过
        if (strtotime($currentDate) < $exitTimeEnd) {
            return true;
        }


        //4. 查询医嘱
        $exitKeywords = RuleWordMap::getArrayById(8000);
        // 2. 查询医嘱是否包含出院/离院关键字
        $yzbQuery = Yzb::query()->where('ZYH', $ZYH)->get()->toArray();
        $yzbData = [];
        foreach ($yzbQuery as $item) {
            if (strpos($item['YZMC'], '死亡') !== false) {
                return false;
            }
            foreach ($exitKeywords as $keyword) {
                if (strpos($item['YZMC'], trim($keyword)) !== false) {
                    $yzbData[] = $item;
                }
            }
        }

        // 如果没有出院/离院医嘱，则不进行质控
        if (empty($yzbData)) {
            return true;
        }

        //6. 查询出院记录
        $ruleMap8001 = RuleWordMap::query()->where('id', '=', 8004)->value('keyword');
        $ruleMap8001 = !empty($ruleMap8001) ? $ruleMap8001 : 1;  // 病案首页BLLB


        //判断是否包含逗号
        if (strpos($ruleMap8001, ',') !== false) {
            $bllbKeywords = explode(',', $ruleMap8001);
        } else {
            $bllbKeywords = [$ruleMap8001];
        }
        $basis = [];

        // 出院时间依据
        $basis[] = "出院时间【" . $exitTime . "】";

        // 4. 查询病案首页数据，注意病案首页BLLB可能包含多个值
        $bl01Data = EMR_BL_BL01::query()->where(['JZHM' => $ZYH])->whereIn('BLLB', $bllbKeywords)->get()->toArray();

        if (empty($bl01Data)) {
            $basis[] = "出院记录【未创建】";
            $this->insertData[] = [
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title'],
                'basis' => json_encode([$basis], JSON_UNESCAPED_UNICODE)
            ];
            //发送预警
            // 检查出院时间+24小时-当前之间是否大于0小于2小时
            $exitTimeEnd = strtotime($exitTime) + 24 * 3600;
            $diffTime = $exitTimeEnd - time();
            if ($diffTime > 0 && $diffTime < 2 * 3600) {
                $res = remainderTime($diffTime);
                $content = '请在' . $res . '内完成出院记录创建';
                $msgYj = ['出院时间【' . $exitTime . '】', '出院记录【未创建】'];
                $this->caseService->sendMsg($ZYH, $content, 96, $msgYj);
            }
            return true;
        }

        //取id8002的keyword
        $ruleMap8002 = RuleWordMap::query()->where('id', '=', 8002)->value('keyword');
        $ruleMap8002 = !empty($ruleMap8002) ? $ruleMap8002 : 'frist_blsy_time';

        $firstBlsyTime = $bl01Data[0][$ruleMap8002];
        //如果为空设置basis
        if (empty($firstBlsyTime)) {
            $basis[] = "出院记录首次签名时间【无】";
            //加入质控的病案首页的BLBH
            $basis["BLBH"] = $bl01Data[0]['BLBH'];
            $this->insertData[] = [
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title'],
                'basis' => json_encode([$basis], JSON_UNESCAPED_UNICODE)
            ];
            //发送预警
            // 检查出院时间+24小时-当前之间是否大于0小于2小时
            $exitTimeEnd = strtotime($exitTime) + 24 * 3600;
            $diffTime = $exitTimeEnd - time();
            if ($diffTime > 0 && $diffTime < 2 * 3600) {
                $res = remainderTime($diffTime);
                $content = '请在' . $res . '内完成出院记录';
                $msgYj = ['出院时间【' . $exitTime . '】', '出院记录【已创建，未签名】'];
                $this->caseService->sendMsg($ZYH, $content, 96, $msgYj);
            }
            return true;
        }

        //如果firstBlsyTime大于出院时间+24小时，则质控
        if (strtotime($firstBlsyTime) > strtotime($exitTime) + 24 * 3600) {
            $basis[] = "出院记录首次签名时间【" . $firstBlsyTime . "（超24小时）】";
            //加入质控的病案首页的BLBH
            $basis["BLBH"] = $bl01Data[0]['BLBH'];
            $this->insertData[] = [
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title'],
                'basis' => json_encode([$basis], JSON_UNESCAPED_UNICODE)
            ];
            return true;
        }
        return true;
    }

    /**
     * 入院记录
     * @param $caseRule
     * @param $ZYH
     * @return true
     */
    public function rule99($caseRule, $ruleId, $ZYH, $isYj = 0)
    {
        // 1. 获取出院时间和入院时间AAB01，如果没有取当前时间，用这个时间-入院时间如果小与24小时，则不质控
        $brry = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->get(['AAB01', 'AAC01'])->toArray();
        /* if (empty($brry)) {
            return true;
        }
        $exitTime = $brry[0]['AAC01'];
        if (empty($brry[0]['AAC01'])) {
            $exitTime = Carbon::now()->toDateTimeString();
        } */
        $currentDate = '';
        if (empty($brry[0]['AAC01'])) {
            $currentDate = Carbon::now()->toDateTimeString();
        } else {
            $currentDate = $brry[0]['AAC01'];
        }

        // 2. 获取入院时间AAB01
        $enterTime = $brry[0]['AAB01'];

        //获取当前时间
        $enterTimeEnd = date("Y-m-d H:i:s", strtotime($enterTime) + 24 * 3600);
        //如果当前时间小于入院时间+24小时就跳过
        if ((strtotime($currentDate) < strtotime($enterTimeEnd)) && $isYj == 0) {
            return true;
        }

        //查询ryjl
        $basis = [];
        // 出院时间依据
        $basis[] = "入院时间【" . $enterTime . "】";

        $keyword20046 = RuleWordMap::getArrayById(20046);
        // 4. 查询24小时内的入院记录、死亡记录、入出院记录
        $bl01Data = EMR_BL_BL01::query()
            ->where(['JZHM' => $ZYH])
            ->whereIn('BLLB', $keyword20046)
            ->get()->toArray();
        if (empty($bl01Data)) {
            $basis[] = "入院后24小时内未完成“入院记录“";
            $this->insertData[] = [
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'basis' => json_encode([$basis], JSON_UNESCAPED_UNICODE),
                'error_field' => $caseRule[$ruleId]['title'],
            ];
            //发送预警
            // 检查入院时间+24小时-当前之间是否大于0小于2小时
            $enterTimeEnd = strtotime($enterTime) + 24 * 3600;
            $diffTime = $enterTimeEnd - time();
            if ($diffTime > 0 && $diffTime < 2 * 3600) {
                $res = remainderTime($diffTime);
                $content = '请在' . $res . '内完成入院记录';
                $msgYj = ['入院时间【' . $enterTime . '】', '入院后24小时内未完成“入院记录“'];
                $this->caseService->sendMsg($ZYH, $content, 99, $msgYj);
            }
            return true;
        }

        $blsy = EMR_BL_BLSY::query()->where("BLBH", $bl01Data[0]['BLBH'])->first();
        if (empty($blsy)) {
            $basis[] = $bl01Data[0]['BLMC'] . "【未签名】";
            //加入质控的病案首页的BLBH
            $basis["BLBH"] = $bl01Data[0]['BLBH'];
            $this->insertData[] = [
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title'],
                'basis' => json_encode([$basis], JSON_UNESCAPED_UNICODE)
            ];
        }

        /* $keyword8011 = RuleWordMap::getFirstById(8011);
        $ZXSJ = $bl01Data[0][$keyword8011[0] ?? 'ZXSJ'];
        if (strtotime($ZXSJ) > strtotime($enterTime) + 24 * 3600) {
            $basis[] = "入院记录标题时间【" . $ZXSJ . "（超24小时）】";
            //加入质控的病案首页的BLBH
            $basis["BLBH"] = $bl01Data[0]['BLBH'];
            $this->insertData[] = [
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title'],
                'basis' => json_encode([$basis], JSON_UNESCAPED_UNICODE)
            ];
        } */

        return true;
    }

    /**
     * 【入院记录】首次签名时间超24小时
     * @param $caseRule
     * @param $ZYH
     * @return true
     */
    public function rule1278($caseRule, $ruleId, $ZYH, $isYj = 0)
    {
        // 1. 获取出院时间和入院时间AAB01，如果没有取当前时间，用这个时间-入院时间如果小与24小时，则不质控
        $brry = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->get(['AAB01', 'AAC01'])->toArray();
        /* if (empty($exitTime)) {
            return false;
        } */
        $exitTime = '';
        if (empty($brry[0]['AAC01'])) {
            $exitTime = Carbon::now()->toDateTimeString();
        } else {
            $exitTime = $brry[0]['AAC01'];
        }

        // 2. 获取入院时间AAB01
        $enterTime = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->value('AAB01');
        if (empty($enterTime)) {
            return true;
        }

        //获取当前时间
        $currentDate = Carbon::now()->toDateTimeString();
        $enterTimeEnd = strtotime($enterTime) + 24 * 3600;
        //如果当前时间小于入院时间+24小时就跳过
        if ((strtotime($currentDate) < $enterTimeEnd) && $isYj == 0) {
            return true;
        }

        // 3. 计算出院时间-入院时间
        $enterDate = date('Y-m-d', strtotime($enterTime));
        $exitDate = date('Y-m-d', strtotime($exitTime));
        $diffDays = (strtotime($exitDate) - strtotime($enterDate)) / (24 * 3600);
        //查询ryjl
        $ryjl = EMR_BL_BL01::query()->where('JZHM', '=', $ZYH)->where('BLLB', '=', 292)->get()->toArray();
        if ($diffDays <= 1 && $isYj == 0) {
            if (empty($ryjl)) {
                return true;
            } else {
                if (empty($ryjl[0]['first_blsy_time'])) {
                    return true;
                }
            }
        }

        //6. 查询入院记录
        $ruleMap8001 = RuleWordMap::query()->where('id', '=', 8006)->value('keyword');
        $ruleMap8001 = !empty($ruleMap8001) ? $ruleMap8001 : 292;  // 病案首页BLLB

        //判断是否包含逗号
        if (strpos($ruleMap8001, ',') !== false) {
            $exitKeywords = explode(',', $ruleMap8001);
        } else {
            $exitKeywords = [$ruleMap8001];
        }
        $basis = [];

        // 出院时间依据
        $basis[] = "入院时间【" . $enterTime . "】";
        $keyword20046 = RuleWordMap::getArrayById(20046);
        // 4. 查询病案首页数据，注意病案首页BLLB可能包含多个值
        $bl01Data = EMR_BL_BL01::query()->where(['JZHM' => $ZYH])->whereIn('BLLB', $exitKeywords)->get()->toArray();

        if (empty($bl01Data)) {
            // 4. 查询24小时内的入院记录、死亡记录、入出院记录
            $bl01Data = EMR_BL_BL01::query()
                ->where(['JZHM' => $ZYH])
                ->whereIn('BLLB', $keyword20046)
                ->get()->toArray();
            if (empty($bl01Data)) {
                return true;
            }
        }


        //取id8002的keyword
        $ruleMap8002 = RuleWordMap::query()->where('id', '=', 8002)->value('keyword');
        $ruleMap8002 = !empty($ruleMap8002) ? $ruleMap8002 : 'first_blsy_time';
        $firstBlsyTime = !empty($bl01Data) && !empty($bl01Data[0]) ? $bl01Data[0][$ruleMap8002] : "";
        if (empty($firstBlsyTime)) {
            return false;
        }

        if (strtotime($firstBlsyTime) > strtotime($enterTime) + 24 * 3600) {
            $basis[] = "入院记录首次签名时间【" . $firstBlsyTime . "（超24小时）】";
            //加入质控的病案首页的BLBH
            $basis["BLBH"] = $bl01Data[0]['BLBH'];
            $this->insertData[] = [
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title'],
                'basis' => json_encode([$basis], JSON_UNESCAPED_UNICODE)
            ];
        }
        return true;
    }

    /**
     * 患者自动出院或转院同意书，患者未签字
     * @param $caseRule
     * @param $ZYH
     * @return true
     */
    public function rule1280($caseRule, $ruleId, $ZYH, $isYj = 0)
    {

        $exitTime = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->value('AAC01');
        if (empty($exitTime)) {
            return true;
        }

        //查询ryjl
        $bllb329 = EMR_BL_BL01::query()->where('JZHM', '=', $ZYH)->where('BLLB', '=', 329)->get()->toArray();
        $cybl01 = [];
        foreach ($bllb329 as $v) {
            if (strpos($v['BLMC'], '自动出院或转院同意书') !== false) {
                $cybl01 = $v;
                break;
            }
        }

        if (empty($cybl01)) {
            return false;
        }
        $blsy = EMR_BL_BLSY::query()->where("BLBH", $cybl01['BLBH'])->where("QMLX", 2)->get()->toArray();
        if (empty($blsy)) {
            $this->insertData[] = [
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title'],
            ];
        }
        return true;
    }

    /**
     * 【自动出院或转院同意书】，患者未签字
     * 1. bl01：BLLB=329，BLMC包含字典配置关键字
     * 2. blsy：按BLBH关联，QMLX=2为患者签名
     */
    public function rule1283($caseRule, $ruleId, $ZYH, $isYj = 0)
    {
        $keywords = RuleWordMap::query()->where('id', '=', '8113')->value('keyword');
        $keywords = !empty($keywords) ? $keywords : '自动出院或转院同意书';
        $keywords = strpos($keywords, ',') !== false
            ? array_values(array_filter(array_map('trim', explode(',', $keywords))))
            : [trim($keywords)];

        $bl01List = EMR_BL_BL01::query()
            ->where('JZHM', '=', $ZYH)
            ->where('BLLB', '=', 329)
            ->get()
            ->toArray();
        if (empty($bl01List)) {
            return false;
        }

        $targetDocs = [];
        foreach ($bl01List as $record) {
            $blmc = $record['BLMC'] ?? '';
            if (empty($blmc)) {
                continue;
            }

            foreach ($keywords as $keyword) {
                if ($keyword !== '' && mb_strpos($blmc, $keyword) !== false) {
                    $targetDocs[] = $record;
                    break;
                }
            }
        }

        if (empty($targetDocs)) {
            return false;
        }

        $basisList = [];
        foreach ($targetDocs as $record) {
            $hasPatientSign = EMR_BL_BLSY::query()
                ->where('BLBH', $record['BLBH'])
                ->where('QMLX', 2)
                ->exists();

            if (!$hasPatientSign) {
                $basisList[] = ['【' . ($record['BLMC'] ?? '') . '】，患者未签字'];
            }
        }

        if (!empty($basisList)) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, JSON_UNESCAPED_UNICODE),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }

        return true;
    }

    /**
     * 【首次病程记录】无诊断依据或过于简单
     * 1. 取 bllb294_295 中所有首次病程记录
     * 2. 取 zdyj，若为空或字数少于 id8114（默认50）则质控
     */
    public function rule1284($caseRule, $ruleId, $ZYH, $isYj = 0)
    {
        $records = Bllb294_295::query()->where('ZYH', $ZYH)->get()->toArray();
        if (empty($records)) {
            return false;
        }

        $minLength = RuleWordMap::query()->where('id', '=', 8114)->value('keyword');
        $minLength = is_numeric($minLength) ? (int)$minLength : 50;

        $basisList = [];
        foreach ($records as $record) {
            $zdyj = trim(strip_tags((string)($record['ZDYJ'] ?? '')));
            $zdyjLength = mb_strlen(preg_replace('/\s+/u', '', $zdyj));

            if ($zdyj !== '' && $zdyjLength >= $minLength) {
                continue;
            }

            $basis = [];
            $basis[] = '首次病程记录【' . ($record['BLMC'] ?? '首次病程记录') . '】';
            if ($zdyj === '') {
                $basis[] = '诊断依据【无】';
            } else {
                $basis[] = '诊断依据字数【少于' . $minLength . '字】';
            }
            $basis['BLBH'] = $record['BLBH'] ?? '';
            $basisList[] = $basis;
        }

        if (!empty($basisList)) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, JSON_UNESCAPED_UNICODE),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }

        return true;
    }

    /**
     * 【首次病程记录】无诊疗计划
     * 1. 取 bllb294_295 中所有首次病程记录
     * 2. 取 zljh，若为空或字数少于 id8115（默认30）则质控
     */
    public function rule1285($caseRule, $ruleId, $ZYH, $isYj = 0)
    {
        $records = Bllb294_295::query()->where('ZYH', $ZYH)->get()->toArray();
        if (empty($records)) {
            return false;
        }

        $minLength = RuleWordMap::query()->where('id', '=', 8115)->value('keyword');
        $minLength = is_numeric($minLength) ? (int)$minLength : 30;

        $basisList = [];
        foreach ($records as $record) {
            $zljh = trim(strip_tags((string)($record['ZLJH'] ?? '')));
            $zljhLength = mb_strlen(preg_replace('/\s+/u', '', $zljh));

            if ($zljh !== '' && $zljhLength >= $minLength) {
                continue;
            }

            $basis = [];
            $basis[] = '首次病程记录【' . ($record['BLMC'] ?? '首次病程记录') . '】';
            if ($zljh === '') {
                $basis[] = '诊疗计划【无】';
            } else {
                $basis[] = '诊疗计划字数【少于' . $minLength . '字】';
            }
            $basis['BLBH'] = $record['BLBH'] ?? '';
            $basisList[] = $basis;
        }

        if (!empty($basisList)) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, JSON_UNESCAPED_UNICODE),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }

        return true;
    }
    /**
     * 入院记录
     * @param $caseRule
     * @param $ZYH
     * @return true
     */
    public function rule1279($caseRule, $ruleId, $ZYH, $isYj = 0)
    {


        // 1. 获取出院时间和入院时间AAB01，如果没有取当前时间，用这个时间-入院时间如果小与24小时，则不质控
        $exitTime = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->value('AAC01');
        if (empty($exitTime)) {
            $exitTime = Carbon::now()->toDateTimeString();
        }

        // 2. 获取入院时间AAB01
        $enterTime = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->value('AAB01');
        if (empty($enterTime)) {
            return true;
        }

        //获取当前时间
        $currentDate = Carbon::now()->toDateTimeString();
        $enterTimeEnd = strtotime($enterTime) + 24 * 3600;
        //如果当前时间小于入院时间+24小时就跳过
        if ((strtotime($currentDate) < $enterTimeEnd) && $isYj == 0) {
            return true;
        }

        // 3. 计算出院时间-入院时间
        $enterDate = date('Y-m-d', strtotime($enterTime));
        $exitDate = date('Y-m-d', strtotime($exitTime));
        $diffDays = (strtotime($exitDate) - strtotime($enterDate)) / (24 * 3600);
        //查询ryjl
        $ryjl = EMR_BL_BL01::query()->where('JZHM', '=', $ZYH)->where('BLLB', '=', 292)->get()->toArray();
        if ($diffDays <= 1 && $isYj == 0) {
            if (empty($ryjl)) {
                return true;
            } else {
                if (empty($ryjl[0]['first_blsy_time'])) {
                    return true;
                }
            }
        }

        //6. 查询入院记录
        $ruleMap8001 = RuleWordMap::query()->where('id', '=', 8006)->value('keyword');
        $ruleMap8001 = !empty($ruleMap8001) ? $ruleMap8001 : 292;  // 病案首页BLLB

        //判断是否包含逗号
        if (strpos($ruleMap8001, ',') !== false) {
            $exitKeywords = explode(',', $ruleMap8001);
        } else {
            $exitKeywords = [$ruleMap8001];
        }
        $basis = [];

        // 出院时间依据
        $basis[] = "入院时间【" . $enterTime . "】";
        // 4. 查询病案首页数据，注意病案首页BLLB可能包含多个值
        $bl01Data = EMR_BL_BL01::query()->where(['JZHM' => $ZYH])->whereIn('BLLB', $exitKeywords)->get()->toArray();
        if (empty($bl01Data)) {
            return true;
        }

        $blsy = EMR_BL_BLSY::query()->where("BLBH", $bl01Data[0]['BLBH']);
        if (empty($blsy)) {
            $basis[] = "入院记录【未签名】";
            //加入质控的病案首页的BLBH
            $basis["BLBH"] = $bl01Data[0]['BLBH'];
            $this->insertData[] = [
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title'],
                'basis' => json_encode([$basis], JSON_UNESCAPED_UNICODE)
            ];
        }
        return true;
    }


    /**
     * 病案首页，死亡患者在死亡后1周内要完成病案首页
     * @param [type] $caseRule
     * @param [type] $ruleId
     * @param [type] $ZYH
     * @return bool
     * @author lch
     * @datetime 2024-11-08
     */
    public function rule278($caseRule, $ruleId, $ZYH)
    {

        // 1. 获取出院/离院医嘱配置项
        $ruleMap8000 = RuleWordMap::query()->where('id', '=', 8003)->value('keyword');
        $ruleMap8000 = !empty($ruleMap8000) ? $ruleMap8000 : "死亡";  // 医嘱名称包含的关键字
        //判断是否包含逗号
        if (strpos($ruleMap8000, ',') !== false) {
            $exitKeywords = explode(',', $ruleMap8000);
        } else {
            $exitKeywords = [$ruleMap8000];
        }

        // 2. 从MySQL查询医嘱是否包含出院/离院关键字
        $yzbQuery = Yzb::query()->where('ZYH', $ZYH)->get()->toArray();
        $yzbData = [];
        foreach ($yzbQuery as $record) {
            foreach ($exitKeywords as $keyword) {
                if (strpos($record['YZMC'], $keyword) !== false) {
                    $yzbData[] = $record['YZMC'];
                    break;
                }
            }
        }

        // 如果没有出院/离院医嘱，则不进行质控
        if (empty($yzbData)) {
            return true;
        }

        // 获取患者信息，使用ZY_BRRY表
        $patientInfo = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->first();
        if (empty($patientInfo)) {
            return true;
        }

        // 获取出院时间
        $exitTime = $patientInfo['AAC01'];
        if (empty($exitTime) || $exitTime == '1970-01-01 00:00:00' || $exitTime == '0000-00-00 00:00:00') {
            return true; // 没有有效的出院时间
        }

        //获取当前时间，如果当前时间-出院时间小于7天，则不质控
        $currentDate = Carbon::now()->toDateTimeString();
        $diffTime = strtotime($currentDate) - strtotime($exitTime);
        if ($diffTime < 7 * 24 * 3600) {
            return true;
        }

        // 计算出院后七天的时间点
        $exitTimeEnd = strtotime($exitTime) + 7 * 24 * 3600;

        // 3. 获取病案首页配置
        $ruleMap8001 = RuleWordMap::query()->where('id', '=', 8001)->value('keyword');
        $ruleMap8001 = !empty($ruleMap8001) ? $ruleMap8001 : 2000001;  // 病案首页BLLB

        //判断是否包含逗号
        if (strpos($ruleMap8001, ',') !== false) {
            $exitKeywords = explode(',', $ruleMap8001);
        } else {
            $exitKeywords = [$ruleMap8001];
        }

        // 4. 查询病案首页数据，注意病案首页BLLB可能包含多个值
        $bl01Data = EMR_BL_BL01::query()->where(['JZHM' => $ZYH])->whereIn('BLLB', $exitKeywords)->get()->toArray();

        $basis = [];

        // 出院时间依据
        $basis[] = "死亡时间【" . $exitTime . "】";


        // 如果没有找到病案首页
        if (empty($bl01Data)) {
            /* $basis[] = "病案首页首次签名时间【不足6人签名】";
            $this->insertData[] = [
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title'],
                'basis' => json_encode([$basis], JSON_UNESCAPED_UNICODE)
            ];
            //发送预警
            // 检查死亡时间+7天-当前之间是否大于0小于2小时
            $exitTimeEnd = strtotime($exitTime) + 7 * 24 * 3600;
            $diffTime = $exitTimeEnd - time();
            if ($diffTime > 0 && $diffTime < 2 * 3600) {
                $res = remainderTime($diffTime);
                $content = '请在' . $res . '内完成病案首页';
                $msgYj = ['死亡时间【' . $exitTime . '】', '病案首页【未创建】'];
                $this->caseService->sendMsg($ZYH, $content, 278, $msgYj);
            } */
            return true;
        }

        // 5. 获取病案首页签名记录
        $blbh = $bl01Data[0]['BLBH'];

        // 查询签名记录并按SYYS分组
        $blsyGroups = EMR_BL_BLSY::query()
            ->where('BLBH', '=', $blbh)
            ->where('FG_ACTIVE', 1)
            ->get()
            ->groupBy('SYYS')
            ->toArray();

        // 检查是否有足够的签名组数
        //$ruleMap8002 = RuleWordMap::query()->where('id', '=', 8002)->value('keyword');
        $requiredSignCount = 3;  // 要求的签名人数

        // 如果签名组数不足
        if (count($blsyGroups) < $requiredSignCount) {
            $basis[] = "病案首页首次签名时间【不足6人签名】";
            //加入质控的病案首页的BLBH
            $basis["BLBH"] = $bl01Data[0]['BLBH'];
            $this->insertData[] = [
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title'],
                'basis' => json_encode([$basis], JSON_UNESCAPED_UNICODE)
            ];
            //发送预警
            // 检查死亡时间+7天-当前之间是否大于0小于2小时
            $exitTimeEnd = strtotime($exitTime) + 7 * 24 * 3600;
            $diffTime = $exitTimeEnd - time();
            if ($diffTime > 0 && $diffTime < 2 * 3600) {
                $res = remainderTime($diffTime);
                $content = '请在' . $res . '内完成病案首页';
                $msgYj = ['死亡时间【' . $exitTime . '】', '病案首页【已创建，已有' . count($blsyGroups) . '人签名】'];
                $this->caseService->sendMsg($ZYH, $content, 278, $msgYj);
            }
            return true;
        }

        // 6. 处理签名时间
        $earliestSignTimes = [];

        foreach ($blsyGroups as $syys => $records) {
            // 获取每组签名记录中最早的签名时间
            $earliestTime = null;
            foreach ($records as $record) {
                $jlsj = strtotime($record['JLSJ']);
                if (is_null($earliestTime) || $jlsj < $earliestTime) {
                    $earliestTime = $jlsj;
                }
            }

            if (!is_null($earliestTime)) {
                $earliestSignTimes[] = $earliestTime;
            }
        }

        // 获取所有最早签名中的最晚时间点
        $latestOfEarliest = !empty($earliestSignTimes) ? max($earliestSignTimes) : null;

        // 如果无法确定签名时间
        if (is_null($latestOfEarliest)) {
            $basis[] = "病案首页首次签名时间【不足6人签名】";
            //加入质控的病案首页的BLBH
            $basis["BLBH"] = $bl01Data[0]['BLBH'];
            $this->insertData[] = [
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title'],
                'basis' => json_encode([$basis], JSON_UNESCAPED_UNICODE)
            ];
            return true;
        }

        // 将最晚的首次签名时间格式化，并更新到病案首页记录
        $firstBlsyTime = date('Y-m-d H:i:s', $latestOfEarliest);
        //8002 病案首页首次签名时间字段
        $firstBlsyTimeField = RuleWordMap::query()->where('id', '=', 8002)->value('keyword');
        EMR_BL_BL01::updateById($blbh, [$firstBlsyTimeField => $firstBlsyTime]);

        // 7. 检查签名时间是否在出院后七天内
        if ($latestOfEarliest > $exitTimeEnd) {
            // 签名时间超过出院后七天
            $basis[] = "病案首页首次签名时间【" . $firstBlsyTime . "（超7天）】";
            //加入质控的病案首页的BLBH
            $basis["BLBH"] = $bl01Data[0]['BLBH'];
            $this->insertData[] = [
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title'],
                'basis' => json_encode([$basis], JSON_UNESCAPED_UNICODE)
            ];
        }

        return true;
    }


    /**
     * 患者死亡后24小时内要完成死亡记录
     * @param mixed $caseRule
     * @param mixed $ruleId
     * @param mixed $ZYH
     * @return bool
     * @author lch
     * @datetime 2024-11-08
     */
    public function rule98($caseRule, $ruleId, $ZYH)
    {

        // 1. 获取出院时间和入院时间AAB01，如果没有取当前时间，用这个时间-入院时间如果小与24小时，则不质控
        $exitTime = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->value('AAC01');
        if (empty($exitTime)) {
            $exitTime = Carbon::now()->toDateTimeString();
        }

        // 2. 获取入院时间AAB01
        $enterTime = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->value('AAB01');
        if (empty($enterTime)) {
            return true;
        }

        // 3. 计算出院时间-入院时间
        $enterDate = date('Y-m-d', strtotime($enterTime));
        $exitDate = date('Y-m-d', strtotime($exitTime));
        $diffDays = (strtotime($exitDate) - strtotime($enterDate)) / (24 * 3600);
        //查询是否有死亡记录
        $swjl = EMR_BL_BL01::query()->where('JZHM', '=', $ZYH)->where('BLLB', '=', 288)->first();
        if ($diffDays <= 1 && empty($swjl)) {
            return true;
        }

        //获取当前时间
        $currentDate = Carbon::now()->toDateTimeString();
        $exitTimeEnd = strtotime($exitTime) + 24 * 3600;
        //如果当前时间小于出院时间+24小时就跳过
        if (strtotime($currentDate) < $exitTimeEnd) {
            return true;
        }

        //4. 查询医嘱
        $exitKeywords = RuleWordMap::getArrayById(8003);
        $yzbQuery = Yzb::query()->where('ZYH', $ZYH)->get()->toArray();
        $yzbData = [];
        foreach ($yzbQuery as $record) {
            foreach ($exitKeywords as $keyword) {
                if (strpos($record['YZMC'], $keyword) !== false) {
                    $yzbData[] = $record['YZMC'];
                    break;
                }
            }
        }

        // 如果没有出院/离院医嘱，则不进行质控
        if (empty($yzbData)) {
            return true;
        }

        // 如果没有死亡医嘱，则不进行质控
        if (empty($yzbData)) {
            return true;
        }

        //6. 查询死亡记录
        $ruleMap8005 = RuleWordMap::query()->where('id', '=', 8005)->value('keyword');
        $ruleMap8005 = !empty($ruleMap8005) ? $ruleMap8005 : 288;  // 病案首页BLLB

        //判断是否包含逗号
        if (strpos($ruleMap8005, ',') !== false) {
            $exitKeywords8005 = explode(',', $ruleMap8005);
        } else {
            $exitKeywords8005 = [$ruleMap8005];
        }

        //8007
        $ruleMap8007 = RuleWordMap::query()->where('id', '=', 8067)->value('keyword');
        $ruleMap8007 = !empty($ruleMap8007) ? $ruleMap8007 : "288,18";
        //判断是否包含逗号
        if (strpos($ruleMap8007, ',') !== false) {
            $exitKeywords8007 = explode(',', $ruleMap8007);
        } else {
            $exitKeywords8007 = [$ruleMap8007];
        }

        $deathbl01 = EMR_BL_BL01::query()->where('JZHM', $ZYH)->whereIn('BLLB', $exitKeywords8007)->get()->toArray();

        if (!empty($deathbl01)) {
            $deathbl01Data = $deathbl01[0];
            //获取hjnr
            $hjnr = EMR_BL_BLXG::query()->where('BLBH', $deathbl01Data['BLBH'])->get(['HJNR'])->toArray();
            $hjnr = $hjnr[0]['HJNR'] ?? '';
            if (!empty($hjnr)) {
                //从hjnr中提取死亡时间：{2025-03-26 03:16} 提取时间
                if (preg_match('/死亡时间：(?:\{)?(\d{4}-\d{2}-\d{2} \d{2}:\d{2})(?:\})?/', $hjnr, $matches)) {
                    $deathTime = $matches[1];
                    // 记录成功提取的时间（可选，排查后可移除）
                    //Log::debug('成功提取死亡时间', ['time' => $deathTime]);
                } else {
                    // 尝试其他可能的格式
                    if (preg_match('/死亡时间[:：].*?(\d{4}[-\/]\d{1,2}[-\/]\d{1,2}\s+\d{1,2}:\d{1,2})/', $hjnr, $matches)) {
                        $deathTime = $matches[1];
                        // 标准化日期格式
                        $deathTime = date('Y-m-d H:i', strtotime($deathTime));
                        //Log::debug('使用备用格式提取死亡时间', ['time' => $deathTime]);
                        //死亡时间：{2025-03-03 17:15}

                    } elseif (preg_match('/于(\d{4}[-\/]\d{1,2}[-\/]\d{1,2}\s+\d{1,2}:\d{1,2})临床死亡/', $hjnr, $matches)) {
                        $deathTime = $matches[1];
                        // 标准化日期格式
                        $deathTime = date('Y-m-d H:i', strtotime($deathTime));
                    } elseif (preg_match('/死亡时间：\{(\d{4}[-\/]\d{1,2}[-\/]\d{1,2}\s+\d{1,2}:\d{1,2})/', $hjnr, $matches)) {
                        $deathTime = $matches[1];
                        // 标准化日期格式
                        $deathTime = date('Y-m-d H:i', strtotime($deathTime));
                    }

                    //死亡时间：2025年8月28日 22:35:00
                    elseif (preg_match('/死亡时间：(\d{4}年\d{1,2}月\d{1,2}日 \d{1,2}:\d{1,2}:\d{1,2})/', $hjnr, $matches)) {
                        $deathTime = $matches[1];

                        //先转换
                        $deathTime = str_replace('年', '-', $deathTime);
                        $deathTime = str_replace('月', '-', $deathTime);
                        $deathTime = str_replace('日', '', $deathTime);
                        // 标准化日期格式
                        $deathTime = date('Y-m-d H:i', strtotime($deathTime));
                    } else {
                        $deathTime = '无';
                        Log::debug('无法提取死亡时间', ['hjnr_contains_keyword' => strpos($hjnr, '死亡时间') !== false]);
                    }

                    //死亡时间：2025年8月28日 22:35
                    if (preg_match('/死亡时间：(\d{4}年\d{1,2}月\d{1,2}日 \d{1,2}:\d{1,2})/', $hjnr, $matches)) {
                        $deathTime = $matches[1];

                        //先转换
                        $deathTime = str_replace('年', '-', $deathTime);
                        $deathTime = str_replace('月', '-', $deathTime);
                        $deathTime = str_replace('日', '', $deathTime);
                        // 标准化日期格式
                        $deathTime = date('Y-m-d H:i', strtotime($deathTime));
                    }
                }
            } else {
                $deathTime = '无';
            }
        } else {
            $deathTime = '无';
        }

        $basis = [];

        // 出院时间依据
        if ($deathTime != '无') {
            $basis[] = "死亡时间【" . $deathTime . "】";
        }

        // 4. 查询病案首页数据，注意可能包含多个值
        $bl01Data = EMR_BL_BL01::query()->where(['JZHM' => $ZYH])->whereIn('BLLB', $exitKeywords8005)->get()->toArray();

        if (empty($bl01Data)) {
            $basis[] = "死亡记录【未创建】";
            $this->insertData[] = [
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'basis' => json_encode([$basis], JSON_UNESCAPED_UNICODE),
                'error_field' => $caseRule[$ruleId]['title'],
            ];
            //发送预警
            // 检查死亡时间+24小时-当前之间是否大于0小于2小时
            $exitTimeEnd = strtotime($exitTime) + 24 * 3600;
            $diffTime = $exitTimeEnd - time();
            if ($diffTime > 0 && $diffTime < 2 * 3600) {
                $res = remainderTime($diffTime);
                $content = '请在' . $res . '内完成死亡记录';
                $msgYj = ['死亡时间【' . $exitTime . '】', '死亡记录【未创建】'];
                $this->caseService->sendMsg($ZYH, $content, 100, $msgYj);
            }
            return true;
        }


        //取id8002的keyword
        $ruleMap8002 = RuleWordMap::query()->where('id', '=', 8002)->value('keyword');
        $ruleMap8002 = !empty($ruleMap8002) ? $ruleMap8002 : 'frist_blsy_time';

        $firstBlsyTime = $bl01Data[0][$ruleMap8002];
        //如果为空设置basis
        if (empty($firstBlsyTime)) {
            $basis[] = "死亡记录首次签名时间【无】";
            //加入质控的病案首页的BLBH
            $basis["BLBH"] = $bl01Data[0]['BLBH'];
            $this->insertData[] = [
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title'],
                'basis' => json_encode([$basis], JSON_UNESCAPED_UNICODE)
            ];
            //发送预警
            // 检查死亡时间+24小时-当前之间是否大于0小于2小时
            $exitTimeEnd = strtotime($exitTime) + 24 * 3600;
            $diffTime = $exitTimeEnd - time();
            if ($diffTime > 0 && $diffTime < 2 * 3600) {
                $res = remainderTime($diffTime);
                $content = '请在' . $res . '内完成死亡记录';
                $msgYj = ['死亡时间【' . $exitTime . '】', '死亡记录【已创建，未签名】'];
                $this->caseService->sendMsg($ZYH, $content, 100, $msgYj);
            }
            return true;
        }

        //如果firstBlsyTime大于出院时间+24小时，则质控
        if (strtotime($firstBlsyTime) > strtotime($deathTime) + 24 * 3600) {
            $basis[] = "死亡记录首次签名时间【" . $firstBlsyTime . "（超24小时）】";
            //加入质控的病案首页的BLBH
            $basis["BLBH"] = $bl01Data[0]['BLBH'];
            $this->insertData[] = [
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title'],
                'basis' => json_encode([$basis], JSON_UNESCAPED_UNICODE)
            ];
            return true;
        }
        return true;
    }

    /**
     * 24小时内入院死亡记录
     * @param mixed $caseRule
     * @param mixed $ruleId
     * @param mixed $ZYH
     * @return bool
     * @author lch
     * @datetime 2025-05-07
     */
    public function rule1000($caseRule, $ruleId, $ZYH)
    {

        // 1. 获取出院时间和入院时间AAB01，如果没有取当前时间，用这个时间-入院时间如果小与24小时，则不质控
        $exitTime = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->value('AAC01');
        if (empty($exitTime)) {
            $exitTime = Carbon::now()->toDateTimeString();
        }

        // 2. 获取入院时间AAB01
        $enterTime = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->value('AAB01');
        if (empty($enterTime)) {
            return true;
        }

        // 3. 计算出院时间-入院时间
        $enterDate = date('Y-m-d', strtotime($enterTime));
        $exitDate = date('Y-m-d', strtotime($exitTime));
        $diffDays = (strtotime($exitDate) - strtotime($enterDate)) / (24 * 3600);
        if ($diffDays > 1) {
            return true;
        }

        //获取当前时间
        $currentDate = Carbon::now()->toDateTimeString();
        $exitTimeEnd = strtotime($exitTime) + 24 * 3600;
        //如果当前时间小于出院时间+24小时就跳过
        if (strtotime($currentDate) < $exitTimeEnd) {
            return true;
        }

        //查询有没有bllb=288的数据
        $swjl = EMR_BL_BL01::query()->where(['JZHM' => $ZYH])->where('BLLB', '288')->get()->toArray();
        if (!empty($swjl)) {
            return true;
        }

        //4. 查询医嘱
        $exitKeywords = RuleWordMap::getArrayById(8003);
        $yzbQuery = Yzb::query()->where('ZYH', $ZYH)->get()->toArray();
        $yzbData = [];
        foreach ($yzbQuery as $record) {
            foreach ($exitKeywords as $keyword) {
                if (strpos($record['YZMC'], $keyword) !== false) {
                    $yzbData[] = $record['YZMC'];
                    break;
                }
            }
        }

        // 如果没有死亡医嘱，则不进行质控
        if (empty($yzbData)) {
            return true;
        }

        //6. 查询死亡记录
        $ruleMap8007 = RuleWordMap::query()->where('id', '=', 8067)->value('keyword');
        $ruleMap8007 = !empty($ruleMap8007) ? $ruleMap8007 : '18,288';

        //判断是否包含逗号
        if (strpos($ruleMap8007, ',') !== false) {
            $exitKeywords = explode(',', $ruleMap8007);
        } else {
            $exitKeywords = [$ruleMap8007];
        }

        // 4. 查询 exitKeywords是数组
        $bl01Data = EMR_BL_BL01::query()->where(['JZHM' => $ZYH])->whereIn('BLLB', $exitKeywords)->get()->toArray();

        if (empty($bl01Data)) {
            return true;
        }

        $deathbl01 = $bl01Data;

        if (!empty($deathbl01)) {
            $deathbl01Data = $deathbl01[0];
            //获取hjnr
            $hjnr = EMR_BL_BLXG::query()->where('BLBH', $deathbl01Data['BLBH'])->get(['HJNR'])->toArray();
            $hjnr = $hjnr[0]['HJNR'] ?? '';
            if (!empty($hjnr)) {
                //从hjnr中提取死亡时间：{2025-03-26 03:16} 提取时间
                if (preg_match('/死亡时间：(?:\{)?(\d{4}-\d{2}-\d{2} \d{2}:\d{2})(?:\})?/', $hjnr, $matches)) {
                    $deathTime = $matches[1];
                    // 记录成功提取的时间（可选，排查后可移除）
                    //Log::debug('成功提取死亡时间', ['time' => $deathTime]);
                } else {
                    // 尝试其他可能的格式
                    if (preg_match('/死亡时间[:：].*?(\d{4}[-\/]\d{1,2}[-\/]\d{1,2}\s+\d{1,2}:\d{1,2})/', $hjnr, $matches)) {
                        $deathTime = $matches[1];
                        // 标准化日期格式
                        $deathTime = date('Y-m-d H:i', strtotime($deathTime));
                        //Log::debug('使用备用格式提取死亡时间', ['time' => $deathTime]);
                    } else {
                        $deathTime = '无';
                        Log::debug('无法提取死亡时间', ['hjnr_contains_keyword' => strpos($hjnr, '死亡时间') !== false]);
                    }
                }
            } else {
                $deathTime = '无';
            }
        } else {
            $deathTime = '无';
        }

        $basis = [];

        // 出院时间依据
        $basis[] = "死亡时间【" . $deathTime . "】";

        //取id8002的keyword
        $ruleMap8002 = RuleWordMap::query()->where('id', '=', 8002)->value('keyword');
        $ruleMap8002 = !empty($ruleMap8002) ? $ruleMap8002 : 'first_blsy_time';

        $firstBlsyTime = $bl01Data[0][$ruleMap8002];

        //如果是18查出的数据jlmc=24小时内入院死亡记录，如果是292查出的数据jlmc=入院记录
        $jlmc = '';
        if ($bl01Data[0]['BLLB'] == '18' || $bl01Data[0]['BLLB'] == '288') {
            $jlmc = '24小时内入院死亡记录';
        } else {
            $jlmc = '入院记录';
        }
        //如果为空设置basis
        if (empty($firstBlsyTime)) {
            $basis[] = "{$jlmc}首次签名时间【未签名】";
            //加入质控的病程的BLBH
            $basis["BLBH"] = $bl01Data[0]['BLBH'];
            $this->insertData[] = [
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title'],
                'basis' => json_encode([$basis], JSON_UNESCAPED_UNICODE)
            ];
            return true;
        }

        //如果firstBlsyTime大于出院时间+24小时，则质控
        if (strtotime($firstBlsyTime) > strtotime($exitTime) + 24 * 3600) {
            $basis[] = "{$jlmc}首次签名时间【" . $firstBlsyTime . "（超24小时）】";
            //加入质控的病案首页的BLBH
            $basis["BLBH"] = $bl01Data[0]['BLBH'];
            $this->insertData[] = [
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title'],
                'basis' => json_encode([$basis], JSON_UNESCAPED_UNICODE)
            ];
            return true;
        }
        return true;
    }

    public function rule97($caseRule, $ruleId, $ZYH)
    {

        // 1. 获取出院时间和入院时间AAB01，如果没有取当前时间，用这个时间-入院时间如果小与24小时，则不质控
        $exitTime = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->value('AAC01');
        if (empty($exitTime)) {
            $exitTime = Carbon::now()->toDateTimeString();
        }

        // 2. 获取入院时间AAB01
        $enterTime = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->value('AAB01');
        if (empty($enterTime)) {
            return true;
        }

        // 3. 计算出院时间-入院时间
        $enterDate = date('Y-m-d', strtotime($enterTime));
        $exitDate = date('Y-m-d', strtotime($exitTime));
        $diffDays = (strtotime($exitDate) - strtotime($enterDate)) / (24 * 3600);
        if ($diffDays > 1) {
            return true;
        }

        //获取当前时间
        $currentDate = Carbon::now()->toDateTimeString();
        $exitTimeEnd = strtotime($exitTime) + 24 * 3600;
        //如果当前时间小于出院时间+24小时就跳过
        if (strtotime($currentDate) < $exitTimeEnd) {
            return true;
        }

        //查询有没有bllb=1的数据
        $ryjl = EMR_BL_BL01::query()->where(['JZHM' => $ZYH])->where('BLLB', '1')->get()->toArray();
        if (!empty($ryjl)) {
            return true;
        }

        //查询bllb=294，zxsj倒序取第一条包含rulemapid = 8078（要求出院）看是否有数据
        $ruleMap8078 = RuleWordMap::query()->where('id', '=', 8078)->value('keyword');
        $ruleMap8078 = !empty($ruleMap8078) ? $ruleMap8078 : '要求出院';
        //判断是否包含逗号
        if (strpos($ruleMap8078, ',') !== false) {
            $exitKeywords = explode(',', $ruleMap8078);
        } else {
            $exitKeywords = [$ruleMap8078];
        }
        $ryjl = EMR_BL_BL01::query()->where(['JZHM' => $ZYH])->where('BLLB', '294')->orderBy('ZXSJ', 'desc')->first();
        if (!empty($ryjl)) {
            $hjnr = EMR_BL_BLXG::query()->where(['BLBH' => $ryjl['BLBH']])->value('HJNR');
            $isExit = false;
            foreach ($exitKeywords as $keyword) {
                if (strpos($hjnr, $keyword) !== false) {
                    $isExit = true;
                    break;
                }
            }
            if ($isExit) {
                //查询bl01是否有bllb=292或者18
                $ry292 = EMR_BL_BL01::query()->where(['JZHM' => $ZYH])->whereIn('BLLB', ['292', '18'])->get()->toArray();
                if (!empty($ry292)) {
                    return true;
                }
            }
        }

        //4. 查询医嘱
        $exitKeywords = RuleWordMap::getArrayById(8000);
        // 2. 查询医嘱是否包含出院/离院关键字
        $yzbQuery = Yzb::query()->where('ZYH', $ZYH)->get()->toArray();
        $yzbData = [];
        foreach ($yzbQuery as $item) {
            if (strpos($item['YZMC'], '死亡') !== false) {
                return false;
            }
            foreach ($exitKeywords as $keyword) {
                if (strpos($item['YZMC'], trim($keyword)) !== false) {
                    $yzbData[] = $item;
                }
            }
        }

        // 如果没有出院/离院医嘱，则不进行质控
        if (empty($yzbData)) {
            return true;
        }

        //6. 查询出院记录
        $ruleMap8051 = RuleWordMap::query()->where('id', '=', 8051)->value('keyword');
        $ruleMap8051 = !empty($ruleMap8051) ? $ruleMap8051 : '18,1';  // 病案首页BLLB

        //判断是否包含逗号
        if (strpos($ruleMap8051, ',') !== false) {
            $exitKeywords = explode(',', $ruleMap8051);
        } else {
            $exitKeywords = [$ruleMap8051];
        }
        $basis = [];
        // 出院时间依据
        $basis[] = "出院时间【" . $exitTime . "】";
        // 4. 查询病案首页数据，注意病案首页BLLB可能包含多个值
        $bl01Data = EMR_BL_BL01::query()->where('JZHM', $ZYH)->whereIn('BLLB', $exitKeywords)->get()->toArray();


        if (empty($bl01Data)) {
            $basis[] = "出院记录【未创建】";
            $this->insertData[] = [
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title'],
                'basis' => json_encode([$basis], JSON_UNESCAPED_UNICODE)
            ];
            //发送预警
            // 检查死亡时间+24小时-当前之间是否大于0小于2小时
            $exitTimeEnd = strtotime($exitTime) + 24 * 3600;
            $diffTime = $exitTimeEnd - time();
            if ($diffTime > 0 && $diffTime < 2 * 3600) {
                $res = remainderTime($diffTime);
                $content = '请在' . $res . '内完成出院记录';
                $msgYj = ['出院时间【' . $exitTime . '】', '出院记录【未创建】'];
                $this->caseService->sendMsg($ZYH, $content, 97, $msgYj);
            }
            return true;
        }

        $bl01_mc = $bl01Data[0]['BLMC'];
        //8023
        $ruleMap8023 = RuleWordMap::query()->where('id', '=', 8023)->value('keyword');
        $ruleMap8023 = !empty($ruleMap8023) ? $ruleMap8023 : '日间';
        //判断是否包含逗号
        if (strpos($ruleMap8023, ',') !== false) {
            $exitKeywords8023 = explode(',', $ruleMap8023);
        } else {
            $exitKeywords8023 = [$ruleMap8023];
        }
        //8024
        $ruleMap8024 = RuleWordMap::query()->where('id', '=', 8024)->value('keyword');
        $ruleMap8024 = !empty($ruleMap8024) ? $ruleMap8024 : '入出院记录,出入院记录';
        //判断是否包含逗号
        if (strpos($ruleMap8024, ',') !== false) {
            $exitKeywords8024 = explode(',', $ruleMap8024);
        } else {
            $exitKeywords8024 = [$ruleMap8024];
        }
        $isExclude = false;
        foreach ($exitKeywords8023 as $keyword) {
            if (strpos($bl01_mc, $keyword) !== false) {
                foreach ($exitKeywords8024 as $keyword8024) {
                    if (strpos($bl01_mc, $keyword8024) !== false) {
                        $isExclude = true;
                        break;
                    }
                }
            }
        }

        if ($isExclude) {
            return true;
        }


        //取id8002的keyword
        $ruleMap8002 = RuleWordMap::query()->where('id', '=', 8002)->value('keyword');
        $ruleMap8002 = !empty($ruleMap8002) ? $ruleMap8002 : 'frist_blsy_time';

        $firstBlsyTime = $bl01Data[0][$ruleMap8002];
        //如果为空设置basis
        if (empty($firstBlsyTime)) {
            $basis[] = "【" . $bl01_mc . "】";
            $basis[] = "首次签名时间【未签名】";
            $basis["BLBH"] = $bl01Data[0]['BLBH']; //加入被质控病程BLBH
            $this->insertData[] = [
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title'],
                'basis' => json_encode([$basis], JSON_UNESCAPED_UNICODE)
            ];
            //发送预警
            // 检查死亡时间+24小时-当前之间是否大于0小于2小时
            $exitTimeEnd = strtotime($exitTime) + 24 * 3600;
            $diffTime = $exitTimeEnd - time();
            if ($diffTime > 0 && $diffTime < 2 * 3600) {
                $res = remainderTime($diffTime);
                $content = '请在' . $res . '内完成出院记录';
                $msgYj = ['出院时间【' . $exitTime . '】', '出院记录【已创建，未签名】'];
                $this->caseService->sendMsg($ZYH, $content, 97, $msgYj);
            }
            return true;
        }

        //如果firstBlsyTime大于出院时间+24小时，则质控
        if (strtotime($firstBlsyTime) > strtotime($exitTime) + 24 * 3600) {
            $basis[] = "【" . $bl01_mc . "】";
            $basis[] = "首次签名时间【" . $firstBlsyTime . "（超24小时）】";
            $basis["BLBH"] = $bl01Data[0]['BLBH'];
            $this->insertData[] = [
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title'],
                'basis' => json_encode([$basis], JSON_UNESCAPED_UNICODE)
            ];
            return true;
        }
        return true;
    }

    /**
     * 患者入院后，8小时内未完成【首次病程记录】
     * @param $caseRule
     * @param $ruleId
     * @param $ZYH
     * @return true
     * @author lzh
     * @datetime 2025-05-08
     */
    public function rule101($caseRule, $ruleId, $ZYH)
    {
        $isError = 0;
        $bl18Data = EMR_BL_BL01::query()->where(['JZHM' => $ZYH])->where('BLLB', '=', 18)->get()->toArray();
        if (!empty($bl18Data)) {
            return true;
        }

        // 2. 获取入院时间AAB01
        $enterTime = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->value('AAB01');
        if (empty($enterTime)) {
            return true;
        }


        //当前时间-入院时间小于8小时，则不质控
        $diffTime = time() - strtotime($enterTime);
        if ($diffTime < 8 * 3600) {
            return true;
        }

        // 2. 获取患者科室
        $brks = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->value('BRKS');
        if (empty($brks)) {
            return true; // 没有科室信息
        }
        $basis = [];
        $basis[] = "入院时间【" . $enterTime . "】";

        // 3. 获取产科排除诊断列表
        $ruleMap8009 = RuleWordMap::query()->where('id', '=', 8009)->value('keyword');
        $ruleMap8009 = !empty($ruleMap8009) ? $ruleMap8009 : "妊娠期高血压,先兆子痫,前置胎盘,胎盘早期剥离,先兆早产,先兆流产,妊娠剧吐,稽留流产,妊娠期肝内胆汁淤积综合症,胎儿宫内窘迫,妊娠合并子宫瘢痕,臀先露,肩先露,子宫复旧不全,晚期产后出血,妊娠合并宫颈功能不全,胎儿生长发育迟缓,黑尔普综合症,胎心监护异常,早期难免流产,羊水过少";
        //判断是否包含逗号
        if (strpos($ruleMap8009, ',') !== false) {
            $excludeDiagnosis = explode(',', $ruleMap8009);
        } else {
            $excludeDiagnosis = [$ruleMap8009];
        }


        // 4. 判断是否为产科且需要质控
        $needCheck = true;
        //获取产科代码id8025
        $id8025 = RuleWordMap::query()->where('id', '=', 8025)->value('keyword');
        //是否包含逗号
        if (strpos($id8025, ',') !== false) {
            $id8025 = explode(',', $id8025);
        } else {
            $id8025 = [$id8025];
        }
        //是否包含产科代码
        if (in_array($brks, $id8025)) { // 假设产科的代码为"产科"，实际使用时请替换为正确的代码
            // 查询患者诊断,使用model
            $needCheck = false;
            $diagnoses = ZY_RYZD::query()->where('ZYH', '=', $ZYH)->where('ZDLB', '!=', '门（急）诊诊断')->pluck('JBMC')->toArray();

            // 检查是否有任何一个诊断在质控列表中,如果不在就不质控
            foreach ($diagnoses as $diagnosis) {
                if (in_array($diagnosis, $excludeDiagnosis)) {
                    //如果不在质控列表中，则不质控
                    $basis[] = "产科诊断名称【" . $diagnosis . "】";
                    $needCheck = true;
                    break;
                }
            }
        }

        // 如果不需要质控，直接返回
        if (!$needCheck) {
            return true;
        }

        // 5. 获取首次病程记录MBLB值
        $ruleMap8008 = RuleWordMap::query()->where('id', '=', 8008)->value('keyword');
        $ruleMap8008 = !empty($ruleMap8008) ? $ruleMap8008 : 295; // 首次病程记录的MBLB值
        //判断是否包含逗号
        if (strpos($ruleMap8008, ',') !== false) {
            $excludeDiagnosis = explode(',', $ruleMap8008);
        } else {
            $excludeDiagnosis = [$ruleMap8008];
        }

        // 6. 查询首次病程记录,查mysql
        $bl01Data = EMR_BL_BL01::query()->where('JZHM', $ZYH)->whereIn('MBLB', $excludeDiagnosis)->get()->toArray();

        // 如果没有找到首次病程记录
        if (empty($bl01Data[0])) {
            $basis[] = "入院后8小时内未完成“首次病程记录“";
            $this->insertData[] = [
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title'],
                'basis' => json_encode([$basis], JSON_UNESCAPED_UNICODE)
            ];
            //发送预警
            // 检查入院时间+8小时-当前之间是否大于0小于2小时
            $enterTimeEnd = strtotime($enterTime) + 8 * 3600;
            $diffTime = $enterTimeEnd - time();
            if ($diffTime > 0 && $diffTime < 2 * 3600) {
                $res = remainderTime($diffTime);
                $content = '请在' . $res . '内完成首次病程记录';
                $msgYj = ['入院时间【' . $enterTime . '】', '入院后8小时内未完成“首次病程记录“'];
                $this->caseService->sendMsg($ZYH, $content, 101, $msgYj);
            }
            return true;
        }

        $blsy = EMR_BL_BLSY::query()->where('BLBH', $bl01Data[0]['BLBH'])->get()->toArray();
        if (empty($blsy)) {
            $basis[] = $bl01Data[0]['BLMC'] . "【未签名】";
            $basis["BLBH"] = $bl01Data[0]['BLBH']; //加入被质控病程BLBH
            $this->insertData[] = [
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title'],
                'basis' => json_encode([$basis], JSON_UNESCAPED_UNICODE)
            ];
        }


        /* $ZXSJ = $bl01Data[0]['ZXSJ'];
        $enterTimeEnd = strtotime($enterTime) + 8 * 3600; */

        // 10. 检查签名时间是否在入院后8小时内
        // if (strtotime($ZXSJ) < strtotime($enterTime)) {
        //     // 签名时间早于入院时间，提前创建
        //     $basis[] = "首次病程记录标题时间【" . $ZXSJ . "（提前创建）】";
        //     $basis["BLBH"] = $bl01Data[0]['BLBH']; //加入被质控病程BLBH
        //     $this->insertData[] = [
        //         'JZHM' => $ZYH,
        //         'rule_id' => $ruleId,
        //         'code' => 'rule_' . $ruleId,
        //         'error_field' => $caseRule[$ruleId]['title'],
        //         'basis' => json_encode([$basis], JSON_UNESCAPED_UNICODE)
        //     ];
        // } else
        /* if (strtotime($ZXSJ) > $enterTimeEnd) {
            // 签名时间超过入院后8小时
            $basis[] = "首次病程记录标题时间【" . $ZXSJ . "（超8小时）】";
            $basis["BLBH"] = $bl01Data[0]['BLBH']; //加入被质控病程BLBH
            $this->insertData[] = [
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title'],
                'basis' => json_encode([$basis], JSON_UNESCAPED_UNICODE)
            ];
        } */

        return true;
    }

    /**
     * 【首次病程记录】首次签名时间超8小时
     * @param $caseRule
     * @param $ruleId
     * @param $ZYH
     * @return true
     * @author lzh
     * @datetime 2025-05-08
     */
    public function rule1276($caseRule, $ruleId, $ZYH)
    {

        $bl18Data = EMR_BL_BL01::query()->where(['JZHM' => $ZYH])->where('BLLB', '=', 18)->get()->toArray();
        if (!empty($bl18Data)) {
            return true;
        }

        // 2. 获取入院时间AAB01
        $enterTime = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->value('AAB01');
        if (empty($enterTime)) {
            return true;
        }

        //当前时间-入院时间小于8小时，则不质控
        $diffTime = time() - strtotime($enterTime);
        if ($diffTime < 8 * 3600) {
            return true;
        }


        // 2. 获取患者科室
        $brks = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->value('BRKS');
        if (empty($brks)) {
            return true; // 没有科室信息
        }
        $basis = [];
        $basis[] = "入院时间【" . $enterTime . "】";

        // 3. 获取产科排除诊断列表
        $ruleMap8009 = RuleWordMap::query()->where('id', '=', 8009)->value('keyword');
        $ruleMap8009 = !empty($ruleMap8009) ? $ruleMap8009 : "妊娠期高血压,先兆子痫,前置胎盘,胎盘早期剥离,先兆早产,先兆流产,妊娠剧吐,稽留流产,妊娠期肝内胆汁淤积综合症,胎儿宫内窘迫,妊娠合并子宫瘢痕,臀先露,肩先露,子宫复旧不全,晚期产后出血,妊娠合并宫颈功能不全,胎儿生长发育迟缓,黑尔普综合症,胎心监护异常,早期难免流产,羊水过少";
        //判断是否包含逗号
        if (strpos($ruleMap8009, ',') !== false) {
            $excludeDiagnosis = explode(',', $ruleMap8009);
        } else {
            $excludeDiagnosis = [$ruleMap8009];
        }


        // 4. 判断是否为产科且需要质控
        $needCheck = true;
        //获取产科代码id8025
        $id8025 = RuleWordMap::query()->where('id', '=', 8025)->value('keyword');
        //是否包含逗号
        if (strpos($id8025, ',') !== false) {
            $id8025 = explode(',', $id8025);
        } else {
            $id8025 = [$id8025];
        }
        //是否包含产科代码
        if (in_array($brks, $id8025)) { // 假设产科的代码为"产科"，实际使用时请替换为正确的代码
            // 查询患者诊断,使用model
            $needCheck = false;
            $diagnoses = ZY_RYZD::query()->where('ZYH', '=', $ZYH)->where('ZDLB', '!=', '门（急）诊诊断')->pluck('JBMC')->toArray();

            // 检查是否有任何一个诊断在质控列表中,如果不在就不质控
            foreach ($diagnoses as $diagnosis) {
                if (in_array($diagnosis, $excludeDiagnosis)) {
                    //如果不在质控列表中，则不质控
                    $basis[] = "产科诊断名称【" . $diagnosis . "】";
                    $needCheck = true;
                    break;
                }
            }
        }

        // 如果不需要质控，直接返回
        if (!$needCheck) {
            return true;
        }

        // 5. 获取首次病程记录MBLB值
        $ruleMap8008 = RuleWordMap::query()->where('id', '=', 8008)->value('keyword');
        $ruleMap8008 = !empty($ruleMap8008) ? $ruleMap8008 : 295; // 首次病程记录的MBLB值
        //判断是否包含逗号
        if (strpos($ruleMap8008, ',') !== false) {
            $excludeDiagnosis = explode(',', $ruleMap8008);
        } else {
            $excludeDiagnosis = [$ruleMap8008];
        }

        // 6. 查询首次病程记录,查mysql
        $bl01Data = EMR_BL_BL01::query()->where('JZHM', $ZYH)->whereIn('MBLB', $excludeDiagnosis)->get()->toArray();
        if (empty($bl01Data)) {
            return [];
        }

        // 获取首次签名时间字段名
        $firstBlsyTimeField = RuleWordMap::query()->where('id', '=', 8002)->value('keyword');
        $firstBlsyTimeField = !empty($firstBlsyTimeField) ? $firstBlsyTimeField : 'frist_blsy_time';
        $firstBlsyTime = $bl01Data[0][$firstBlsyTimeField];
        if (empty($firstBlsyTime)) {
            return [];
        }

        $enterTimeEnd = strtotime($enterTime) + 8 * 3600;
        if (strtotime($firstBlsyTime) > $enterTimeEnd) {
            // 签名时间超过入院后8小时
            $basis[] = "首次病程记录首次签名时间【" . $firstBlsyTime . "（超8小时）】";
            $basis["BLBH"] = $bl01Data[0]['BLBH']; //加入被质控病程BLBH
            $this->insertData[] = [
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title'],
                'basis' => json_encode([$basis], JSON_UNESCAPED_UNICODE)
            ];
        }

        return true;
    }

    /**
     * 【首次病程记录】未签名
     * @param $caseRule
     * @param $ruleId
     * @param $ZYH
     * @return true
     * @author lzh
     * @datetime 2025-05-08
     */
    public function rule1277($caseRule, $ruleId, $ZYH)
    {

        $bl18Data = EMR_BL_BL01::query()->where(['JZHM' => $ZYH])->where('BLLB', '=', 18)->get()->toArray();
        if (!empty($bl18Data)) {
            return true;
        }

        // 2. 获取入院时间AAB01
        $enterTime = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->value('AAB01');
        if (empty($enterTime)) {
            return true;
        }

        // 2. 获取患者科室
        $brks = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->value('BRKS');
        if (empty($brks)) {
            return true; // 没有科室信息
        }
        $basis = [];
        $basis[] = "入院时间【" . $enterTime . "】";

        // 3. 获取产科排除诊断列表
        $ruleMap8009 = RuleWordMap::query()->where('id', '=', 8009)->value('keyword');
        $ruleMap8009 = !empty($ruleMap8009) ? $ruleMap8009 : "妊娠期高血压,先兆子痫,前置胎盘,胎盘早期剥离,先兆早产,先兆流产,妊娠剧吐,稽留流产,妊娠期肝内胆汁淤积综合症,胎儿宫内窘迫,妊娠合并子宫瘢痕,臀先露,肩先露,子宫复旧不全,晚期产后出血,妊娠合并宫颈功能不全,胎儿生长发育迟缓,黑尔普综合症,胎心监护异常,早期难免流产,羊水过少";
        //判断是否包含逗号
        if (strpos($ruleMap8009, ',') !== false) {
            $excludeDiagnosis = explode(',', $ruleMap8009);
        } else {
            $excludeDiagnosis = [$ruleMap8009];
        }


        // 4. 判断是否为产科且需要质控
        $needCheck = true;
        //获取产科代码id8025
        $id8025 = RuleWordMap::query()->where('id', '=', 8025)->value('keyword');
        //是否包含逗号
        if (strpos($id8025, ',') !== false) {
            $id8025 = explode(',', $id8025);
        } else {
            $id8025 = [$id8025];
        }
        //是否包含产科代码
        if (in_array($brks, $id8025)) { // 假设产科的代码为"产科"，实际使用时请替换为正确的代码
            // 查询患者诊断,使用model
            $needCheck = false;
            $diagnoses = ZY_RYZD::query()->where('ZYH', '=', $ZYH)->where('ZDLB', '!=', '门（急）诊诊断')->pluck('JBMC')->toArray();

            // 检查是否有任何一个诊断在质控列表中,如果不在就不质控
            foreach ($diagnoses as $diagnosis) {
                if (in_array($diagnosis, $excludeDiagnosis)) {
                    //如果不在质控列表中，则不质控
                    $basis[] = "产科诊断名称【" . $diagnosis . "】";
                    $needCheck = true;
                    break;
                }
            }
        }

        // 如果不需要质控，直接返回
        if (!$needCheck) {
            return true;
        }


        // 5. 获取首次病程记录MBLB值
        $ruleMap8008 = RuleWordMap::query()->where('id', '=', 8008)->value('keyword');
        $ruleMap8008 = !empty($ruleMap8008) ? $ruleMap8008 : 295; // 首次病程记录的MBLB值
        //判断是否包含逗号
        if (strpos($ruleMap8008, ',') !== false) {
            $excludeDiagnosis = explode(',', $ruleMap8008);
        } else {
            $excludeDiagnosis = [$ruleMap8008];
        }

        // 6. 查询首次病程记录,查mysql
        $bl01Data = EMR_BL_BL01::query()->where('JZHM', $ZYH)->whereIn('MBLB', $excludeDiagnosis)->get()->toArray();
        if (empty($bl01Data)) {
            return [];
        }

        $blsy = EMR_BL_BLSY::query()->where('BLBH', $bl01Data[0]['BLBH'])->get()->toArray();
        if (empty($blsy)) {
            // 签名时间超过入院后8小时
            $basis[] = "首次病程【未签名】";
            $basis["BLBH"] = $bl01Data[0]['BLBH']; //加入被质控病程BLBH
            $this->insertData[] = [
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title'],
                'basis' => json_encode([$basis], JSON_UNESCAPED_UNICODE)
            ];
        }

        return true;
    }

    //新建rule114，患者入院后，48小时内未完成【上级医师首次查房记录】
    public function rule114($caseRule, $ruleId, $ZYH, $isYj = 0)
    {
        // 1. 获取出院时间和入院时间AAB01，如果没有取当前时间，用这个时间-入院时间如果小与24小时，则不质控
        $exitTime = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->value('AAC01');
        if (empty($exitTime)) {
            $exitTime = Carbon::now()->toDateTimeString();
        }

        // 2. 获取入院时间AAB01
        $enterTime = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->value('AAB01');
        if (empty($enterTime)) {
            return true;
        }

        if (strtotime($exitTime) - strtotime($enterTime) < 48 * 3600) {
            return true;
        }

        // 3. 计算出院时间-入院时间
        $enterDate = date('Y-m-d', strtotime($enterTime));
        $exitDate = date('Y-m-d', strtotime($exitTime));
        $diffDays = (strtotime($exitDate) - strtotime($enterDate)) / (24 * 3600);
        if ($diffDays <= 1) {
            return true;
        }

        //获取当前时间
        $currentDate = Carbon::now()->toDateTimeString();
        $enterTimeEnd = strtotime($enterTime) + 48 * 3600;
        //如果当前时间小于出入时间+48小时就跳过
        if (strtotime($currentDate) < $enterTimeEnd && $isYj == 0) {
            return true;
        }

        // 2. 获取患者科室
        $brks = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->value('BRKS');
        if (empty($brks)) {
            return true; // 没有科室信息
        }

        // 3. 获取产科排除诊断列表
        $ruleMap8009 = RuleWordMap::query()->where('id', '=', 8009)->value('keyword');
        $ruleMap8009 = !empty($ruleMap8009) ? $ruleMap8009 : "妊娠期高血压,先兆子痫,前置胎盘,胎盘早期剥离,先兆早产,先兆流产,妊娠剧吐,稽留流产,妊娠期肝内胆汁淤积综合症,胎儿宫内窘迫,妊娠合并子宫瘢痕,臀先露,肩先露,子宫复旧不全,晚期产后出血,妊娠合并宫颈功能不全,胎儿生长发育迟缓,黑尔普综合症,胎心监护异常,早期难免流产,羊水过少";
        //判断是否包含逗号
        if (strpos($ruleMap8009, ',') !== false) {
            $excludeDiagnosis = explode(',', $ruleMap8009);
        } else {
            $excludeDiagnosis = [$ruleMap8009];
        }

        // 4. 判断是否为产科且需要质控
        $needCheck = true;
        //获取产科代码id8025
        $id8025 = RuleWordMap::query()->where('id', '=', 8025)->value('keyword');
        //是否包含逗号
        if (strpos($id8025, ',') !== false) {
            $id8025 = explode(',', $id8025);
        } else {
            $id8025 = [$id8025];
        }
        //是否包含产科代码
        if (in_array($brks, $id8025)) { // 假设产科的代码为"产科"，实际使用时请替换为正确的代码
            // 查询患者诊断,使用model
            $needCheck = false;
            $diagnoses = ZY_RYZD::query()->where('ZYH', '=', $ZYH)->pluck('JBMC')->toArray();

            // 检查是否有任何一个诊断在质控列表中,如果不在就不质控
            foreach ($diagnoses as $diagnosis) {
                if (in_array($diagnosis, $excludeDiagnosis)) {
                    //如果不在质控列表中，则不质控
                    $needCheck = true;
                    break;
                }
            }
        }

        // 如果不需要质控，直接返回
        if (!$needCheck) {
            return true;
        }

        //获取除了首次病程记录id8008外的其他病程记录id8010
        $excludeDiagnosis = RuleWordMap::getArrayById(8010);
        //排除首次病程记录id8008
        $excludeDiagnosis1 = RuleWordMap::getArrayById(8008);
        //入院时间+48小时
        $enterTimeEnd = strtotime($enterTime) + 48 * 3600;

        //获取除了首次病程记录id8008外的其他病程记录id8010，加入条件ZXSJ（id8011）再入院时间到入院时间+48小时之间
        $ruleMap8011 = RuleWordMap::query()->where('id', '=', 8011)->value('keyword');
        $ruleMap8011 = !empty($ruleMap8011) ? $ruleMap8011 : 'ZXSJ'; // 上级医师查房记录的ZXSJ字段名


        //转换结束时间成YYYY-MM-DD HH:MM:SS
        $enterTimeEnd = date('Y-m-d H:i:s', $enterTimeEnd);

        $bl01Data = EMR_BL_BL01::query()->where('JZHM', $ZYH)->whereIn('BLLB', $excludeDiagnosis)->whereNotIn('MBLB', $excludeDiagnosis1)->where($ruleMap8011, '<=', $enterTimeEnd)->get()->toArray();

        $basis = [];
        //入院时间依据
        $basis[] = "入院时间【" . $enterTime . "】";

        if (empty($bl01Data)) {
            $basis[] = "上级医师查房记录【无】-1";
            $this->insertData[] = [
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title'],
                'basis' => json_encode([$basis], JSON_UNESCAPED_UNICODE)
            ];
            //发送预警
            // 检查入院时间+48小时-当前之间是否大于0小于2小时
            $enterTimeEnd = strtotime($enterTime) + 48 * 3600;
            $diffTime = $enterTimeEnd - time();
            if ($diffTime > 0 && $diffTime < 2 * 3600) {
                $res = remainderTime($diffTime);
                $content = '请在' . $res . '内完成上级医师查房记录';
                $msgYj = ['入院时间【' . $enterTime . '】', '上级医师查房记录【无】'];
                $this->caseService->sendMsg($ZYH, $content, 114, $msgYj);
            }
            return true;
        }
        //获取首次签名时间字段名
        $firstBlsyTimeField = RuleWordMap::query()->where('id', '=', 8002)->value('keyword');
        $firstBlsyTimeField = !empty($firstBlsyTimeField) ? $firstBlsyTimeField : 'frist_blsy_time';

        //是否有主任
        $isMatched = false;
        $excludeDiagnosis = RuleWordMap::getArrayById(8012);
        foreach ($bl01Data as $value) {
            foreach ($excludeDiagnosis as $title) {
                if (strpos($value['BLMC'], $title) !== false) {
                    $isMatched = true;
                    break 2;
                }
            }
            if (!$isMatched) {
                //取blbh查询blsy获取blsy的医生工号SYYS，按照syys分组
                $blsy = EMR_BL_BLSY::query()->where('BLBH', $value['BLBH'])->where('FG_ACTIVE', 1)->pluck('SYYS')->toArray();
                //去重
                $blsy = array_unique($blsy);
                //循环查询blsy的医生工号SYYS，去staff表查询医生职称是否包含'主任'或'主治'
                foreach ($blsy as $blsyValue) {
                    $staff = STAFF::query()->where('code', $blsyValue)->value('ygjb_text');
                    //判断医生职称是否包含'主任'或'主治'
                    foreach ($excludeDiagnosis as $title) {
                        if (strpos($staff, $title) !== false) {
                            $isMatched = true;
                            break;
                        }
                    }
                }
            }
            if ($isMatched) {
                break;
            }
        }
        if (!$isMatched) {
            $basis[] = "上级医师查房记录【无】";
            $this->insertData[] = [
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title'],
                'basis' => json_encode([$basis], JSON_UNESCAPED_UNICODE)
            ];
            //发送预警
            // 检查入院时间+48小时-当前之间是否大于0小于2小时
            $enterTimeEnd = strtotime($enterTime) + 48 * 3600;
            $diffTime = $enterTimeEnd - time();
            if ($diffTime > 0 && $diffTime < 2 * 3600) {
                $res = remainderTime($diffTime);
                $content = '请在' . $res . '内完成上级医师查房记录';
                $msgYj = ['入院时间【' . $enterTime . '】', '上级医师查房记录【无】'];
                $this->caseService->sendMsg($ZYH, $content, 114, $msgYj);
            }
            return true;
        }
        return true;
    }

    //新建rule1047，患者入院后，48小时内未完成【上级医师首次查房记录】(查blmc)
    public function rule1047($caseRule, $ruleId, $ZYH)
    {


        // 1. 获取出院时间和入院时间AAB01，如果没有取当前时间，用这个时间-入院时间如果小与24小时，则不质控
        $exitTime = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->value('AAC01');
        if (empty($exitTime)) {
            $exitTime = Carbon::now()->toDateTimeString();
        }

        // 2. 获取入院时间AAB01
        $enterTime = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->value('AAB01');
        if (empty($enterTime)) {
            return true;
        }

        if (strtotime($exitTime) - strtotime($enterTime) < 48 * 3600) {
            return true;
        }

        // 3. 计算出院时间-入院时间
        $enterDate = date('Y-m-d', strtotime($enterTime));
        $exitDate = date('Y-m-d', strtotime($exitTime));
        $diffDays = (strtotime($exitDate) - strtotime($enterDate)) / (24 * 3600);
        if ($diffDays <= 1) {
            return true;
        }

        // 2. 获取患者科室
        $brks = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->value('BRKS');
        if (empty($brks)) {
            return true; // 没有科室信息
        }

        // 3. 获取产科排除诊断列表
        $ruleMap8009 = RuleWordMap::query()->where('id', '=', 8009)->value('keyword');
        $ruleMap8009 = !empty($ruleMap8009) ? $ruleMap8009 : "妊娠期高血压,先兆子痫,前置胎盘,胎盘早期剥离,先兆早产,先兆流产,妊娠剧吐,稽留流产,妊娠期肝内胆汁淤积综合症,胎儿宫内窘迫,妊娠合并子宫瘢痕,臀先露,肩先露,子宫复旧不全,晚期产后出血,妊娠合并宫颈功能不全,胎儿生长发育迟缓,黑尔普综合症,胎心监护异常,早期难免流产,羊水过少";
        //判断是否包含逗号
        if (strpos($ruleMap8009, ',') !== false) {
            $excludeDiagnosis = explode(',', $ruleMap8009);
        } else {
            $excludeDiagnosis = [$ruleMap8009];
        }

        // 4. 判断是否为产科且需要质控
        $needCheck = true;
        //获取产科代码id8025
        $id8025 = RuleWordMap::query()->where('id', '=', 8025)->value('keyword');
        //是否包含逗号
        if (strpos($id8025, ',') !== false) {
            $id8025 = explode(',', $id8025);
        } else {
            $id8025 = [$id8025];
        }
        //是否包含产科代码
        if (in_array($brks, $id8025)) { // 假设产科的代码为"产科"，实际使用时请替换为正确的代码
            // 查询患者诊断,使用model
            $needCheck = false;
            $diagnoses = ZY_RYZD::query()->where('ZYH', '=', $ZYH)->pluck('JBMC')->toArray();

            // 检查是否有任何一个诊断在质控列表中,如果不在就不质控
            foreach ($diagnoses as $diagnosis) {
                if (in_array($diagnosis, $excludeDiagnosis)) {
                    //如果不在质控列表中，则不质控
                    $needCheck = true;
                    break;
                }
            }
        }

        // 如果不需要质控，直接返回
        if (!$needCheck) {
            return true;
        }

        //获取除了首次病程记录id8008外的其他病程记录id8010

        $ruleMap8010 = RuleWordMap::query()->where('id', '=', 8010)->value('keyword');
        $ruleMap8010 = !empty($ruleMap8010) ? $ruleMap8010 : 294; // 上级医师首次查房记录的BLLB值
        //判断是否包含逗号
        if (strpos($ruleMap8010, ',') !== false) {
            $excludeDiagnosis = explode(',', $ruleMap8010);
        } else {
            $excludeDiagnosis = [$ruleMap8010];
        }
        //排除首次病程记录id8008
        $ruleMap8008 = RuleWordMap::query()->where('id', '=', 8008)->value('keyword');
        $ruleMap8008 = !empty($ruleMap8008) ? $ruleMap8008 : 295; // 首次病程记录的MBLB值
        //判断是否包含逗号
        if (strpos($ruleMap8008, ',') !== false) {
            $excludeDiagnosis1 = explode(',', $ruleMap8008);
        } else {
            $excludeDiagnosis1 = [$ruleMap8008];
        }
        //入院时间+48小时
        $enterTimeEnd = strtotime($enterTime) + 48 * 3600;

        //获取除了首次病程记录id8008外的其他病程记录id8010，加入条件ZXSJ（id8011）再入院时间到入院时间+48小时之间
        $ruleMap8011 = RuleWordMap::query()->where('id', '=', 8011)->value('keyword');
        $ruleMap8011 = !empty($ruleMap8011) ? $ruleMap8011 : 'ZXSJ'; // 上级医师查房记录的ZXSJ字段名


        //转换结束时间成YYYY-MM-DD HH:MM:SS
        $enterTimeEnd = date('Y-m-d H:i:s', $enterTimeEnd);

        $bl01Data = EMR_BL_BL01::query()->where('JZHM', $ZYH)->whereIn('BLLB', $excludeDiagnosis)->whereNotIn('MBLB', $excludeDiagnosis1)->where($ruleMap8011, '<=', $enterTimeEnd)->get()->toArray();

        $basis = [];
        //入院时间依据
        $basis[] = "入院时间【" . $enterTime . "】";

        if (empty($bl01Data)) {
            $basis[] = "上级医师查房记录【无】";
            $this->insertData[] = [
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title'],
                'basis' => json_encode([$basis], JSON_UNESCAPED_UNICODE)
            ];
            //发送预警
            // 检查入院时间+48小时-当前之间是否大于0小于2小时
            $enterTimeEnd = strtotime($enterTime) + 48 * 3600;
            $diffTime = $enterTimeEnd - time();
            if ($diffTime > 0 && $diffTime < 2 * 3600) {
                $res = remainderTime($diffTime);
                $content = '请在' . $res . '内完成上级医师查房记录';
                $msgYj = ['入院时间【' . $enterTime . '】', '上级医师查房记录【无】'];
                $this->caseService->sendMsg($ZYH, $content, 114, $msgYj);
            }
            return true;
        }
        //获取首次签名时间字段名
        $firstBlsyTimeField = RuleWordMap::query()->where('id', '=', 8002)->value('keyword');
        $firstBlsyTimeField = !empty($firstBlsyTimeField) ? $firstBlsyTimeField : 'frist_blsy_time';

        //是否有主任
        $isMatched = false;
        $insertData = [];
        $excludeDiagnosis = RuleWordMap::getArrayById(8012);
        foreach ($bl01Data as $value) {

            $blmc = $value['BLMC'];

            //判断医生职称是否包含'主任'或'主治'
            foreach ($excludeDiagnosis as $title) {
                if (strpos($blmc, $title) !== false) {
                    $isMatched = true;
                    break;
                }
            }

            $blsy = EMR_BL_BLSY::query()->where('BLBH', $value['BLBH'])->where('FG_ACTIVE', 1)->pluck('SYYS')->toArray();
            $blsy = array_unique($blsy);
            foreach ($blsy as $doctor) {
                $staffInfo = STAFF::query()->where('code', $doctor)->first();
                if (!$staffInfo) continue;
                $staff = $staffInfo->ygjb_text;
                foreach ($excludeDiagnosis as $title) {
                    if (strpos($staff, $title) !== false) {
                        $isMatched = true;
                        break;
                    }
                }
            }
        }
        if (!$isMatched) {

            $basis[] = "上级医师查房记录【无】";
            $this->insertData[] = [
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title'],
                'basis' => json_encode([$basis], JSON_UNESCAPED_UNICODE)
            ];
            //发送预警
            // 检查入院时间+48小时-当前之间是否大于0小于2小时
            $enterTimeEnd = strtotime($enterTime) + 48 * 3600;
            $diffTime = $enterTimeEnd - time();
            if ($diffTime > 0 && $diffTime < 2 * 3600) {
                $res = remainderTime($diffTime);
                $content = '请在' . $res . '内完成上级医师查房记录';
                $msgYj = ['入院时间【' . $enterTime . '】', '上级医师查房记录【无】'];
                $this->caseService->sendMsg($ZYH, $content, 114, $msgYj);
            }
        }
        return true;
    }

    /*
     * 住院期间副高及以上医师每周查房不少于2次
     * @author lzh
     * @param $caseRule
     * @param $ruleId
     * @param $ZYH
     * @return bool
     */
    public function rule113($caseRule, $ruleId, $ZYH)
    {

        // 1. 获取入院时间和出院时间
        $enterTime = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->value('AAB01');
        $dischargeTime = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->value('AAC01');

        // 如果没有出院时间或出院时间为默认值，使用当前时间
        if (empty($dischargeTime) || $dischargeTime == '1970-01-01 00:00:00' || $dischargeTime == '0000-00-00 00:00:00') {
            $dischargeTime = date('Y-m-d H:i:s');
        }

        // 检查入院时间是否有效
        if (empty($enterTime) || $enterTime == '1970-01-01 00:00:00' || $enterTime == '0000-00-00 00:00:00') {
            return true; // 没有有效的入院时间
        }

        // 2. 计算住院天数
        $enterDate = date('Y-m-d', strtotime($enterTime));
        $enterTimestamp = strtotime($enterDate);
        $dischargeTimestamp = strtotime($dischargeTime);
        $daysDiff = ceil(($dischargeTimestamp - $enterTimestamp) / (24 * 3600));

        // 如果住院天数不足7天，不进行质控
        if ($daysDiff < 7) {
            return true;
        }

        // 3. 计算完整周期数量（每7天为一个周期）
        $cycleCount = floor($daysDiff / 7);
        $cycleDays = 7; // 固定周期为7天

        // 4. 获取病程记录类型
        $ruleMap8010 = RuleWordMap::query()->where('id', '=', 8010)->value('keyword');
        $ruleMap8010 = !empty($ruleMap8010) ? $ruleMap8010 : 294; // 病程记录类型BLLB值

        // 判断是否包含逗号
        if (strpos($ruleMap8010, ',') !== false) {
            $blTypes = explode(',', $ruleMap8010);
        } else {
            $blTypes = [$ruleMap8010];
        }

        // 5. 获取查房记录的时间字段
        $ruleMap8011 = RuleWordMap::query()->where('id', '=', 8011)->value('keyword');
        $ruleMap8011 = !empty($ruleMap8011) ? $ruleMap8011 : 'ZXSJ'; // 查房记录的时间字段名

        // 6. 获取副高或正高级职称（主任/副主任医师）
        $ruleMap8013 = RuleWordMap::query()->where('id', '=', 8013)->value('keyword');
        $ruleMap8013 = !empty($ruleMap8013) ? $ruleMap8013 : '主任,副主任'; // 职称列表

        // 判断是否包含逗号
        if (strpos($ruleMap8013, ',') !== false) {
            $highLevelTitles = explode(',', $ruleMap8013);
        } else {
            $highLevelTitles = [$ruleMap8013];
        }

        // 7. 获取首次签名时间字段名
        $firstBlsyTimeField = RuleWordMap::query()->where('id', '=', 8002)->value('keyword');
        $firstBlsyTimeField = !empty($firstBlsyTimeField) ? $firstBlsyTimeField : 'first_blsy_time';

        // 8. 获取所有查房记录
        try {
            $bl01Data = EMR_BL_BL01::query()
                ->where('JZHM', $ZYH)
                ->whereIn('BLLB', $blTypes)
                ->where($ruleMap8011, '>=', $enterTime)
                ->where($ruleMap8011, '<=', $dischargeTime)
                ->get()
                ->toArray();
        } catch (\Exception $e) {
            Log::error("rule-$ruleId-$ZYH-error: " . $e->getMessage());
            return true; // 查询出错，跳过质控
        }

        // 收集所有质控结果
        $allBasisGroups = [];

        // 如果没有任何查房记录
        if (empty($bl01Data)) {
            // 没有查房记录，记录质控结果
            $basis = [];
            $basis[] = "住院周期【" . $enterDate . " 至 " . date('Y-m-d', strtotime($enterDate) + 6 * 24 * 3600) . "（0次）】";

            $allBasisGroups[] = $basis;
        } else {
            // 9. 按周期检查查房记录
            for ($i = 0; $i < $cycleCount; $i++) {
                // 周期开始日期（当天00:00:00）
                $cycleStartDate = date('Y-m-d', $enterTimestamp + $i * $cycleDays * 24 * 3600);
                $cycleStartTime = $cycleStartDate . ' 00:00:00';

                // 周期结束日期（第七天的23:59:59）
                $cycleEndDate = date('Y-m-d', $enterTimestamp + ($i * $cycleDays + 6) * 24 * 3600);
                $cycleEndTime = $cycleEndDate . ' 23:59:59';

                // 查找当前周期内的查房记录
                $validRoundRecords = [];
                $invalidRecords = [];

                foreach ($bl01Data as $record) {
                    $recordTime = $record[$ruleMap8011];
                    // 只检查在当前周期内的记录
                    if (
                        strtotime($recordTime) >= strtotime($cycleStartTime) &&
                        strtotime($recordTime) <= strtotime($cycleEndTime)
                    ) {
                        // 检查医生职称
                        try {
                            $blsy = EMR_BL_BLSY::query()->where('BLBH', $record['BLBH'])->where('FG_ACTIVE', 1)->pluck('SYYS')->toArray();
                            $blsy = array_unique($blsy);
                        } catch (\Exception $e) {
                            Log::error("rule-$ruleId-$ZYH-error: " . $e->getMessage());
                            continue; // 查询出错时跳过当前记录
                        }

                        $highLevelDoctors = [];

                        foreach ($blsy as $doctor) {
                            try {
                                $staffInfo = STAFF::query()->where('code', $doctor)->first();
                                if (!$staffInfo) continue;

                                $staff = $staffInfo->ygjb_text;
                                $doctorName = $staffInfo->name;

                                // 检查是否是副高或正高级职称
                                foreach ($highLevelTitles as $title) {
                                    if (strpos($staff, $title) !== false) {
                                        $highLevelDoctors[] = $doctorName . '（' . $staff . '）';
                                        break;
                                    }
                                }
                            } catch (\Exception $e) {
                                Log::error("rule-$ruleId-$ZYH-error: " . $e->getMessage());
                                continue; // 查询出错时跳过当前医生
                            }
                        }

                        // 如果有高级职称医师
                        if (!empty($highLevelDoctors)) {
                            // 检查是否有签名时间
                            $firstBlsyTime = isset($record[$firstBlsyTimeField]) ? $record[$firstBlsyTimeField] : '';

                            // 添加医生信息
                            $record['doctor_info'] = $highLevelDoctors;

                            if (empty($firstBlsyTime)) {
                                // 没有签名
                                $record['sign_status'] = '未签名';
                                $invalidRecords[] = $record;
                            } else {
                                // 检查签名时间是否在周期内
                                if (
                                    strtotime($firstBlsyTime) >= strtotime($cycleStartTime) &&
                                    strtotime($firstBlsyTime) <= strtotime($cycleEndTime)
                                ) {
                                    // 有效记录
                                    $record['sign_status'] = '正常';
                                    $validRoundRecords[] = $record;
                                } else {
                                    // 签名时间不在周期内
                                    $record['sign_status'] = '超时';
                                    $invalidRecords[] = $record;
                                }
                            }
                        }
                    }
                }

                // 检查当前周期内的有效查房次数
                if (count($validRoundRecords) < 2) {
                    $basis = [];
                    $basis[] = "住院周期【" . $cycleStartDate . " 至 " . $cycleEndDate . "（" . count($validRoundRecords) . "次）】";

                    // 添加医师信息
                    if (!empty($invalidRecords)) {
                        foreach ($invalidRecords as $record) {
                            $doctorInfo = implode("，", $record['doctor_info']);
                            $signInfo = "";

                            if ($record['sign_status'] == '未签名') {
                                $signInfo = "未签名";
                            } else {
                                $signInfo = $record[$firstBlsyTimeField] . "（超时）";
                            }

                            $basis[] = "【" . $record['BLMC'] . "】【" . $doctorInfo . "】首次签名时间【" . $signInfo . "】";
                        }

                        // 添加BLBH（如果有）
                        if (!empty($invalidRecords)) {
                            $basis["BLBH"] = $invalidRecords[0]['BLBH'];
                        }
                    } else if (!empty($validRoundRecords)) {
                        // 只有一次有效记录，但不够两次
                        foreach ($validRoundRecords as $record) {
                            $doctorInfo = implode("，", $record['doctor_info']);
                            $basis[] = "【" . $record['BLMC'] . "】【" . $doctorInfo . "】首次签名时间【" . $record[$firstBlsyTimeField] . "】";
                        }

                        // 添加BLBH（如果有）
                        if (!empty($validRoundRecords)) {
                            $basis["BLBH"] = $validRoundRecords[0]['BLBH'];
                        }
                    } else {
                        // 当前周期没有任何查房记录
                    }

                    // 收集当前周期的质控依据
                    $allBasisGroups[] = $basis;
                }
            }
        }

        // 10. 将所有质控结果一次性插入到insertData
        if (!empty($allBasisGroups)) {
            $this->insertData[] = [
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title'],
                'basis' => json_encode($allBasisGroups, JSON_UNESCAPED_UNICODE) // 将整个组作为一个数组传入
            ];
        }

        return true;
    }

    /*
     * 住院期间副高及以上医师每周查房不少于2次 新（查blmc）
     * @author lzh
     * @param $caseRule
     * @param $ruleId
     * @param $ZYH
     * @return bool
     */
    public function rule1048($caseRule, $ruleId, $ZYH)
    {

        // 1. 获取入院时间和出院时间
        $enterTime = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->value('AAB01');
        $dischargeTime = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->value('AAC01');

        // 如果没有出院时间或出院时间为默认值，使用当前时间
        if (empty($dischargeTime) || $dischargeTime == '1970-01-01 00:00:00' || $dischargeTime == '0000-00-00 00:00:00') {
            $dischargeTime = date('Y-m-d H:i:s');
        }

        // 检查入院时间是否有效
        if (empty($enterTime) || $enterTime == '1970-01-01 00:00:00' || $enterTime == '0000-00-00 00:00:00') {
            return true; // 没有有效的入院时间
        }

        // 2. 计算住院天数
        $enterDate = date('Y-m-d', strtotime($enterTime));
        $enterTimestamp = strtotime($enterDate);
        $dischargeTimestamp = strtotime($dischargeTime);
        $daysDiff = ceil(($dischargeTimestamp - $enterTimestamp) / (24 * 3600)) - 1; //出院当天排除-1

        // 如果住院天数不足7天，不进行质控
        if ($daysDiff < 7) {
            return true;
        }

        // 3. 计算完整周期数量（每7天为一个周期）
        $cycleCount = floor($daysDiff / 7);
        $cycleDays = 7; // 固定周期为7天

        // 4. 获取病程记录类型
        $ruleMap8010 = RuleWordMap::query()->where('id', '=', 8010)->value('keyword');
        $ruleMap8010 = !empty($ruleMap8010) ? $ruleMap8010 : 294; // 病程记录类型BLLB值

        // 判断是否包含逗号
        if (strpos($ruleMap8010, ',') !== false) {
            $blTypes = explode(',', $ruleMap8010);
        } else {
            $blTypes = [$ruleMap8010];
        }

        // 5. 获取查房记录的时间字段
        $ruleMap8011 = RuleWordMap::query()->where('id', '=', 8011)->value('keyword');
        $ruleMap8011 = !empty($ruleMap8011) ? $ruleMap8011 : 'ZXSJ'; // 查房记录的时间字段名

        // 6. 获取副高或正高级职称（主任/副主任医师）
        $ruleMap8013 = RuleWordMap::query()->where('id', '=', 8013)->value('keyword');
        $ruleMap8013 = !empty($ruleMap8013) ? $ruleMap8013 : '主任,副主任'; // 职称列表

        // 判断是否包含逗号
        if (strpos($ruleMap8013, ',') !== false) {
            $highLevelTitles = explode(',', $ruleMap8013);
        } else {
            $highLevelTitles = [$ruleMap8013];
        }

        // 7. 获取首次签名时间字段名
        $firstBlsyTimeField = RuleWordMap::query()->where('id', '=', 8002)->value('keyword');
        $firstBlsyTimeField = !empty($firstBlsyTimeField) ? $firstBlsyTimeField : 'first_blsy_time';

        // 8. 获取所有查房记录
        try {
            $bl01Data = EMR_BL_BL01::query()
                ->where('JZHM', $ZYH)
                ->whereIn('BLLB', $blTypes)
                ->where($ruleMap8011, '>=', $enterTime)
                ->where($ruleMap8011, '<=', $dischargeTime)
                ->get()
                ->toArray();
        } catch (\Exception $e) {
            Log::error("rule-$ruleId-$ZYH-error: " . $e->getMessage());
            return true; // 查询出错，跳过质控
        }

        // 收集所有质控结果
        $allBasisGroups = [];

        // 如果没有任何查房记录
        if (empty($bl01Data)) {
            // 没有查房记录，记录质控结果
            $basis = [];
            $basis[] = "住院周期【" . $enterDate . " 至 " . date('Y-m-d', strtotime($enterDate) + 6 * 24 * 3600) . "（0次）】";

            $allBasisGroups[] = $basis;
        } else {
            // 9. 按周期检查查房记录
            for ($i = 0; $i < $cycleCount; $i++) {
                // 周期开始日期（当天00:00:00）
                $cycleStartDate = date('Y-m-d', $enterTimestamp + $i * $cycleDays * 24 * 3600);
                $cycleStartTime = $cycleStartDate . ' 00:00:00';

                // 周期结束日期（第七天的23:59:59）
                $cycleEndDate = date('Y-m-d', $enterTimestamp + ($i * $cycleDays + 6) * 24 * 3600);
                $cycleEndTime = $cycleEndDate . ' 23:59:59';

                // 查找当前周期内的查房记录
                $validRoundRecords = [];
                $invalidRecords = [];

                foreach ($bl01Data as $record) {
                    $recordTime = $record[$ruleMap8011];
                    // 只检查在当前周期内的记录
                    if (
                        strtotime($recordTime) >= strtotime($cycleStartTime) &&
                        strtotime($recordTime) <= strtotime($cycleEndTime)
                    ) {

                        $blmc = $record['BLMC'];
                        $highLevelDoctors = [];


                        // 检查是否是副高或正高级职称
                        foreach ($highLevelTitles as $title) {
                            if (strpos($blmc, $title) !== false) {
                                $highLevelDoctors[] = $title;
                                break;
                            }
                        }


                        // 如果有高级职称医师
                        if (!empty($highLevelDoctors)) {
                            // 检查是否有签名时间
                            $firstBlsyTime = isset($record[$firstBlsyTimeField]) ? $record[$firstBlsyTimeField] : '';

                            // 添加医生信息
                            $record['doctor_info'] = $highLevelDoctors;

                            if (empty($firstBlsyTime)) {
                                // 没有签名
                                $record['sign_status'] = '未签名';
                                $invalidRecords[] = $record;
                            } else {
                                // 检查签名时间是否在周期内
                                if (
                                    strtotime($firstBlsyTime) >= strtotime($cycleStartTime) &&
                                    strtotime($firstBlsyTime) <= strtotime($cycleEndTime)
                                ) {
                                    // 有效记录
                                    $record['sign_status'] = '正常';
                                    $validRoundRecords[] = $record;
                                } else {
                                    // 签名时间不在周期内
                                    $record['sign_status'] = '超时';
                                    $invalidRecords[] = $record;
                                }
                            }
                        }
                    }
                }

                // 检查当前周期内的有效查房次数
                if (count($validRoundRecords) < 2) {
                    $basis = [];
                    $basis[] = "住院周期【" . $cycleStartDate . " 至 " . $cycleEndDate . "（" . count($validRoundRecords) . "次）】";

                    // 添加医师信息
                    if (!empty($invalidRecords)) {
                        foreach ($invalidRecords as $record) {
                            $doctorInfo = implode("，", $record['doctor_info']);
                            $signInfo = "";

                            if ($record['sign_status'] == '未签名') {
                                $signInfo = "未签名";
                            } else {
                                $signInfo = $record[$firstBlsyTimeField] . "（超时）";
                            }

                            $basis[] = "【" . $record['BLMC'] . "】【" . $doctorInfo . "】首次签名时间【" . $signInfo . "】";
                        }

                        // 添加BLBH（如果有）
                        if (!empty($invalidRecords)) {
                            $basis["BLBH"] = $invalidRecords[0]['BLBH'];
                        }
                    } else if (!empty($validRoundRecords)) {
                        // 只有一次有效记录，但不够两次
                        foreach ($validRoundRecords as $record) {
                            $doctorInfo = implode("，", $record['doctor_info']);
                            $basis[] = "【" . $record['BLMC'] . "】【" . $doctorInfo . "】首次签名时间【" . $record[$firstBlsyTimeField] . "】";
                        }

                        // 添加BLBH（如果有）
                        if (!empty($validRoundRecords)) {
                            $basis["BLBH"] = $validRoundRecords[0]['BLBH'];
                        }
                    } else {
                        // 当前周期没有任何查房记录
                    }

                    // 收集当前周期的质控依据
                    $allBasisGroups[] = $basis;
                }
            }
        }

        // 10. 将所有质控结果一次性插入到insertData
        if (!empty($allBasisGroups)) {
            $this->insertData[] = [
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title'],
                'basis' => json_encode($allBasisGroups, JSON_UNESCAPED_UNICODE) // 将整个组作为一个数组传入
            ];
        }

        return true;
    }

    /**
     * 医嘱名称含"病危"，每天1次上级医师查房
     * @param $caseRule
     * @param $ruleId
     * @param $ZYH
     * @return bool
     * @author lzh
     */
    public function rule133($caseRule, $ruleId, $ZYH)
    {

        // 1. 获取出院时间和入院时间AAB01，如果没有取当前时间，用这个时间-入院时间如果小与24小时，则不质控
        $exitTime = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->value('AAC01');
        if (empty($exitTime)) {
            $exitTime = Carbon::now()->toDateTimeString();
        }

        // 2. 获取入院时间AAB01
        $enterTime = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->value('AAB01');
        if (empty($enterTime)) {
            return true;
        }

        // 3. 计算出院时间-入院时间
        $enterDate = date('Y-m-d', strtotime($enterTime));
        $exitDate = date('Y-m-d', strtotime($exitTime));
        $diffDays = (strtotime($exitDate) - strtotime($enterDate)) / (24 * 3600);
        if ($diffDays <= 1) {
            return true;
        }

        // 1. 直接设置周期为1天
        $cycleDays = 1;

        // 2. 从MySQL查询医嘱名称含"病危"的医嘱
        //获取病危字段
        $ruleMap8015 = RuleWordMap::query()->where('id', '=', 8015)->value('keyword');
        $ruleMap8015 = !empty($ruleMap8015) ? $ruleMap8015 : '病危';
        // 构建查询条件：医嘱名称含"病危"且住院号为$ZYH
        $yzbData = Yzb::query()
            ->where('YZMC', 'like', '%' . $ruleMap8015 . '%')
            ->where('ZYH', $ZYH)
            ->get()->toArray();

        if (empty($yzbData)) {
            return true; // 没有符合条件的医嘱，不进行质控
        }

        // 3. 获取上级医师查房记录的配置
        // 2. 获取病程记录类型配置
        $ruleMap8049 = RuleWordMap::query()->where('id', '=', 8049)->value('keyword');
        $ruleMap8049 = !empty($ruleMap8049) ? $ruleMap8049 : '50,296'; // 病程记录BLLB值
        //是否包含逗号
        if (strpos($ruleMap8049, ',') !== false) {
            $recordTypes = explode(',', $ruleMap8049);
        } else {
            $recordTypes = [$ruleMap8049];
        }

        // 获取查房记录的时间字段
        $ruleMap8011 = RuleWordMap::query()->where('id', '=', 8011)->value('keyword');
        $ruleMap8011 = !empty($ruleMap8011) ? $ruleMap8011 : 'ZXSJ'; // 查房记录的时间字段名

        // 获取上级医师职称（主任/主治医师）
        $ruleMap8012 = RuleWordMap::query()->where('id', '=', 8012)->value('keyword');
        $ruleMap8012 = !empty($ruleMap8012) ? $ruleMap8012 : '主治,主任'; // 上级医师

        // 判断是否包含逗号
        if (strpos($ruleMap8012, ',') !== false) {
            $highLevelTitles = explode(',', $ruleMap8012);
        } else {
            $highLevelTitles = [$ruleMap8012];
        }

        // 获取首次签名时间字段名
        $firstBlsyTimeField = RuleWordMap::query()->where('id', '=', 8002)->value('keyword');
        $firstBlsyTimeField = !empty($firstBlsyTimeField) ? $firstBlsyTimeField : 'first_blsy_time';

        //8050
        $ruleMap8050 = RuleWordMap::query()->where('id', '=', 8050)->value('keyword');
        $ruleMap8050 = !empty($ruleMap8050) ? $ruleMap8050 : '操作记录';
        //是否包含逗号
        if (strpos($ruleMap8050, ',') !== false) {
            $operationRecordTypes = explode(',', $ruleMap8050);
        } else {
            $operationRecordTypes = [$ruleMap8050];
        }

        // 收集所有质控结果，按医嘱分组
        $allBasisGroups = [];
        $orderBasisGroups = [];

        // 4. 处理每条病危医嘱
        foreach ($yzbData as $order) {
            // 获取开嘱和停嘱时间，添加字段存在检查
            $startTime = isset($order['KZSJ']) ? $order['KZSJ'] : '';
            $endTime = isset($order['TZSJ']) ? $order['TZSJ'] : date('Y-m-d H:i:s'); // 如果没有停嘱时间，使用当前时间
            $yzmc = isset($order['YZMC']) ? $order['YZMC'] : '未知医嘱';

            // 检查开嘱时间是否有效
            if (empty($startTime) || $startTime == '1970-01-01 00:00:00' || $startTime == '0000-00-00 00:00:00') {
                continue; // 跳过无效的开嘱时间
            }

            // 计算开嘱时间点到开嘱时间+24小时的时间段
            $startDateTime = Carbon::parse($startTime);
            $endDateTime = $startDateTime->copy()->addHours(24);

            // 格式化时间段
            $specialStartDate = $startDateTime->format('Y-m-d H:i:s');
            $specialEndDate = $endDateTime->format('Y-m-d H:i:s');

            // 计算开嘱日期和停嘱日期
            $startDate = date('Y-m-d', strtotime($startTime));
            $endDate = date('Y-m-d', strtotime($endTime));

            // 获取包含这个时间段的所有查房记录（包括特殊时间段和剩余周期）
            try {
                $bl01Data = EMR_BL_BL01::query()
                    ->where('JZHM', $ZYH)
                    ->whereIn('MBLB', $recordTypes)
                    ->where(function ($query) use ($specialStartDate, $specialEndDate, $startDate, $endDate, $ruleMap8011) {
                        // 查询特殊时间段（开嘱时间到开嘱时间+24小时）的记录
                        $query->where(function ($subQuery) use ($specialStartDate, $specialEndDate, $ruleMap8011) {
                            $subQuery->where($ruleMap8011, '>=', $specialStartDate)
                                ->where($ruleMap8011, '<=', $specialEndDate);
                        })
                            // 或者查询剩余周期中除开嘱当天的记录
                            ->orWhere(function ($subQuery) use ($ruleMap8011, $startDate, $endDate) {
                                $subQuery->where($ruleMap8011, '>', $startDate . ' 23:59:59')
                                    ->where($ruleMap8011, '<=', $endDate . ' 23:59:59');
                            });
                    })
                    ->get()
                    ->toArray();
            } catch (\Exception $e) {
                Log::error("rule-$ruleId-$ZYH-error: " . $e->getMessage());
                continue; // 查询出错时跳过当前医嘱
            }

            // 按日期分组查房记录
            $roundsByDate = [];
            foreach ($bl01Data as $record) {
                if (!isset($record[$ruleMap8011])) {
                    continue; // 跳过没有执行时间的记录
                }
                foreach ($operationRecordTypes as $operationRecordType) {
                    if (strpos($record['BLMC'], $operationRecordType) !== false) {
                        continue 2;
                    }
                }
                $recordDate = date('Y-m-d', strtotime($record[$ruleMap8011]));
                if (!isset($roundsByDate[$recordDate])) {
                    $roundsByDate[$recordDate] = [];
                }
                $roundsByDate[$recordDate][] = $record;
            }

            // 医嘱信息
            $orderInfo = "医嘱名称【" . $yzmc . "】";
            $orderInfo1 = "开嘱时间【" . $startTime . "】停嘱时间【" . $endTime . "】";

            // 为当前医嘱创建质控记录组
            $cycleGroups = [];

            // 5. 首先检查特殊周期（开嘱时间到开嘱时间+24小时）
            $specialCycleStartDate = $startDateTime->format('Y-m-d');
            $specialCycleEndDate = $endDateTime->format('Y-m-d');

            // 获取特殊周期内的有效查房记录
            $validRoundsInSpecialCycle = [];
            $invalidRoundsInSpecialCycle = [];

            // 遍历特殊周期内的每一天
            $currentDate = $specialCycleStartDate;
            while (strtotime($currentDate) <= strtotime($specialCycleEndDate)) {
                if (isset($roundsByDate[$currentDate])) {
                    foreach ($roundsByDate[$currentDate] as $record) {
                        // 检查记录时间是否在特殊时间段内
                        $recordTime = $record[$ruleMap8011];
                        if ($recordTime >= $specialStartDate && $recordTime <= $specialEndDate) {
                            // 检查医生职称
                            try {
                                $blsy = EMR_BL_BLSY::query()->where('BLBH', $record['BLBH'])->where('FG_ACTIVE', 1)->pluck('SYYS')->toArray();
                                $blsy = array_unique($blsy);
                            } catch (\Exception $e) {
                                Log::error("rule-$ruleId-$ZYH-error: " . $e->getMessage());
                                continue; // 查询出错时跳过当前记录
                            }

                            $highLevelDoctors = [];

                            foreach ($blsy as $doctor) {
                                try {
                                    $staffInfo = STAFF::query()->where('code', $doctor)->first();
                                    if (!$staffInfo) continue;

                                    $staff = $staffInfo->ygjb_text;
                                    $doctorName = $staffInfo->name;

                                    // 检查是否是上级医师
                                    foreach ($highLevelTitles as $title) {
                                        if (strpos($staff, $title) !== false) {
                                            $highLevelDoctors[] = $doctorName . '（' . $staff . '）';
                                            break;
                                        }
                                    }
                                } catch (\Exception $e) {
                                    Log::error("rule-$ruleId-$ZYH-error: " . $e->getMessage());
                                    continue; // 查询出错时跳过当前医生
                                }
                            }

                            // 如果有上级医师
                            if (!empty($highLevelDoctors)) {
                                // 检查签名时间
                                $firstBlsyTime = isset($record[$firstBlsyTimeField]) ? $record[$firstBlsyTimeField] : '';

                                // 添加医生信息和签名状态
                                $record['doctor_info'] = $highLevelDoctors;

                                if (empty($firstBlsyTime)) {
                                    // 未签名
                                    $record['sign_status'] = '未签名';
                                    $invalidRoundsInSpecialCycle[] = $record;
                                } else {
                                    // 检查签名时间是否在特殊周期内
                                    $signDate = date('Y-m-d', strtotime($firstBlsyTime));

                                    if (
                                        strtotime($signDate) >= strtotime($specialCycleStartDate) &&
                                        strtotime($signDate) <= strtotime($specialCycleEndDate)
                                    ) {
                                        // 有效记录
                                        $record['sign_status'] = '正常';
                                        $validRoundsInSpecialCycle[] = $record;
                                    } else {
                                        // 签名时间不在周期内
                                        $record['sign_status'] = '超时';
                                        $invalidRoundsInSpecialCycle[] = $record;
                                    }
                                }
                            }
                        }
                    }
                }

                // 移动到下一天
                $currentDate = date('Y-m-d', strtotime($currentDate . ' +1 day'));
            }

            // 如果特殊周期内没有有效的上级医师查房记录，记录质控
            if (empty($validRoundsInSpecialCycle)) {
                $cycleItem = [];
                $cycleItem[] = "开嘱当天【" . $specialStartDate . " 至 " . $specialEndDate . "】";

                // 添加医师信息
                if (!empty($invalidRoundsInSpecialCycle)) {
                    foreach ($invalidRoundsInSpecialCycle as $record) {
                        $doctorInfo = implode("，", $record['doctor_info']);
                        $signInfo = "";

                        if ($record['sign_status'] == '未签名') {
                            $signInfo = "未签名";
                        } else {
                            $signInfo = $record[$firstBlsyTimeField] . "（超时）";
                        }

                        $cycleItem[] = "【" . $record['BLMC'] . "】【" . $doctorInfo . "】首次签名时间【" . $signInfo . "】";
                    }
                } else {
                    // 当前周期没有任何查房记录
                    $cycleItem[] = "【无】";
                }

                // 添加BLBH（如果有）
                if (!empty($invalidRoundsInSpecialCycle)) {
                    $cycleItem["BLBH"] = $invalidRoundsInSpecialCycle[0]['BLBH'];
                }

                // 将这个周期的记录添加到当前医嘱的周期组中
                $cycleGroups[] = $cycleItem;
            }

            // 6. 计算剩余周期（跳过开嘱当天）
            $remainingStartDate = date('Y-m-d', strtotime($startDate . ' +1 day'));
            $remainingEndDate = $endDate;

            // 如果剩余开始日期大于结束日期，说明没有剩余周期
            if (strtotime($remainingStartDate) > strtotime($remainingEndDate)) {
                $totalRemainingCycles = 0;
            } else {
                $remainingDays = ceil((strtotime($remainingEndDate) - strtotime($remainingStartDate)) / (24 * 3600)) + 1;
                $totalRemainingCycles = ceil($remainingDays / $cycleDays);
            }

            // 7. 循环检查每个剩余周期
            for ($i = 0; $i < $totalRemainingCycles; $i++) {
                // 计算周期开始日期
                $cycleStartDate = date('Y-m-d', strtotime($remainingStartDate . " +" . ($i * $cycleDays) . " days"));
                // 计算周期结束日期（不超过医嘱结束日期）
                $cycleEndTimestamp = min(strtotime($cycleStartDate . " +" . ($cycleDays - 1) . " days"), strtotime($remainingEndDate));
                $cycleEndDate = date('Y-m-d', $cycleEndTimestamp);

                // 获取当前周期内的有效查房记录
                $validRoundsInCycle = [];
                $invalidRounds = [];

                // 遍历周期内的每一天
                $currentDate = $cycleStartDate;
                while (strtotime($currentDate) <= strtotime($cycleEndDate)) {
                    if (isset($roundsByDate[$currentDate])) {
                        foreach ($roundsByDate[$currentDate] as $record) {
                            // 检查医生职称
                            try {
                                $blsy = EMR_BL_BLSY::query()->where('BLBH', $record['BLBH'])->where('FG_ACTIVE', 1)->pluck('SYYS')->toArray();
                                $blsy = array_unique($blsy);
                            } catch (\Exception $e) {
                                Log::error("rule-$ruleId-$ZYH-error: " . $e->getMessage());
                                continue; // 查询出错时跳过当前记录
                            }

                            $highLevelDoctors = [];

                            foreach ($blsy as $doctor) {
                                try {
                                    $staffInfo = STAFF::query()->where('code', $doctor)->first();
                                    if (!$staffInfo) continue;

                                    $staff = $staffInfo->ygjb_text;
                                    $doctorName = $staffInfo->name;

                                    // 检查是否是上级医师
                                    foreach ($highLevelTitles as $title) {
                                        if (strpos($staff, $title) !== false) {
                                            $highLevelDoctors[] = $doctorName . '（' . $staff . '）';
                                            break;
                                        }
                                    }
                                } catch (\Exception $e) {
                                    Log::error("rule-$ruleId-$ZYH-error: " . $e->getMessage());
                                    continue; // 查询出错时跳过当前医生
                                }
                            }

                            // 如果有上级医师
                            if (!empty($highLevelDoctors)) {
                                // 检查签名时间
                                $firstBlsyTime = isset($record[$firstBlsyTimeField]) ? $record[$firstBlsyTimeField] : '';

                                // 添加医生信息和签名状态
                                $record['doctor_info'] = $highLevelDoctors;

                                if (empty($firstBlsyTime)) {
                                    // 未签名
                                    $record['sign_status'] = '未签名';
                                    $invalidRounds[] = $record;
                                } else {
                                    // 检查签名时间是否在当前周期内
                                    $signDate = date('Y-m-d', strtotime($firstBlsyTime));

                                    if (
                                        strtotime($signDate) >= strtotime($cycleStartDate) &&
                                        strtotime($signDate) <= strtotime($cycleEndDate)
                                    ) {
                                        // 有效记录
                                        $record['sign_status'] = '正常';
                                        $validRoundsInCycle[] = $record;
                                    } else {
                                        // 签名时间不在周期内
                                        $record['sign_status'] = '超时';
                                        $invalidRounds[] = $record;
                                    }
                                }
                            }
                        }
                    }

                    // 移动到下一天
                    $currentDate = date('Y-m-d', strtotime($currentDate . ' +1 day'));
                }

                // 如果当前周期内没有有效的上级医师查房记录，记录质控
                if (empty($validRoundsInCycle)) {
                    $cycleItem = [];
                    $cycleItem[] = "周期【" . $cycleStartDate . " 至 " . $cycleEndDate . "】";

                    // 添加医师信息
                    if (!empty($invalidRounds)) {
                        foreach ($invalidRounds as $record) {
                            $doctorInfo = implode("，", $record['doctor_info']);
                            $signInfo = "";

                            if ($record['sign_status'] == '未签名') {
                                $signInfo = "未签名";
                            } else {
                                $signInfo = $record[$firstBlsyTimeField] . "（超时）";
                            }

                            $cycleItem[] = "【" . $record['BLMC'] . "】【" . $doctorInfo . "】首次签名时间【" . $signInfo . "】";
                        }
                    } else {
                        // 当前周期没有任何查房记录
                        $cycleItem[] = "【无】";
                    }

                    // 添加BLBH（如果有）
                    if (!empty($invalidRounds)) {
                        $cycleItem["BLBH"] = $invalidRounds[0]['BLBH'];
                    }

                    // 将这个周期的记录添加到当前医嘱的周期组中
                    $cycleGroups[] = $cycleItem;
                }
            }

            // 如果有需要质控的周期
            if (!empty($cycleGroups)) {
                // 创建当前医嘱的基础信息
                $orderBasis = [];
                $orderBasis[] = $orderInfo;
                $orderBasis[] = $orderInfo1;

                // 创建一个包含所有周期的完整记录
                $allCycleDetails = [];
                foreach ($cycleGroups as $cycle) {
                    // 取出第一个元素(周期信息)
                    $cycleInfo = array_shift($cycle);
                    // 将周期信息添加到基础信息
                    $newCycle = array_merge([$cycleInfo], $cycle);
                    // 添加到所有周期记录
                    $allCycleDetails = array_merge($allCycleDetails, $newCycle);
                }

                // 合并医嘱基础信息和周期详情
                $orderBasisGroups = array_merge($orderBasis, $allCycleDetails);
                $allBasisGroups[] = $orderBasisGroups;
            }
        }

        // 6. 将所有质控结果一次性插入到insertData
        if (!empty($allBasisGroups)) {
            $this->insertData[] = [
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title'],
                'basis' => json_encode($allBasisGroups, JSON_UNESCAPED_UNICODE)
            ];
        }

        return true;
    }

    /**
     * 医嘱名称含"病危"，每天1次上级医师查房 新（查blmc）
     * @param $caseRule
     * @param $ruleId
     * @param $ZYH
     * @return bool
     * @author lzh
     */
    public function rule1049($caseRule, $ruleId, $ZYH)
    {

        // 1. 获取出院时间和入院时间AAB01，如果没有取当前时间，用这个时间-入院时间如果小与24小时，则不质控
        $exitTime = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->value('AAC01');
        if (empty($exitTime)) {
            $exitTime = Carbon::now()->toDateTimeString();
        }

        // 2. 获取入院时间AAB01
        $enterTime = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->value('AAB01');
        if (empty($enterTime)) {
            return true;
        }

        // 3. 计算出院时间-入院时间
        $enterDate = date('Y-m-d', strtotime($enterTime));
        $exitDate = date('Y-m-d', strtotime($exitTime));
        $diffDays = (strtotime($exitDate) - strtotime($enterDate)) / (24 * 3600);
        if ($diffDays <= 1) {
            return true;
        }

        // 1. 直接设置周期为1天
        $cycleDays = 1;

        // 2. 从MySQL查询医嘱名称含"病危"的医嘱
        //获取病危字段
        $ruleMap8015 = RuleWordMap::query()->where('id', '=', 8015)->value('keyword');
        $ruleMap8015 = !empty($ruleMap8015) ? $ruleMap8015 : '病危';
        // 构建查询条件：医嘱名称含"病危"且住院号为$ZYH
        $yzbData = Yzb::query()
            ->where('YZMC', 'like', '%' . $ruleMap8015 . '%')
            ->where('ZYH', $ZYH)
            ->get()->toArray();

        if (empty($yzbData)) {
            return true; // 没有符合条件的医嘱，不进行质控
        }

        // 3. 获取上级医师查房记录的配置
        // 2. 获取病程记录类型配置
        $ruleMap8049 = RuleWordMap::query()->where('id', '=', 8049)->value('keyword');
        $ruleMap8049 = !empty($ruleMap8049) ? $ruleMap8049 : '50,296'; // 病程记录BLLB值
        //是否包含逗号
        if (strpos($ruleMap8049, ',') !== false) {
            $recordTypes = explode(',', $ruleMap8049);
        } else {
            $recordTypes = [$ruleMap8049];
        }

        // 获取查房记录的时间字段
        $ruleMap8011 = RuleWordMap::query()->where('id', '=', 8011)->value('keyword');
        $ruleMap8011 = !empty($ruleMap8011) ? $ruleMap8011 : 'ZXSJ'; // 查房记录的时间字段名

        // 获取上级医师职称（主任/主治医师）
        $ruleMap8012 = RuleWordMap::query()->where('id', '=', 8012)->value('keyword');
        $ruleMap8012 = !empty($ruleMap8012) ? $ruleMap8012 : '主治,主任'; // 上级医师

        // 判断是否包含逗号
        if (strpos($ruleMap8012, ',') !== false) {
            $highLevelTitles = explode(',', $ruleMap8012);
        } else {
            $highLevelTitles = [$ruleMap8012];
        }

        // 获取首次签名时间字段名
        $firstBlsyTimeField = RuleWordMap::query()->where('id', '=', 8002)->value('keyword');
        $firstBlsyTimeField = !empty($firstBlsyTimeField) ? $firstBlsyTimeField : 'first_blsy_time';

        //8050
        $ruleMap8050 = RuleWordMap::query()->where('id', '=', 8050)->value('keyword');
        $ruleMap8050 = !empty($ruleMap8050) ? $ruleMap8050 : '操作记录';
        //是否包含逗号
        if (strpos($ruleMap8050, ',') !== false) {
            $operationRecordTypes = explode(',', $ruleMap8050);
        } else {
            $operationRecordTypes = [$ruleMap8050];
        }

        // 收集所有质控结果，按医嘱分组
        $allBasisGroups = [];
        $orderBasisGroups = [];

        // 4. 处理每条病危医嘱
        foreach ($yzbData as $order) {
            // 获取开嘱和停嘱时间，添加字段存在检查
            $startTime = isset($order['KZSJ']) ? $order['KZSJ'] : '';
            $endTime = isset($order['TZSJ']) ? $order['TZSJ'] : date('Y-m-d H:i:s'); // 如果没有停嘱时间，使用当前时间
            $yzmc = isset($order['YZMC']) ? $order['YZMC'] : '未知医嘱';

            // 检查开嘱时间是否有效
            if (empty($startTime) || $startTime == '1970-01-01 00:00:00' || $startTime == '0000-00-00 00:00:00') {
                continue; // 跳过无效的开嘱时间
            }

            // 计算开嘱时间点到开嘱时间+24小时的时间段
            $startDateTime = Carbon::parse($startTime);
            $endDateTime = $startDateTime->copy()->addHours(24);

            // 格式化时间段
            $specialStartDate = $startDateTime->format('Y-m-d H:i:s');
            $specialEndDate = $endDateTime->format('Y-m-d H:i:s');

            // 计算开嘱日期和停嘱日期
            $startDate = date('Y-m-d', strtotime($startTime));
            $endDate = date('Y-m-d', strtotime($endTime));

            // 获取包含这个时间段的所有查房记录（包括特殊时间段和剩余周期）
            try {
                $bl01Data = EMR_BL_BL01::query()
                    ->where('JZHM', $ZYH)
                    ->whereIn('MBLB', $recordTypes)
                    ->where(function ($query) use ($specialStartDate, $specialEndDate, $startDate, $endDate, $ruleMap8011) {
                        // 查询特殊时间段（开嘱时间到开嘱时间+24小时）的记录
                        $query->where(function ($subQuery) use ($specialStartDate, $specialEndDate, $ruleMap8011) {
                            $subQuery->where($ruleMap8011, '>=', $specialStartDate)
                                ->where($ruleMap8011, '<=', $specialEndDate);
                        })
                            // 或者查询剩余周期中除开嘱当天的记录
                            ->orWhere(function ($subQuery) use ($ruleMap8011, $startDate, $endDate) {
                                $subQuery->where($ruleMap8011, '>', $startDate . ' 23:59:59')
                                    ->where($ruleMap8011, '<=', $endDate . ' 23:59:59');
                            });
                    })
                    ->get()
                    ->toArray();
            } catch (\Exception $e) {
                Log::error("rule-$ruleId-$ZYH-error: " . $e->getMessage());
                continue; // 查询出错时跳过当前医嘱
            }

            // 按日期分组查房记录
            $roundsByDate = [];
            foreach ($bl01Data as $record) {
                if (!isset($record[$ruleMap8011])) {
                    continue; // 跳过没有执行时间的记录
                }
                foreach ($operationRecordTypes as $operationRecordType) {
                    if (strpos($record['BLMC'], $operationRecordType) !== false) {
                        continue 2;
                    }
                }
                $recordDate = date('Y-m-d', strtotime($record[$ruleMap8011]));
                if (!isset($roundsByDate[$recordDate])) {
                    $roundsByDate[$recordDate] = [];
                }
                $roundsByDate[$recordDate][] = $record;
            }

            // 医嘱信息
            $orderInfo = "医嘱名称【" . $yzmc . "】";
            $orderInfo1 = "开嘱时间【" . $startTime . "】停嘱时间【" . $endTime . "】";

            // 为当前医嘱创建质控记录组
            $cycleGroups = [];

            // 5. 首先检查特殊周期（开嘱时间到开嘱时间+24小时）
            $specialCycleStartDate = $startDateTime->format('Y-m-d');
            $specialCycleEndDate = $endDateTime->format('Y-m-d');

            // 获取特殊周期内的有效查房记录
            $validRoundsInSpecialCycle = [];
            $invalidRoundsInSpecialCycle = [];

            // 遍历特殊周期内的每一天
            $currentDate = $specialCycleStartDate;
            while (strtotime($currentDate) <= strtotime($specialCycleEndDate)) {
                if (isset($roundsByDate[$currentDate])) {
                    foreach ($roundsByDate[$currentDate] as $record) {
                        // 检查记录时间是否在特殊时间段内
                        $recordTime = $record[$ruleMap8011];
                        if ($recordTime >= $specialStartDate && $recordTime <= $specialEndDate) {


                            $highLevelDoctors = [];


                            $blmc = $record['BLMC'];
                            // 检查是否是上级医师
                            foreach ($highLevelTitles as $title) {
                                if (strpos($blmc, $title) !== false) {
                                    $highLevelDoctors[] = $title;
                                    break;
                                }
                            }


                            // 如果有上级医师
                            if (!empty($highLevelDoctors)) {
                                // 检查签名时间
                                $firstBlsyTime = isset($record[$firstBlsyTimeField]) ? $record[$firstBlsyTimeField] : '';

                                // 添加医生信息和签名状态
                                $record['doctor_info'] = $highLevelDoctors;

                                if (empty($firstBlsyTime)) {
                                    // 未签名
                                    $record['sign_status'] = '未签名';
                                    $invalidRoundsInSpecialCycle[] = $record;
                                } else {
                                    // 检查签名时间是否在特殊周期内
                                    $signDate = date('Y-m-d', strtotime($firstBlsyTime));

                                    if (
                                        strtotime($signDate) >= strtotime($specialCycleStartDate) &&
                                        strtotime($signDate) <= strtotime($specialCycleEndDate)
                                    ) {
                                        // 有效记录
                                        $record['sign_status'] = '正常';
                                        $validRoundsInSpecialCycle[] = $record;
                                    } else {
                                        // 签名时间不在周期内
                                        $record['sign_status'] = '超时';
                                        $invalidRoundsInSpecialCycle[] = $record;
                                    }
                                }
                            }
                        }
                    }
                }

                // 移动到下一天
                $currentDate = date('Y-m-d', strtotime($currentDate . ' +1 day'));
            }

            // 如果特殊周期内没有有效的上级医师查房记录，记录质控
            if (empty($validRoundsInSpecialCycle)) {
                $cycleItem = [];
                $cycleItem[] = "开嘱当天【" . $specialStartDate . " 至 " . $specialEndDate . "】";

                // 添加医师信息
                if (!empty($invalidRoundsInSpecialCycle)) {
                    foreach ($invalidRoundsInSpecialCycle as $record) {
                        $doctorInfo = implode("，", $record['doctor_info']);
                        $signInfo = "";

                        if ($record['sign_status'] == '未签名') {
                            $signInfo = "未签名";
                        } else {
                            $signInfo = $record[$firstBlsyTimeField] . "（超时）";
                        }

                        $cycleItem[] = "【" . $record['BLMC'] . "】【" . $doctorInfo . "】首次签名时间【" . $signInfo . "】";
                    }
                } else {
                    // 当前周期没有任何查房记录
                    $cycleItem[] = "【无】";
                }

                // 添加BLBH（如果有）
                if (!empty($invalidRoundsInSpecialCycle)) {
                    $cycleItem["BLBH"] = $invalidRoundsInSpecialCycle[0]['BLBH'];
                }

                // 将这个周期的记录添加到当前医嘱的周期组中
                $cycleGroups[] = $cycleItem;
            }

            // 6. 计算剩余周期（跳过开嘱当天）
            $remainingStartDate = date('Y-m-d', strtotime($startDate . ' +1 day'));
            $remainingEndDate = $endDate;

            // 如果剩余开始日期大于结束日期，说明没有剩余周期
            if (strtotime($remainingStartDate) > strtotime($remainingEndDate)) {
                $totalRemainingCycles = 0;
            } else {
                $remainingDays = ceil((strtotime($remainingEndDate) - strtotime($remainingStartDate)) / (24 * 3600)) + 1;
                $totalRemainingCycles = ceil($remainingDays / $cycleDays);
            }

            // 7. 循环检查每个剩余周期
            for ($i = 0; $i < $totalRemainingCycles; $i++) {
                // 计算周期开始日期
                $cycleStartDate = date('Y-m-d', strtotime($remainingStartDate . " +" . ($i * $cycleDays) . " days"));
                // 计算周期结束日期（不超过医嘱结束日期）
                $cycleEndTimestamp = min(strtotime($cycleStartDate . " +" . ($cycleDays - 1) . " days"), strtotime($remainingEndDate));
                $cycleEndDate = date('Y-m-d', $cycleEndTimestamp);

                //先查询当前周期内是否有死亡医嘱，如果有就跳过这个周期
                $starttime = $cycleStartDate . ' 00:00:00';
                $endtime = $cycleEndDate . ' 23:59:59';

                // 从MySQL查询死亡医嘱
                $exitKeywords = RuleWordMap::getArrayById(8003);
                $yzbQuery = Yzb::query()
                    ->where('ZYH', $ZYH)
                    ->where('KZSJ', '>=', $starttime)
                    ->where('KZSJ', '<=', $endtime)
                    ->get()->toArray();
                $yzbData = [];
                foreach ($yzbQuery as $record) {
                    foreach ($exitKeywords as $keyword) {
                        if (strpos($record['YZMC'], $keyword) !== false) {
                            $yzbData[] = $record['YZMC'];
                            break;
                        }
                    }
                }

                if (!empty($yzbData)) {
                    continue;
                }

                // 获取当前周期内的有效查房记录
                $validRoundsInCycle = [];
                $invalidRounds = [];

                // 遍历周期内的每一天
                $currentDate = $cycleStartDate;
                while (strtotime($currentDate) <= strtotime($cycleEndDate)) {
                    if (isset($roundsByDate[$currentDate])) {
                        foreach ($roundsByDate[$currentDate] as $record) {
                            // 检查医生职称
                            try {
                                $blsy = EMR_BL_BLSY::query()->where('BLBH', $record['BLBH'])->where('FG_ACTIVE', 1)->pluck('SYYS')->toArray();
                                $blsy = array_unique($blsy);
                            } catch (\Exception $e) {
                                Log::error("rule-$ruleId-$ZYH-error: " . $e->getMessage());
                                continue; // 查询出错时跳过当前记录
                            }

                            $highLevelDoctors = [];

                            $blmc = $record['BLMC'];
                            // 检查是否是上级医师
                            foreach ($highLevelTitles as $title) {
                                if (strpos($blmc, $title) !== false) {
                                    $highLevelDoctors[] = $title;
                                    break;
                                }
                            }

                            // 如果有上级医师
                            if (!empty($highLevelDoctors)) {
                                // 检查签名时间
                                $firstBlsyTime = isset($record[$firstBlsyTimeField]) ? $record[$firstBlsyTimeField] : '';

                                // 添加医生信息和签名状态
                                $record['doctor_info'] = $highLevelDoctors;

                                if (empty($firstBlsyTime)) {
                                    // 未签名
                                    $record['sign_status'] = '未签名';
                                    $invalidRounds[] = $record;
                                } else {
                                    // 检查签名时间是否在当前周期内
                                    $signDate = date('Y-m-d', strtotime($firstBlsyTime));

                                    if (
                                        strtotime($signDate) >= strtotime($cycleStartDate) &&
                                        strtotime($signDate) <= strtotime($cycleEndDate)
                                    ) {
                                        // 有效记录
                                        $record['sign_status'] = '正常';
                                        $validRoundsInCycle[] = $record;
                                    } else {
                                        // 签名时间不在周期内
                                        $record['sign_status'] = '超时';
                                        $invalidRounds[] = $record;
                                    }
                                }
                            }
                        }
                    }

                    // 移动到下一天
                    $currentDate = date('Y-m-d', strtotime($currentDate . ' +1 day'));
                }

                // 如果当前周期内没有有效的上级医师查房记录，记录质控
                if (empty($validRoundsInCycle)) {
                    $cycleItem = [];
                    $cycleItem[] = "周期【" . $cycleStartDate . " 至 " . $cycleEndDate . "】";

                    // 添加医师信息
                    if (!empty($invalidRounds)) {
                        foreach ($invalidRounds as $record) {
                            $doctorInfo = implode("，", $record['doctor_info']);
                            $signInfo = "";

                            if ($record['sign_status'] == '未签名') {
                                $signInfo = "未签名";
                            } else {
                                $signInfo = $record[$firstBlsyTimeField] . "（超时）";
                            }

                            $cycleItem[] = "【" . $record['BLMC'] . "】【" . $doctorInfo . "】首次签名时间【" . $signInfo . "】";
                        }
                    } else {
                        // 当前周期没有任何查房记录
                        $cycleItem[] = "【无】";
                    }

                    // 添加BLBH（如果有）
                    if (!empty($invalidRounds)) {
                        $cycleItem["BLBH"] = $invalidRounds[0]['BLBH'];
                    }

                    // 将这个周期的记录添加到当前医嘱的周期组中
                    $cycleGroups[] = $cycleItem;
                }
            }

            // 如果有需要质控的周期
            if (!empty($cycleGroups)) {
                // 创建当前医嘱的基础信息
                $orderBasis = [];
                $orderBasis[] = $orderInfo;
                $orderBasis[] = $orderInfo1;

                // 创建一个包含所有周期的完整记录
                $allCycleDetails = [];
                foreach ($cycleGroups as $cycle) {
                    // 取出第一个元素(周期信息)
                    $cycleInfo = array_shift($cycle);
                    // 将周期信息添加到基础信息
                    $newCycle = array_merge([$cycleInfo], $cycle);
                    // 添加到所有周期记录
                    $allCycleDetails = array_merge($allCycleDetails, $newCycle);
                }

                // 合并医嘱基础信息和周期详情
                $orderBasisGroups = array_merge($orderBasis, $allCycleDetails);
                $allBasisGroups[] = $orderBasisGroups;
            }
        }

        // 6. 将所有质控结果一次性插入到insertData
        if (!empty($allBasisGroups)) {
            $this->insertData[] = [
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title'],
                'basis' => json_encode($allBasisGroups, JSON_UNESCAPED_UNICODE)
            ];
        }

        return true;
    }

    /**
     * 医嘱名称含"病重"，每两天1次上级医师查房
     * @param $caseRule
     * @param $ruleId
     * @param $ZYH
     * @return bool
     * @author lzh
     */
    public function rule134($caseRule, $ruleId, $ZYH)
    {

        // 1. 获取出院时间和入院时间AAB01，如果没有取当前时间，用这个时间-入院时间如果小与24小时，则不质控
        $exitTime = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->value('AAC01');
        if (empty($exitTime)) {
            $exitTime = Carbon::now()->toDateTimeString();
        }

        // 2. 获取入院时间AAB01
        $enterTime = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->value('AAB01');
        if (empty($enterTime)) {
            return true;
        }


        // 3. 计算出院时间-入院时间
        $enterDate = date('Y-m-d', strtotime($enterTime));
        $exitDate = date('Y-m-d', strtotime($exitTime));
        $diffDays = (strtotime($exitDate) - strtotime($enterDate)) / (24 * 3600);
        if ($diffDays <= 1) {
            return true;
        }

        // 1. 直接设置周期为2天
        $cycleDays = 2;

        // 2. 从MySQL查询医嘱名称含"病重"的医嘱
        //获取病重字段
        $ruleMap8014 = RuleWordMap::getArrayById(8014);
        $yzbQuery = Yzb::query()->where('ZYH', $ZYH)->get()->toArray();
        $yzbData = [];
        foreach ($yzbQuery as $record) {
            foreach ($ruleMap8014 as $keyword) {
                if (strpos($record['YZMC'], $keyword) !== false) {
                    $yzbData[] = $record['YZMC'];
                    break;
                }
            }
        }
        if (empty($yzbData)) {
            return true; // 没有符合条件的医嘱，不进行质控
        }

        // 2. 获取病程记录类型配置
        $ruleMap8049 = RuleWordMap::query()->where('id', '=', 8049)->value('keyword');
        $ruleMap8049 = !empty($ruleMap8049) ? $ruleMap8049 : '50,296'; // 病程记录BLLB值
        //是否包含逗号
        if (strpos($ruleMap8049, ',') !== false) {
            $recordTypes = explode(',', $ruleMap8049);
        } else {
            $recordTypes = [$ruleMap8049];
        }

        // 获取查房记录的时间字段
        $ruleMap8011 = RuleWordMap::query()->where('id', '=', 8011)->value('keyword');
        $ruleMap8011 = !empty($ruleMap8011) ? $ruleMap8011 : 'ZXSJ'; // 查房记录的时间字段名

        // 获取上级医师职称（主任/主治医师）
        $ruleMap8012 = RuleWordMap::query()->where('id', '=', 8012)->value('keyword');
        $ruleMap8012 = !empty($ruleMap8012) ? $ruleMap8012 : '主治,主任'; // 上级医师

        // 判断是否包含逗号
        if (strpos($ruleMap8012, ',') !== false) {
            $highLevelTitles = explode(',', $ruleMap8012);
        } else {
            $highLevelTitles = [$ruleMap8012];
        }

        // 获取首次签名时间字段名
        $firstBlsyTimeField = RuleWordMap::query()->where('id', '=', 8002)->value('keyword');
        $firstBlsyTimeField = !empty($firstBlsyTimeField) ? $firstBlsyTimeField : 'first_blsy_time'; // 修正拼写错误

        //8050
        $ruleMap8050 = RuleWordMap::query()->where('id', '=', 8050)->value('keyword');
        $ruleMap8050 = !empty($ruleMap8050) ? $ruleMap8050 : '操作记录';
        //是否包含逗号
        if (strpos($ruleMap8050, ',') !== false) {
            $operationRecordTypes = explode(',', $ruleMap8050);
        } else {
            $operationRecordTypes = [$ruleMap8050];
        }

        // 收集所有质控结果，按医嘱分组
        $allBasisGroups = [];

        // 4. 处理每条病重医嘱
        foreach ($yzbData as $order) {
            // 获取开嘱和停嘱时间，添加字段存在检查
            $startTime = isset($order['KZSJ']) ? $order['KZSJ'] : '';
            $endTime = isset($order['TZSJ']) ? $order['TZSJ'] : date('Y-m-d H:i:s'); // 如果没有停嘱时间，使用当前时间
            $yzmc = isset($order['YZMC']) ? $order['YZMC'] : '未知医嘱';

            // 检查开嘱时间是否有效
            if (empty($startTime) || $startTime == '1970-01-01 00:00:00' || $startTime == '0000-00-00 00:00:00') {
                continue; // 跳过无效的开嘱时间
            }

            // 计算开嘱日期到停嘱日期的天数
            $startDate = date('Y-m-d', strtotime($startTime));
            $endDate = date('Y-m-d', strtotime($endTime));
            $totalDays = ceil((strtotime($endDate) - strtotime($startDate)) / (24 * 3600)) + 1;
            $totalCycles = ceil($totalDays / $cycleDays);

            // 获取包含这个时间段的所有查房记录
            try {
                $bl01Data = EMR_BL_BL01::query()
                    ->where('JZHM', $ZYH)
                    ->whereIn('MBLB', $recordTypes)
                    ->where($ruleMap8011, '>=', $startDate . ' 00:00:00')
                    ->where($ruleMap8011, '<=', $endDate . ' 23:59:59')
                    ->get()
                    ->toArray();
            } catch (\Exception $e) {
                Log::error("rule-$ruleId-$ZYH-error: " . $e->getMessage());
                continue; // 查询出错时跳过当前医嘱
            }

            // 按日期分组查房记录
            $roundsByDate = [];
            foreach ($bl01Data as $record) {
                if (!isset($record[$ruleMap8011])) {
                    continue; // 跳过没有执行时间的记录
                }
                foreach ($operationRecordTypes as $operationRecordType) {
                    if (strpos($record['BLMC'], $operationRecordType) !== false) {
                        continue 2;
                    }
                }
                $recordDate = date('Y-m-d', strtotime($record[$ruleMap8011]));
                if (!isset($roundsByDate[$recordDate])) {
                    $roundsByDate[$recordDate] = [];
                }
                $roundsByDate[$recordDate][] = $record;
            }

            // 医嘱信息
            $orderInfo = "医嘱名称【" . $yzmc . "】";
            $orderInfo1 = "开嘱时间【" . $startTime . "】停嘱时间【" . $endTime . "】";

            // 为当前医嘱创建质控记录组
            $cycleGroups = [];

            // 5. 循环检查每个周期
            for ($i = 0; $i < $totalCycles; $i++) {
                // 计算周期开始日期
                $cycleStartDate = date('Y-m-d', strtotime($startDate . " +" . ($i * $cycleDays) . " days"));
                // 计算周期结束日期（不超过医嘱结束日期）
                $cycleEndTimestamp = min(strtotime($cycleStartDate . " +" . ($cycleDays - 1) . " days"), strtotime($endDate));
                $cycleEndDate = date('Y-m-d', $cycleEndTimestamp);

                // 获取当前周期内的有效查房记录
                $validRoundsInCycle = [];
                $invalidRounds = [];

                // 遍历周期内的每一天
                $currentDate = $cycleStartDate;
                while (strtotime($currentDate) <= strtotime($cycleEndDate)) {
                    if (isset($roundsByDate[$currentDate])) {
                        foreach ($roundsByDate[$currentDate] as $record) {
                            // 检查医生职称
                            try {
                                $blsy = EMR_BL_BLSY::query()->where('BLBH', $record['BLBH'])->where('FG_ACTIVE', 1)->pluck('SYYS')->toArray();
                                $blsy = array_unique($blsy);
                            } catch (\Exception $e) {
                                Log::error("rule-$ruleId-$ZYH-error: " . $e->getMessage());
                                continue; // 查询出错时跳过当前记录
                            }

                            $highLevelDoctors = [];

                            foreach ($blsy as $doctor) {
                                try {
                                    $staffInfo = STAFF::query()->where('code', $doctor)->first();
                                    if (!$staffInfo) continue;

                                    $staff = $staffInfo->ygjb_text;
                                    $doctorName = $staffInfo->name;

                                    // 检查是否是上级医师
                                    foreach ($highLevelTitles as $title) {
                                        if (strpos($staff, $title) !== false) {
                                            $highLevelDoctors[] = $doctorName . '（' . $staff . '）';
                                            break;
                                        }
                                    }
                                } catch (\Exception $e) {
                                    Log::error("rule-$ruleId-$ZYH-error: " . $e->getMessage());
                                    continue; // 查询出错时跳过当前医生
                                }
                            }

                            // 如果有上级医师
                            if (!empty($highLevelDoctors)) {
                                // 检查签名时间
                                $firstBlsyTime = isset($record[$firstBlsyTimeField]) ? $record[$firstBlsyTimeField] : '';

                                // 添加医生信息和签名状态
                                $record['doctor_info'] = $highLevelDoctors;

                                if (empty($firstBlsyTime)) {
                                    // 未签名
                                    $record['sign_status'] = '未签名';
                                    $invalidRounds[] = $record;
                                } else {
                                    // 检查签名时间是否在当前周期内
                                    $signDate = date('Y-m-d', strtotime($firstBlsyTime));

                                    if (
                                        strtotime($signDate) >= strtotime($cycleStartDate) &&
                                        strtotime($signDate) <= strtotime($cycleEndDate)
                                    ) {
                                        // 有效记录
                                        $record['sign_status'] = '正常';
                                        $validRoundsInCycle[] = $record;
                                    } else {
                                        // 签名时间不在周期内
                                        $record['sign_status'] = '超时';
                                        $invalidRounds[] = $record;
                                    }
                                }
                            }
                        }
                    }

                    // 移动到下一天
                    $currentDate = date('Y-m-d', strtotime($currentDate . ' +1 day'));
                }

                // 如果当前周期内没有有效的上级医师查房记录，记录质控
                if (empty($validRoundsInCycle)) {
                    $cycleItem = [];
                    $cycleItem[] = "周期【" . $cycleStartDate . " 至 " . $cycleEndDate . "】";

                    // 添加医师信息
                    if (!empty($invalidRounds)) {
                        foreach ($invalidRounds as $record) {
                            $doctorInfo = implode("，", $record['doctor_info']);
                            $signInfo = "";

                            if ($record['sign_status'] == '未签名') {
                                $signInfo = "未签名";
                            } else {
                                $signInfo = $record[$firstBlsyTimeField] . "（超时）";
                            }

                            $cycleItem[] = "【" . $record['BLMC'] . "】【" . $doctorInfo . "】首次签名时间【" . $signInfo . "】";
                        }
                    } else {
                        // 当前周期没有任何查房记录
                        $cycleItem[] = "【无】";
                        //$cycleItem[] = "首次签名时间【无】";
                    }

                    // 添加BLBH（如果有）
                    if (!empty($invalidRounds)) {
                        $cycleItem["BLBH"] = $invalidRounds[0]['BLBH'];
                    }

                    // 将这个周期的记录添加到当前医嘱的周期组中
                    $cycleGroups[] = $cycleItem;
                }
            }

            // 如果有需要质控的周期
            if (!empty($cycleGroups)) {
                // 创建当前医嘱的基础信息
                $orderBasis = [];
                $orderBasis[] = $orderInfo;
                $orderBasis[] = $orderInfo1;

                // 创建一个包含所有周期的完整记录
                $allCycleDetails = [];
                foreach ($cycleGroups as $cycle) {
                    // 取出第一个元素(周期信息)
                    $cycleInfo = array_shift($cycle);
                    // 将周期信息添加到基础信息
                    $newCycle = array_merge([$cycleInfo], $cycle);
                    // 添加到所有周期记录
                    $allCycleDetails = array_merge($allCycleDetails, $newCycle);
                }

                // 合并医嘱基础信息和周期详情
                $orderBasisGroups = array_merge($orderBasis, $allCycleDetails);
                $allBasisGroups[] = $orderBasisGroups;
            }
        }

        // 6. 将所有质控结果一次性插入到insertData
        if (!empty($allBasisGroups)) {
            $this->insertData[] = [
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title'],
                'basis' => json_encode($allBasisGroups, JSON_UNESCAPED_UNICODE)
            ];
        }

        return true;
    }

    /**
     * 医嘱名称含"病重"，每两天1次上级医师查房 新（查blmc）
     * @param $caseRule
     * @param $ruleId
     * @param $ZYH
     * @return bool
     * @author lzh
     */
    public function rule1050($caseRule, $ruleId, $ZYH)
    {

        // 1. 获取出院时间和入院时间AAB01，如果没有取当前时间，用这个时间-入院时间如果小与24小时，则不质控
        $exitTime = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->value('AAC01');
        if (empty($exitTime)) {
            $exitTime = Carbon::now()->toDateTimeString();
        }

        // 2. 获取入院时间AAB01
        $enterTime = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->value('AAB01');
        if (empty($enterTime)) {
            return true;
        }


        // 3. 计算出院时间-入院时间
        $enterDate = date('Y-m-d', strtotime($enterTime));
        $exitDate = date('Y-m-d', strtotime($exitTime));
        $diffDays = (strtotime($exitDate) - strtotime($enterDate)) / (24 * 3600);
        if ($diffDays <= 1) {
            return true;
        }

        // 1. 直接设置周期为2天
        $cycleDays = 2;

        // 2. 从MySQL查询医嘱名称含"病重"的医嘱
        $ruleMap8014 = RuleWordMap::getArrayById(8014);
        $yzbQuery = Yzb::query()->where('ZYH', $ZYH)->get()->toArray();
        $yzbData = [];
        foreach ($yzbQuery as $record) {
            foreach ($ruleMap8014 as $keyword) {
                if (strpos($record['YZMC'], $keyword) !== false) {
                    $yzbData[] = $record['YZMC'];
                    break;
                }
            }
        }
        if (empty($yzbData)) {
            return true; // 没有符合条件的医嘱，不进行质控
        }

        // 2. 获取病程记录类型配置
        $ruleMap8049 = RuleWordMap::query()->where('id', '=', 8049)->value('keyword');
        $ruleMap8049 = !empty($ruleMap8049) ? $ruleMap8049 : '50,296'; // 病程记录BLLB值
        //是否包含逗号
        if (strpos($ruleMap8049, ',') !== false) {
            $recordTypes = explode(',', $ruleMap8049);
        } else {
            $recordTypes = [$ruleMap8049];
        }

        // 获取查房记录的时间字段
        $ruleMap8011 = RuleWordMap::query()->where('id', '=', 8011)->value('keyword');
        $ruleMap8011 = !empty($ruleMap8011) ? $ruleMap8011 : 'ZXSJ'; // 查房记录的时间字段名

        // 获取上级医师职称（主任/主治医师）
        $ruleMap8012 = RuleWordMap::query()->where('id', '=', 8012)->value('keyword');
        $ruleMap8012 = !empty($ruleMap8012) ? $ruleMap8012 : '主治,主任'; // 上级医师

        // 判断是否包含逗号
        if (strpos($ruleMap8012, ',') !== false) {
            $highLevelTitles = explode(',', $ruleMap8012);
        } else {
            $highLevelTitles = [$ruleMap8012];
        }

        // 获取首次签名时间字段名
        $firstBlsyTimeField = RuleWordMap::query()->where('id', '=', 8002)->value('keyword');
        $firstBlsyTimeField = !empty($firstBlsyTimeField) ? $firstBlsyTimeField : 'first_blsy_time'; // 修正拼写错误

        //8050
        $ruleMap8050 = RuleWordMap::query()->where('id', '=', 8050)->value('keyword');
        $ruleMap8050 = !empty($ruleMap8050) ? $ruleMap8050 : '操作记录';
        //是否包含逗号
        if (strpos($ruleMap8050, ',') !== false) {
            $operationRecordTypes = explode(',', $ruleMap8050);
        } else {
            $operationRecordTypes = [$ruleMap8050];
        }

        // 收集所有质控结果，按医嘱分组
        $allBasisGroups = [];

        // 4. 处理每条病重医嘱
        foreach ($yzbData as $order) {
            // 获取开嘱和停嘱时间，添加字段存在检查
            $startTime = isset($order['KZSJ']) ? $order['KZSJ'] : '';
            $endTime = isset($order['TZSJ']) ? $order['TZSJ'] : date('Y-m-d H:i:s'); // 如果没有停嘱时间，使用当前时间
            $yzmc = isset($order['YZMC']) ? $order['YZMC'] : '未知医嘱';

            // 检查开嘱时间是否有效
            if (empty($startTime) || $startTime == '1970-01-01 00:00:00' || $startTime == '0000-00-00 00:00:00') {
                continue; // 跳过无效的开嘱时间
            }

            // 计算开嘱日期到停嘱日期的天数
            $startDate = date('Y-m-d', strtotime($startTime));
            $endDate = date('Y-m-d', strtotime($endTime));
            $totalDays = ceil((strtotime($endDate) - strtotime($startDate)) / (24 * 3600)) + 1;
            $totalCycles = ceil($totalDays / $cycleDays);

            // 获取包含这个时间段的所有查房记录
            try {
                $bl01Data = EMR_BL_BL01::query()
                    ->where('JZHM', $ZYH)
                    ->whereIn('MBLB', $recordTypes)
                    ->where($ruleMap8011, '>=', $startDate . ' 00:00:00')
                    ->where($ruleMap8011, '<=', $endDate . ' 23:59:59')
                    ->get()
                    ->toArray();
            } catch (\Exception $e) {
                Log::error("rule-$ruleId-$ZYH-error: " . $e->getMessage());
                continue; // 查询出错时跳过当前医嘱
            }

            // 按日期分组查房记录
            $roundsByDate = [];
            foreach ($bl01Data as $record) {
                if (!isset($record[$ruleMap8011])) {
                    continue; // 跳过没有执行时间的记录
                }
                foreach ($operationRecordTypes as $operationRecordType) {
                    if (strpos($record['BLMC'], $operationRecordType) !== false) {
                        continue 2;
                    }
                }
                $recordDate = date('Y-m-d', strtotime($record[$ruleMap8011]));
                if (!isset($roundsByDate[$recordDate])) {
                    $roundsByDate[$recordDate] = [];
                }
                $roundsByDate[$recordDate][] = $record;
            }

            // 医嘱信息
            $orderInfo = "医嘱名称【" . $yzmc . "】";
            $orderInfo1 = "开嘱时间【" . $startTime . "】停嘱时间【" . $endTime . "】";

            // 为当前医嘱创建质控记录组
            $cycleGroups = [];

            // 5. 循环检查每个周期
            for ($i = 0; $i < $totalCycles; $i++) {
                // 计算周期开始日期
                $cycleStartDate = date('Y-m-d', strtotime($startDate . " +" . ($i * $cycleDays) . " days"));
                // 计算周期结束日期（不超过医嘱结束日期）
                $cycleEndTimestamp = min(strtotime($cycleStartDate . " +" . ($cycleDays - 1) . " days"), strtotime($endDate));
                $cycleEndDate = date('Y-m-d', $cycleEndTimestamp);

                //先查询当前周期内是否有死亡医嘱，如果有就跳过这个周期
                $starttime = $cycleStartDate . ' 00:00:00';
                $endtime = $cycleEndDate . ' 23:59:59';

                // 从MySQL查询死亡医嘱
                $yzbQuery = Yzb::query()
                    ->where('ZYH', $ZYH)
                    ->where('KZSJ', '>=', $starttime)
                    ->where('KZSJ', '<=', $endtime)
                    ->get()->toArray();
                $ruleMap8003 = RuleWordMap::getArrayById(8003);
                $yzbData = [];
                foreach ($yzbQuery as $record) {
                    foreach ($ruleMap8003 as $keyword) {
                        if (strpos($record['YZMC'], $keyword) !== false) {
                            $yzbData[] = $record['YZMC'];
                            break;
                        }
                    }
                }

                if (!empty($yzbData)) {
                    continue;
                }

                // 获取当前周期内的有效查房记录
                $validRoundsInCycle = [];
                $invalidRounds = [];

                // 遍历周期内的每一天
                $currentDate = $cycleStartDate;
                while (strtotime($currentDate) <= strtotime($cycleEndDate)) {
                    if (isset($roundsByDate[$currentDate])) {
                        foreach ($roundsByDate[$currentDate] as $record) {
                            // 检查医生职称

                            $highLevelDoctors = [];

                            $blmc = $record['BLMC'];
                            // 检查是否是上级医师
                            foreach ($highLevelTitles as $title) {
                                if (strpos($blmc, $title) !== false) {
                                    $highLevelDoctors[] = $title;
                                    break;
                                }
                            }

                            // 如果有上级医师
                            if (!empty($highLevelDoctors)) {
                                // 检查签名时间
                                $firstBlsyTime = isset($record[$firstBlsyTimeField]) ? $record[$firstBlsyTimeField] : '';

                                // 添加医生信息和签名状态
                                $record['doctor_info'] = $highLevelDoctors;

                                if (empty($firstBlsyTime)) {
                                    // 未签名
                                    $record['sign_status'] = '未签名';
                                    $invalidRounds[] = $record;
                                } else {
                                    // 检查签名时间是否在当前周期内
                                    $signDate = date('Y-m-d', strtotime($firstBlsyTime));

                                    if (
                                        strtotime($signDate) >= strtotime($cycleStartDate) &&
                                        strtotime($signDate) <= strtotime($cycleEndDate)
                                    ) {
                                        // 有效记录
                                        $record['sign_status'] = '正常';
                                        $validRoundsInCycle[] = $record;
                                    } else {
                                        // 签名时间不在周期内
                                        $record['sign_status'] = '超时';
                                        $invalidRounds[] = $record;
                                    }
                                }
                            }
                        }
                    }

                    // 移动到下一天
                    $currentDate = date('Y-m-d', strtotime($currentDate . ' +1 day'));
                }
                // 如果当前周期内没有有效的上级医师查房记录，记录质控
                if (empty($validRoundsInCycle)) {
                    $cycleItem = [];
                    $cycleItem[] = "周期【" . $cycleStartDate . " 至 " . $cycleEndDate . "】";

                    // 添加医师信息
                    if (!empty($invalidRounds)) {
                        foreach ($invalidRounds as $record) {
                            $doctorInfo = implode("，", $record['doctor_info']);
                            $signInfo = "";

                            if ($record['sign_status'] == '未签名') {
                                $signInfo = "未签名";
                            } else {
                                $signInfo = $record[$firstBlsyTimeField] . "（超时）";
                            }

                            $cycleItem[] = "【" . $record['BLMC'] . "】【" . $doctorInfo . "】首次签名时间【" . $signInfo . "】";
                        }
                    } else {
                        // 当前周期没有任何查房记录
                        $cycleItem[] = "【无】";
                        //$cycleItem[] = "首次签名时间【无】";
                    }

                    // 添加BLBH（如果有）
                    if (!empty($invalidRounds)) {
                        $cycleItem["BLBH"] = $invalidRounds[0]['BLBH'];
                    }

                    // 将这个周期的记录添加到当前医嘱的周期组中
                    $cycleGroups[] = $cycleItem;
                }
            }

            // 如果有需要质控的周期
            if (!empty($cycleGroups)) {
                // 创建当前医嘱的基础信息
                $orderBasis = [];
                $orderBasis[] = $orderInfo;
                $orderBasis[] = $orderInfo1;

                // 创建一个包含所有周期的完整记录
                $allCycleDetails = [];
                foreach ($cycleGroups as $cycle) {
                    // 取出第一个元素(周期信息)
                    $cycleInfo = array_shift($cycle);
                    // 将周期信息添加到基础信息
                    $newCycle = array_merge([$cycleInfo], $cycle);
                    // 添加到所有周期记录
                    $allCycleDetails = array_merge($allCycleDetails, $newCycle);
                }

                // 合并医嘱基础信息和周期详情
                $orderBasisGroups = array_merge($orderBasis, $allCycleDetails);
                $allBasisGroups[] = $orderBasisGroups;
            }
        }


        // 6. 将所有质控结果一次性插入到insertData
        if (!empty($allBasisGroups)) {
            $this->insertData[] = [
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title'],
                'basis' => json_encode($allBasisGroups, JSON_UNESCAPED_UNICODE)
            ];
        }

        return true;
    }

    /**
     * 医嘱名称不包含"病重"和"病危"，每3天1次上级医师查房
     * @param $caseRule
     * @param $ruleId
     * @param $ZYH
     * @return bool
     * @author lzh
     */
    public function rule132($caseRule, $ruleId, $ZYH)
    {

        // 1. 直接设置周期为3天
        $cycleDays = 3;

        // 2. 从MySQL查询医嘱，检查是否有病重或病危医嘱
        // 获取病重和病危关键词
        $ruleMap8014 = RuleWordMap::query()->where('id', '=', 8014)->value('keyword');
        $ruleMap8014 = !empty($ruleMap8014) ? $ruleMap8014 : '病重';

        $ruleMap8015 = RuleWordMap::query()->where('id', '=', 8015)->value('keyword');
        $ruleMap8015 = !empty($ruleMap8015) ? $ruleMap8015 : '病危';

        // 2. 获取病程记录类型配置
        $ruleMap8049 = RuleWordMap::query()->where('id', '=', 8049)->value('keyword');
        $ruleMap8049 = !empty($ruleMap8049) ? $ruleMap8049 : '50,296'; // 病程记录BLLB值
        //是否包含逗号
        if (strpos($ruleMap8049, ',') !== false) {
            $recordTypes = explode(',', $ruleMap8049);
        } else {
            $recordTypes = [$ruleMap8049];
        }

        // 构建查询条件：查询住院号为$ZYH且医嘱名称包含病重或病危的医嘱
        $yzbQuery = Yzb::query()->where('ZYH', $ZYH)->get()->toArray();
        $yzbData = [];
        foreach ($yzbQuery as $record) {
            if (strpos($record['YZMC'], $ruleMap8014) !== false || strpos($record['YZMC'], $ruleMap8015) !== false) {
                $yzbData[] = $record['YZMC'];
            }
        }

        // 如果有病重或病危医嘱，则不进行此规则的质控
        if (!empty($yzbData)) {
            return true;
        }

        // 3. 获取入院时间和出院时间
        try {
            $enterTime = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->value('AAB01');
            $dischargeTime = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->value('AAC01');

            // 如果没有出院时间或出院时间为默认值，使用当前时间
            if (empty($dischargeTime) || $dischargeTime == '1970-01-01 00:00:00' || $dischargeTime == '0000-00-00 00:00:00') {
                $dischargeTime = date('Y-m-d H:i:s');
            }

            // 检查入院时间是否有效
            if (empty($enterTime) || $enterTime == '1970-01-01 00:00:00' || $enterTime == '0000-00-00 00:00:00') {
                return true; // 没有有效的入院时间
            }
        } catch (\Exception $e) {
            Log::error("rule-$ruleId-$ZYH-error: " . $e->getMessage());
            return true; // 查询出错，跳过质控
        }

        // 4. 计算住院天数
        $enterDate = date('Y-m-d', strtotime($enterTime));
        $enterTimestamp = strtotime($enterDate);
        $dischargeDate = date('Y-m-d', strtotime($dischargeTime));
        $dischargeTimestamp = strtotime($dischargeDate);
        $totalDays = ceil(($dischargeTimestamp - $enterTimestamp) / (24 * 3600)) + 1;
        $totalCycles = ceil($totalDays / $cycleDays);

        // 如果住院天数不足3天，不进行质控
        if ($totalDays < $cycleDays) {
            return true;
        }

        // 6. 获取查房记录的时间字段
        $ruleMap8011 = RuleWordMap::query()->where('id', '=', 8011)->value('keyword');
        $ruleMap8011 = !empty($ruleMap8011) ? $ruleMap8011 : 'ZXSJ'; // 查房记录的时间字段名

        // 7. 获取上级医师职称（主任/主治医师）
        $ruleMap8012 = RuleWordMap::query()->where('id', '=', 8012)->value('keyword');
        $ruleMap8012 = !empty($ruleMap8012) ? $ruleMap8012 : '主治,主任'; // 上级医师

        // 判断是否包含逗号
        if (strpos($ruleMap8012, ',') !== false) {
            $highLevelTitles = explode(',', $ruleMap8012);
        } else {
            $highLevelTitles = [$ruleMap8012];
        }

        // 8. 获取首次签名时间字段名
        $firstBlsyTimeField = RuleWordMap::query()->where('id', '=', 8002)->value('keyword');
        $firstBlsyTimeField = !empty($firstBlsyTimeField) ? $firstBlsyTimeField : 'first_blsy_time';

        //8050
        $ruleMap8050 = RuleWordMap::query()->where('id', '=', 8050)->value('keyword');
        $ruleMap8050 = !empty($ruleMap8050) ? $ruleMap8050 : '操作记录';
        //是否包含逗号
        if (strpos($ruleMap8050, ',') !== false) {
            $operationRecordTypes = explode(',', $ruleMap8050);
        } else {
            $operationRecordTypes = [$ruleMap8050];
        }

        // 9. 获取所有查房记录
        try {
            $bl01Data = EMR_BL_BL01::query()
                ->where('JZHM', $ZYH)
                ->whereIn('MBLB', $recordTypes)
                ->where($ruleMap8011, '>=', $enterTime)
                ->where($ruleMap8011, '<=', $dischargeTime)
                ->get()
                ->toArray();
        } catch (\Exception $e) {
            Log::error("rule-$ruleId-$ZYH-error: " . $e->getMessage());
            return true; // 查询出错，跳过质控
        }

        // 收集所有质控结果
        $allBasisGroups = [];

        // 10. 按周期检查查房记录
        for ($i = 0; $i < $totalCycles; $i++) {
            // 周期开始日期
            $cycleStartDate = date('Y-m-d', $enterTimestamp + $i * $cycleDays * 24 * 3600);
            $cycleStartTime = $cycleStartDate . ' 00:00:00';

            // 周期结束日期（不超过出院日期）
            $cycleEndTimestamp = min(strtotime($cycleStartDate . " +" . ($cycleDays - 1) . " days"), $dischargeTimestamp);
            $cycleEndDate = date('Y-m-d', $cycleEndTimestamp);
            $cycleEndTime = $cycleEndDate . ' 23:59:59';

            // 计算当前周期实际天数
            $cycleDaysActual = ceil(($cycleEndTimestamp - strtotime($cycleStartDate)) / (24 * 3600)) + 1;

            // 如果剩余周期不足三天，则不进行质控
            if ($cycleDaysActual < $cycleDays) {
                continue;
            }

            // 查找当前周期内的查房记录
            $validRoundsInCycle = [];
            $invalidRounds = [];


            // 按日期分组查房记录
            $roundsByDate = [];
            foreach ($bl01Data as $record) {
                if (!isset($record[$ruleMap8011])) {
                    continue; // 跳过没有执行时间的记录
                }
                foreach ($operationRecordTypes as $operationRecordType) {
                    if (strpos($record['BLMC'], $operationRecordType) !== false) {
                        continue 2;
                    }
                }

                $recordTime = $record[$ruleMap8011];
                // 只检查在当前周期内的记录
                if (
                    strtotime($recordTime) >= strtotime($cycleStartTime) &&
                    strtotime($recordTime) <= strtotime($cycleEndTime)
                ) {
                    $recordDate = date('Y-m-d', strtotime($recordTime));
                    if (!isset($roundsByDate[$recordDate])) {
                        $roundsByDate[$recordDate] = [];
                    }
                    $roundsByDate[$recordDate][] = $record;
                }
            }

            // 遍历周期内的每一天
            $currentDate = $cycleStartDate;
            while (strtotime($currentDate) <= strtotime($cycleEndDate)) {
                if (isset($roundsByDate[$currentDate])) {
                    foreach ($roundsByDate[$currentDate] as $record) {
                        /* // 检查医生职称
                        try {
                            $blsy = EMR_BL_BLSY::query()->where('BLBH', $record['BLBH'])->pluck('SYYS')->toArray();
                            $blsy = array_unique($blsy);
                        } catch (\Exception $e) {
                            Log::error("rule-$ruleId-$ZYH-error: " . $e->getMessage());
                            continue; // 查询出错时跳过当前记录
                        }

                        $highLevelDoctors = [];

                        foreach ($blsy as $doctor) {
                            try {
                                $staffInfo = STAFF::query()->where('code', $doctor)->first();
                                if (!$staffInfo) continue;

                                $staff = $staffInfo->ygjb_text;
                                $doctorName = $staffInfo->name;

                                // 检查是否是上级医师
                                foreach ($highLevelTitles as $title) {
                                    if (strpos($staff, $title) !== false) {
                                        $highLevelDoctors[] = $doctorName . '（' . $staff . '）';
                                        break;
                                    }
                                }
                            } catch (\Exception $e) {
                                Log::error("rule-$ruleId-$ZYH-error: " . $e->getMessage());
                                continue; // 查询出错时跳过当前医生
                            }
                        } */

                        // 如果有上级医师
                        //if (!empty($highLevelDoctors)) {
                        // 检查签名时间
                        $firstBlsyTime = isset($record[$firstBlsyTimeField]) ? $record[$firstBlsyTimeField] : '';

                        // 添加医生信息和签名状态
                        //$record['doctor_info'] = $highLevelDoctors;

                        if (empty($firstBlsyTime)) {
                            // 未签名
                            $record['sign_status'] = '未签名';
                            $invalidRounds[] = $record;
                        } else {
                            // 检查签名时间是否在当前周期内
                            $signDate = date('Y-m-d', strtotime($firstBlsyTime));

                            if (
                                strtotime($signDate) >= strtotime($cycleStartDate) &&
                                strtotime($signDate) <= strtotime($cycleEndDate)
                            ) {
                                // 有效记录
                                $record['sign_status'] = '正常';
                                $validRoundsInCycle[] = $record;
                            } else {
                                // 签名时间不在周期内
                                $record['sign_status'] = '超时';
                                $invalidRounds[] = $record;
                            }
                        }
                        //}
                    }
                }

                // 移动到下一天
                $currentDate = date('Y-m-d', strtotime($currentDate . ' +1 day'));
            }

            // 如果当前周期内没有有效的上级医师查房记录，记录质控
            if (empty($validRoundsInCycle)) {
                $basis = [];
                $basis[] = "周期【" . $cycleStartDate . " 至 " . $cycleEndDate . "】";

                // 添加医师信息
                if (!empty($invalidRounds)) {
                    foreach ($invalidRounds as $record) {
                        //$doctorInfo = implode("，", $record['doctor_info']);
                        $signInfo = "";

                        if ($record['sign_status'] == '未签名') {
                            $signInfo = "未签名";
                        } else {
                            $signInfo = $record[$firstBlsyTimeField] . "（超时）";
                        }

                        $basis[] = "【" . $record['BLMC'] . "】首次签名时间【" . $signInfo . "】";
                    }

                    // 添加BLBH（如果有）
                    if (!empty($invalidRounds)) {
                        $basis["BLBH"] = $invalidRounds[0]['BLBH'];
                    }
                } else {
                    // 当前周期没有任何查房记录
                    $basis[] = "【无】";
                    //$basis[] = "首次签名时间【无】";
                }

                // 收集当前周期的质控依据
                $allBasisGroups[] = $basis;
            }
        }

        // 11. 将所有质控结果一次性插入到insertData
        if (!empty($allBasisGroups)) {
            $this->insertData[] = [
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title'],
                'basis' => json_encode($allBasisGroups, JSON_UNESCAPED_UNICODE) // 将整个组作为一个数组传入
            ];
        }

        return true;
    }

    /**
     * 术前小结及术前讨论结论记录
     * @param $caseRule
     * @param $ruleId
     * @param $ZYH
     * @return bool
     * @author lzh
     * @datetime 2025-05-10
     */
    public function rule1001($caseRule, $ruleId, $ZYH)
    {

        // 1. 查询手术信息，获取手术开始时间
        try {
            $ssapData = SM_SSAP::query()->where('ZYH', '=', $ZYH)->orderBy('SSRQ', 'asc')->groupBy('SSRQ')->get()->toArray();
            if (empty($ssapData)) {
                return true; // 没有手术记录，不进行质控
            }
        } catch (\Exception $e) {
            Log::error("rule-$ruleId-$ZYH-error: " . $e->getMessage());
            return true; // 查询出错，跳过质控
        }

        // 2. 获取术前小结及术前讨论结论记录的配置
        $ruleMap8016 = RuleWordMap::query()->where('id', '=', 8016)->value('keyword');
        $ruleMap8016 = !empty($ruleMap8016) ? $ruleMap8016 : '82,304'; // 术前小结及术前讨论结论记录MBLB值

        // 判断是否包含逗号
        if (strpos($ruleMap8016, ',') !== false) {
            $recordTypes = explode(',', $ruleMap8016);
        } else {
            $recordTypes = [$ruleMap8016];
        }

        // 3. 获取查询字段
        // 获取执行时间字段名
        $ruleMap8011 = RuleWordMap::query()->where('id', '=', 8011)->value('keyword');
        $ruleMap8011 = !empty($ruleMap8011) ? $ruleMap8011 : 'ZXSJ'; // 执行时间字段名

        // 获取首次签名时间字段名
        $firstBlsyTimeField = RuleWordMap::query()->where('id', '=', 8002)->value('keyword');
        $firstBlsyTimeField = !empty($firstBlsyTimeField) ? $firstBlsyTimeField : 'frist_blsy_time';

        //获取8047,8048
        $excludeKeywords8047 = RuleWordMap::getArrayById(8047);

        // 收集所有质控结果
        $allBasisGroups = [];
        //是否是多台手术
        $isMultiSurgery = false;
        $surgerySTime = '';

        // 4. 处理每个手术记录
        foreach ($ssapData as $surgery) {
            // 获取手术开始时间
            $surgeryStartTime = $surgery['SSRQ'];
            //手术名称
            $surgeryName = $surgery['ICD9_SSCZMC'];
            if (empty($surgery['SSRQ']) || empty($surgery['ICD9_SSCZMC']) || empty($surgery['JSRQ']) || strpos($surgery['JSRQ'], '1970-01-01') !== false || $surgery['ICD9_SSCZMC'] == 'NULL') {
                continue;
            }
            //根据手术名称查询SSCZ的SSLB
            $ssCZ = SSCZ::query()->where('SSMC', $surgeryName)->value('SSLB');
            //检查是否是手术+介入
            if (!in_array($ssCZ, $excludeKeywords8047)) {
                continue;
            }
            // 检查手术开始时间是否有效
            if (empty($surgeryStartTime) || $surgeryStartTime == '1970-01-01 00:00:00' || $surgeryStartTime == '0000-00-00 00:00:00') {
                continue; // 跳过无效的手术时间
            }

            // 5. 查询术前小结及术前讨论结论记录
            try {
                $records = EMR_BL_BL01::query()
                    ->where('JZHM', $ZYH)
                    ->whereIn('MBLB', $recordTypes);
                /* if ($isMultiSurgery && !empty($surgerySTime)) {
                    $records = $records->where($ruleMap8011, '>=', $surgerySTime)->where($ruleMap8011, '<=', $surgeryStartTime);
                } else { */
                $records = $records->where($ruleMap8011, '>=', date('Y-m-d 00:00:00', strtotime($surgeryStartTime) - 24 * 3600))->where($ruleMap8011, '<=', $surgeryStartTime); //前一天0点到开始时间
                //}
                $records = $records->get()->toArray();
            } catch (\Exception $e) {
                Log::error("rule-$ruleId-$ZYH-error: " . $e->getMessage());
                continue; // 查询出错，跳过当前手术记录
            }

            // 如果没有找到记录
            if (empty($records)) {
                continue;
            }

            // 6. 检查每条记录的签名时间
            foreach ($records as $record) {
                $firstBlsyTime = isset($record[$firstBlsyTimeField]) ? $record[$firstBlsyTimeField] : '';

                // 构建基础信息
                $basis = [];
                $basis[] = "手术名称【" . $surgeryName . "】";
                $basis[] = "(手麻)手术开始时间【" . $surgeryStartTime . "】";

                // 检查是否有签名
                if (empty($firstBlsyTime)) {
                    // 未签名
                    $basis[] = "【" . $record['BLMC'] . "】【未签名】";
                    $basis["BLBH"] = $record['BLBH'];
                    $allBasisGroups[] = $basis;
                } // 检查签名时间是否在手术开始前
                else if (strtotime($firstBlsyTime) > strtotime($surgeryStartTime)) {
                    // 签名时间晚于手术开始时间
                    $basis[] = "【" . $record['BLMC'] . "】首次签名时间【" . $firstBlsyTime . "（超时）】";
                    $basis["BLBH"] = $record['BLBH'];
                    $allBasisGroups[] = $basis;
                }
            }
            $isMultiSurgery = true;
            $surgerySTime = $surgery['JSRQ'];
        }

        // 7. 将所有质控结果一次性插入到insertData
        if (!empty($allBasisGroups)) {
            $this->insertData[] = [
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title'],
                'basis' => json_encode($allBasisGroups, JSON_UNESCAPED_UNICODE)
            ];
        }

        return true;
    }

    /**
     * 无术前小结及术前讨论结论记录
     * @param $caseRule
     * @param $ruleId
     * @param $ZYH
     * @return bool
     * @author lzh
     * @datetime 2025-05-10
     */
    public function rule1227($caseRule, $ruleId, $ZYH)
    {
        $brry = ZY_BRRY::query()->where('ZYH', $ZYH)->get(['AAB01', 'AAC01'])->toArray();
        if (date('d', strtotime($brry[0]['AAC01'])) - date('d', strtotime($brry[0]['AAB01'])) <= 1) {
            return [];
        }

        // 1. 查询手术信息，获取手术开始时间
        try {
            $ssapData = SM_SSAP::query()->where('ZYH', '=', $ZYH)->orderBy('SSRQ', 'asc')->groupBy('SSRQ')->get()->toArray();
            if (empty($ssapData)) {
                return true; // 没有手术记录，不进行质控
            }
        } catch (\Exception $e) {
            Log::error("rule-$ruleId-$ZYH-error: " . $e->getMessage());
            return true; // 查询出错，跳过质控
        }

        // 2. 获取术前小结及术前讨论结论记录的配置
        $recordTypes = RuleWordMap::getArrayById(8016);

        // 3. 获取查询字段
        // 获取执行时间字段名
        $ruleMap8011 = RuleWordMap::query()->where('id', '=', 8011)->value('keyword');
        $ruleMap8011 = !empty($ruleMap8011) ? $ruleMap8011 : 'ZXSJ'; // 执行时间字段名

        // 获取首次签名时间字段名
        $firstBlsyTimeField = RuleWordMap::query()->where('id', '=', 8002)->value('keyword');
        $firstBlsyTimeField = !empty($firstBlsyTimeField) ? $firstBlsyTimeField : 'frist_blsy_time';

        //获取8047,8048
        $excludeKeywords8047 = RuleWordMap::getArrayById(8047);

        // 收集所有质控结果
        $allBasisGroups = [];
        //是否是多台手术

        // 4. 处理每个手术记录
        foreach ($ssapData as $surgery) {
            // 获取手术开始时间
            $surgeryStartTime = $surgery['SSRQ'];
            //手术名称
            $surgeryName = $surgery['ICD9_SSCZMC'];
            if (empty($surgery['SSRQ']) || empty($surgery['ICD9_SSCZMC']) || empty($surgery['JSRQ']) || strpos($surgery['JSRQ'], '1970-01-01') !== false || $surgery['ICD9_SSCZMC'] == 'NULL') {
                continue;
            }

            //根据手术名称查询SSCZ的SSLB
            $ssCZ = SSCZ::query()->where('SSMC', $surgeryName)->value('SSLB');
            if (empty($ssCZ)) {
                continue;
            }
            //检查是否是手术+介入
            if (!in_array($ssCZ, $excludeKeywords8047)) {
                continue;
            }
            // 检查手术开始时间是否有效
            if (empty($surgeryStartTime) || $surgeryStartTime == '1970-01-01 00:00:00' || $surgeryStartTime == '0000-00-00 00:00:00') {
                continue; // 跳过无效的手术时间
            }

            // 5. 查询术前小结及术前讨论结论记录
            $records = EMR_BL_BL01::query()
                ->where('JZHM', $ZYH)
                ->whereIn('MBLB', $recordTypes)
                ->where($ruleMap8011, '<', $surgeryStartTime); //前一天0点到开始时间
            $records = $records->get()->toArray();

            // 如果没有找到记录
            if (empty($records)) {
                // 记录质控结果 - 未找到记录
                $basis = [];
                $basis[] = "手术名称【" . $surgeryName . "】";
                $basis[] = "手术开始时间【" . $surgeryStartTime . "】前，无术前小结";

                $allBasisGroups[] = $basis;
            }
        }

        // 7. 将所有质控结果一次性插入到insertData
        if (!empty($allBasisGroups)) {
            $this->insertData[] = [
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title'],
                'basis' => json_encode($allBasisGroups, JSON_UNESCAPED_UNICODE)
            ];
        }

        return true;
    }

    /**
     * 术后首次病程记录
     * @param $caseRule
     * @param $ruleId
     * @param $ZYH
     * @return bool
     * @author lzh
     * @datetime 2025-05-10
     */
    public function rule1002($caseRule, $ruleId, $ZYH)
    {

        // 1. 查询手术信息，获取手术结束时间
        try {
            //获取出入院时间
            $enterTime = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->value('AAB01');
            $exitTime = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->value('AAC01');

            // 如果没有出院时间或出院时间为默认值，使用当前时间
            if (empty($exitTime) || $exitTime == '1970-01-01 00:00:00' || $exitTime == '0000-00-00 00:00:00') {
                $exitTime = date('Y-m-d H:i:s');
            }

            // 3. 计算出院时间-入院时间
            $enterDate = date('Y-m-d', strtotime($enterTime));
            $exitDate = date('Y-m-d', strtotime($exitTime));
            $diffDays = (strtotime($exitDate) - strtotime($enterDate)) / (24 * 3600);
            if ($diffDays <= 1) {
                return true;
            }


            $ssapData = SM_SSAP::query()->where('ZYH', '=', $ZYH)->get()->toArray();
            if (empty($ssapData)) {
                return true; // 没有手术记录，不进行质控
            }
        } catch (\Exception $e) {
            Log::error("rule-$ruleId-$ZYH-error: " . $e->getMessage());
            return true; // 查询出错，跳过质控
        }

        // 2. 获取术后首次病程记录的配置
        $ruleMap8017 = RuleWordMap::query()->where('id', '=', 8017)->value('keyword');
        $ruleMap8017 = !empty($ruleMap8017) ? $ruleMap8017 : '42'; // 术后首次病程记录MBLB值
        //逗号分割
        if (strpos($ruleMap8017, ',') !== false) {
            $recordTypes = explode(',', $ruleMap8017);
        } else {
            $recordTypes = [$ruleMap8017];
        }

        $ruleMap8010 = RuleWordMap::query()->where('id', '=', 8010)->value('keyword');
        $ruleMap8010 = !empty($ruleMap8010) ? $ruleMap8010 : '294'; // 病程记录BLLB值

        $ruleMap8018 = RuleWordMap::query()->where('id', '=', 8018)->value('keyword');
        $ruleMap8018 = !empty($ruleMap8018) ? $ruleMap8018 : '术后首次病程记录'; // 病程记录标题包含的关键词

        // 3. 获取查询字段
        // 获取执行时间字段名
        $ruleMap8011 = RuleWordMap::query()->where('id', '=', 8011)->value('keyword');
        $ruleMap8011 = !empty($ruleMap8011) ? $ruleMap8011 : 'ZXSJ'; // 执行时间字段名

        // 获取首次签名时间字段名
        $firstBlsyTimeField = RuleWordMap::query()->where('id', '=', 8002)->value('keyword');
        $firstBlsyTimeField = !empty($firstBlsyTimeField) ? $firstBlsyTimeField : 'first_blsy_time';

        //获取8047,8048
        $excludeKeywords8047 = RuleWordMap::getArrayById(8047);

        // 收集所有质控结果
        $allBasisGroups = [];

        // 4. 处理每个手术记录
        foreach ($ssapData as $surgery) {
            // 获取手术结束时间
            $surgeryEndTime = $surgery['JSRQ'];
            //获取当前时间
            $currentDate = Carbon::now()->toDateTimeString();
            $sixHoursAfterSurgery = date('Y-m-d H:i:s', strtotime($surgeryEndTime) + 6 * 3600);
            //如果当前时间小于手术结束时间+6小时就跳过
            if (strtotime($currentDate) < strtotime($sixHoursAfterSurgery)) {
                continue;
            }
            //手术名称
            $surgeryName = $surgery['ICD9_SSCZMC'];
            if (empty($surgery['SSRQ']) || empty($surgery['ICD9_SSCZMC']) || empty($surgery['JSRQ']) || strpos($surgery['JSRQ'], '1970-01-01') !== false || $surgery['ICD9_SSCZMC'] == 'NULL') {
                continue;
            }
            //根据手术名称查询SSCZ的SSLB
            $ssCZ = SSCZ::query()->where('SSMC', $surgeryName)->value('SSLB');
            if (empty($ssCZ)) {
                continue;
            }
            //检查是否是手术+介入
            if (!in_array($ssCZ, $excludeKeywords8047)) {
                continue;
            }
            // 检查手术结束时间是否有效
            if (empty($surgeryEndTime) || $surgeryEndTime == '1970-01-01 00:00:00' || $surgeryEndTime == '0000-00-00 00:00:00') {
                continue; // 跳过无效的手术时间
            }

            // 计算手术结束后6小时的时间点
            $sixHoursAfterSurgery = date('Y-m-d H:i:s', strtotime($surgeryEndTime) + 6 * 3600);

            //使用sql查询
            $records = null;

            $recordsmblb = EMR_BL_BL01::query()
                ->where('JZHM', $ZYH)
                ->whereIn('MBLB', $recordTypes)
                ->where($ruleMap8011, '>=', $surgeryEndTime)
                ->where($ruleMap8011, '<=', $sixHoursAfterSurgery)
                ->get()->toArray();


            if (!empty($recordsmblb)) {
                $records = $recordsmblb;
            } else {
                $recordsbllb = EMR_BL_BL01::query()
                    ->where('JZHM', $ZYH)
                    ->where('BLLB', $ruleMap8010)
                    ->where('BLMC', 'like', '%' . $ruleMap8018 . '%')
                    ->where($ruleMap8011, '>=', $surgeryEndTime)
                    ->where($ruleMap8011, '<=', $sixHoursAfterSurgery)
                    ->get()->toArray();
                $records = $recordsbllb;
            }


            // 如果没有找到记录
            if (empty($records)) {
                // 记录质控结果 - 未找到记录
                $basis = [];
                $basis[] = "手术名称【" . $surgeryName . "】";
                $basis[] = "(手麻)手术结束时间【" . $surgeryEndTime . "】";
                $basis[] = "【术后首次病程记录】【未书写】";

                $allBasisGroups[] = $basis;
                continue;
            }

            // 6. 检查每条记录的签名时间
            $hasValidRecord = false;
            $invalidRecords = [];

            foreach ($records as $record) {
                if (isset($record[$firstBlsyTimeField])) {
                    $firstBlsyTime = $record[$firstBlsyTimeField];
                } else {
                    continue;
                }

                // 检查是否有签名
                $blsy = EMR_BL_BLSY::query()
                    ->where('BLBH', $record['BLBH'])
                    ->first();
                if (empty($blsy)) {
                    // 未签名
                    $record['sign_status'] = '未签名';
                    $invalidRecords[] = $record;
                } // 检查签名时间是否在手术后6小时内
                else if (!empty($firstBlsyTime) && strtotime($firstBlsyTime) > strtotime($sixHoursAfterSurgery)) {
                    // 签名时间超过规定时间
                    $record['sign_status'] = '超时';
                    $invalidRecords[] = $record;
                } else {
                    // 有效记录
                    $hasValidRecord = true;
                }
            }

            // 如果没有有效记录，记录质控结果
            if (!$hasValidRecord && !empty($invalidRecords)) {
                foreach ($invalidRecords as $record) {
                    $basis = [];
                    $basis[] = "手术名称【" . $surgeryName . "】";
                    $basis[] = "(手麻)手术结束时间【" . $surgeryEndTime . "】";

                    if ($record['sign_status'] == '未签名') {
                        $basis[] = "【" . $record['BLMC'] . "】【未签名】";
                    } else {
                        $basis[] = "【" . $record['BLMC'] . "】首次签名时间【" . $record[$firstBlsyTimeField] . "（超时）】";
                    }

                    // 添加BLBH（如果有）
                    $basis["BLBH"] = $record['BLBH'];

                    $allBasisGroups[] = $basis;
                }
            }
        }

        // 7. 将所有质控结果一次性插入到insertData
        if (!empty($allBasisGroups)) {
            $this->insertData[] = [
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title'],
                'basis' => json_encode($allBasisGroups, JSON_UNESCAPED_UNICODE)
            ];
        }

        return true;
    }

    /**
     * 手术记录
     * @param $caseRule
     * @param $ruleId
     * @param $ZYH
     * @return bool
     * @author lzh
     * @datetime 2025-05-10
     */
    public function rule1003($caseRule, $ruleId, $ZYH)
    {

        // 1. 查询手术信息，获取手术结束时间
        $ssapData = SM_SSAP::query()->where('ZYH', '=', $ZYH)->get()->toArray();
        if (empty($ssapData)) {
            return true; // 没有手术记录，不进行质控
        }

        // 2. 获取手术记录的配置
        $ruleMap8019 = RuleWordMap::query()->where('id', '=', 8019)->value('keyword');
        $ruleMap8019 = !empty($ruleMap8019) ? $ruleMap8019 : '306'; // 手术记录MBLB值
        //逗号分割
        if (strpos($ruleMap8019, ',') !== false) {
            $recordTypes = explode(',', $ruleMap8019);
        } else {
            $recordTypes = [$ruleMap8019];
        }

        $ruleMap8021 = RuleWordMap::query()->where('id', '=', 8021)->value('keyword');
        $ruleMap8021 = !empty($ruleMap8021) ? $ruleMap8021 : '303'; // 手术记录bllb

        $ruleMap8020 = RuleWordMap::query()->where('id', '=', 8020)->value('keyword');
        $ruleMap8020 = !empty($ruleMap8020) ? $ruleMap8020 : '手术记录'; // 手术记录标题包含的关键词

        // 3. 获取查询字段
        // 获取执行时间字段名
        $ruleMap8011 = RuleWordMap::query()->where('id', '=', 8011)->value('keyword');
        $ruleMap8011 = !empty($ruleMap8011) ? $ruleMap8011 : 'ZXSJ'; // 执行时间字段名

        // 获取首次签名时间字段名
        $firstBlsyTimeField = RuleWordMap::query()->where('id', '=', 8002)->value('keyword');
        $firstBlsyTimeField = !empty($firstBlsyTimeField) ? $firstBlsyTimeField : 'frist_blsy_time';

        //获取8047,8048
        $excludeKeywords8047 = RuleWordMap::getArrayById(8047);
        // 收集所有质控结果
        $allBasisGroups = [];

        // 4. 处理每个手术记录
        foreach ($ssapData as $surgery) {
            // 获取手术结束时间
            $surgeryEndTime = $surgery['JSRQ'];
            //获取当前时间
            $currentDate = Carbon::now()->toDateTimeString();
            $oneDayAfterSurgery = date('Y-m-d H:i:s', strtotime($surgeryEndTime) + 24 * 3600);
            //如果当前时间小于手术结束时间+24小时就跳过
            if (strtotime($currentDate) < strtotime($oneDayAfterSurgery)) {
                continue;
            }
            //手术名称
            $surgeryName = $surgery['ICD9_SSCZMC'];

            if (empty($surgery['SSRQ']) || empty($surgery['ICD9_SSCZMC']) || empty($surgery['JSRQ']) || strpos($surgery['JSRQ'], '1970-01-01') !== false || $surgery['ICD9_SSCZMC'] == 'NULL') {
                continue;
            }

            //根据手术名称查询SSCZ的SSLB
            $ssCZ = SSCZ::query()->where('SSMC', $surgeryName)->value('SSLB');
            if (empty($ssCZ)) {
                continue;
            }
            //检查是否是手术+介入
            if (!in_array($ssCZ, $excludeKeywords8047)) {
                continue;
            }
            // 检查手术结束时间是否有效
            if (empty($surgeryEndTime) || $surgeryEndTime == '1970-01-01 00:00:00' || $surgeryEndTime == '0000-00-00 00:00:00') {
                continue; // 跳过无效的手术时间
            }

            // 计算手术结束后24小时的时间点
            $oneDayAfterSurgery = date('Y-m-d H:i:s', strtotime($surgeryEndTime) + 24 * 3600);

            //使用sql查询
            $records = null;
            $recordsmblb = EMR_BL_BL01::query()
                ->where('JZHM', $ZYH)
                ->whereIn('MBLB', $recordTypes)
                ->where($ruleMap8011, '>=', date('Y-m-d H:i', strtotime($surgeryEndTime))) //精确到分钟就行
                ->where($ruleMap8011, '<=', $oneDayAfterSurgery)
                ->get()->toArray();


            if (!empty($recordsmblb)) {
                $records = $recordsmblb;
            } else {
                $recordsbllb = EMR_BL_BL01::query()
                    ->where('JZHM', $ZYH)
                    ->where('BLLB', $ruleMap8021)
                    ->where('BLMC', 'like', '%' . $ruleMap8020 . '%')
                    ->where('BLMC', 'not like', '%附页%')
                    ->where($ruleMap8011, '>=', date('Y-m-d H:i', strtotime($surgeryEndTime))) //精确到分钟就行
                    ->where($ruleMap8011, '<=', $oneDayAfterSurgery)
                    ->get()->toArray();
                $records = $recordsbllb;
            }

            // 如果没有找到记录
            if (empty($records)) {
                // 记录质控结果 - 未找到记录
                $basis = [];
                $basis[] = "手术名称【" . $surgeryName . "】";
                $basis[] = "(手麻)手术结束时间【" . $surgeryEndTime . "】";
                $basis[] = "【手术记录】【提前创建或未书写】";

                $allBasisGroups[] = $basis;
                continue;
            }

            // 6. 检查每条记录的签名时间
            foreach ($records as $record) {
                if (isset($record[$firstBlsyTimeField])) {
                    $firstBlsyTime = $record[$firstBlsyTimeField];
                }

                // 构建基础信息
                $basis = [];
                $basis[] = "手术名称【" . $surgeryName . "】";
                $basis[] = "(手麻)手术结束时间【" . $surgeryEndTime . "】";

                // 检查是否有签名
                if (empty($firstBlsyTime)) {
                    // 未签名
                    $basis[] = "【" . $record['BLMC'] . "】【未签名】";
                    $basis["BLBH"] = $record['BLBH'];
                    $allBasisGroups[] = $basis;
                } // 检查签名时间是否在手术后24小时内
                else if (strtotime($firstBlsyTime) > strtotime($oneDayAfterSurgery)) {
                    // 签名时间超过规定时间
                    $basis[] = "【" . $record['BLMC'] . "】";
                    $basis[] = "首次签名时间【" . $firstBlsyTime . "（超时）】";
                    $basis["BLBH"] = $record['BLBH'];
                    $allBasisGroups[] = $basis;
                }
            }
        }

        // 7. 将所有质控结果一次性插入到insertData
        if (!empty($allBasisGroups)) {
            $this->insertData[] = [
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title'],
                'basis' => json_encode($allBasisGroups, JSON_UNESCAPED_UNICODE)
            ];
        }

        return true;
    }

    /**
     * 术者术前24小时查房记录
     * @param $caseRule
     * @param $ruleId
     * @param $ZYH
     * @return bool
     * @author lzh
     * @datetime 2025-05-10
     */
    public function rule1004($caseRule, $ruleId, $ZYH)
    {

        // 1. 获取患者入院和出院信息
        $patientInfo = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->first();
        if (empty($patientInfo)) {
            return true; // 找不到患者信息，跳过质控
        }

        $enterTime = $patientInfo['AAB01'];
        $exitTime = $patientInfo['AAC01'];

        // 如果没有出院时间或出院时间为默认值，使用当前时间
        if (empty($exitTime)) {
            $exitTime = date('Y-m-d H:i:s');
        }

        // 3. 计算出院时间-入院时间
        $enterDate = date('Y-m-d', strtotime($enterTime));
        $exitDate = date('Y-m-d', strtotime($exitTime));
        $diffDays = (strtotime($exitDate) - strtotime($enterDate)) / (24 * 3600);

        if ($diffDays <= 1) {
            return true;
        }

        // 1. 查询手术信息，获取手术开始时间和术者
        try {
            $ssapData = SM_SSAP::query()->where('ZYH', '=', $ZYH)->orderBy('SSRQ', 'asc')->get()->toArray();
            if (empty($ssapData)) {
                return true; // 没有手术记录，不进行质控
            }
        } catch (\Exception $e) {
            Log::error("rule-$ruleId-$ZYH-error: " . $e->getMessage());
            return true; // 查询出错，跳过质控
        }

        // 2. 获取病程记录类型配置
        $ruleMap8010 = RuleWordMap::query()->where('id', '=', 8010)->value('keyword');
        $ruleMap8010 = !empty($ruleMap8010) ? $ruleMap8010 : '294'; // 病程记录BLLB值

        // 排除首次病程记录配置
        $ruleMap8008 = RuleWordMap::query()->where('id', '=', 8008)->value('keyword');
        $ruleMap8008 = !empty($ruleMap8008) ? $ruleMap8008 : '295'; // 首次病程记录MBLB值，需要排除
        //逗号分割
        if (strpos($ruleMap8008, ',') !== false) {
            $recordTypes = explode(',', $ruleMap8008);
        } else {
            $recordTypes = [$ruleMap8008];
        }

        // 排除会诊、危急值关键词
        $ruleMap8022 = RuleWordMap::query()->where('id', '=', 8022)->value('keyword');
        $ruleMap8022 = !empty($ruleMap8022) ? $ruleMap8022 : '会诊,危急值'; // 需要排除的关键词
        //逗号分割
        if (strpos($ruleMap8022, ',') !== false) {
            $excludeKeywords = explode(',', $ruleMap8022);
        } else {
            $excludeKeywords = [$ruleMap8022];
        }

        // 3. 获取查询字段
        // 获取执行时间字段名
        $ruleMap8011 = RuleWordMap::query()->where('id', '=', 8011)->value('keyword');
        $ruleMap8011 = !empty($ruleMap8011) ? $ruleMap8011 : 'ZXSJ'; // 执行时间字段名

        //签名时间
        $ruleMap8002 = RuleWordMap::query()->where('id', '=', 8002)->value('keyword');
        $ruleMap8002 = !empty($ruleMap8002) ? $ruleMap8002 : 'first_blsy_time'; // 签名时间字段名

        // 定义会诊、危急值关键词是否包含逗号
        if (strpos($ruleMap8022, ',') !== false) {
            $excludeKeywords = explode(',', $ruleMap8022);
        } else {
            $excludeKeywords = [$ruleMap8022];
        }
        //获取8047,8048
        $excludeKeywords8047 = RuleWordMap::getArrayById(8047);

        $ruleMap8016 = RuleWordMap::query()->where('id', '=', 8016)->value('keyword');
        $ruleMap8016 = !empty($ruleMap8016) ? $ruleMap8016 : '82,304'; // 术前小结mblb
        //逗号分割
        if (strpos($ruleMap8016, ',') !== false) {
            $recordTypes8016 = explode(',', $ruleMap8016);
        } else {
            $recordTypes8016 = [$ruleMap8016];
        }

        // 收集所有质控结果
        $allBasisGroups = [];

        // 4. 处理每个手术记录
        //上次手术结束时间
        $prevSurgeryEndTime = null;
        for ($i = 0; $i < count($ssapData); $i++) {
            $surgery = $ssapData[$i];

            // 获取手术开始时间和术者
            $surgeryStartTime = $surgery['SSRQ'];
            $surgeon = $surgery['SZ']; // 术者
            $surgeon1 = $surgery['SZ'];

            if (empty($surgery['SSRQ']) || empty($surgery['ICD9_SSCZMC']) || empty($surgery['JSRQ']) || strpos($surgery['JSRQ'], '1970-01-01') !== false || $surgery['ICD9_SSCZMC'] == 'NULL') {
                continue;
            }
            if (empty($surgeon1)) {
                continue;
            }
            $surgeonCode = $surgery['SZDM']; // 术者代码
            //手术名称
            $surgeryName = $surgery['ICD9_SSCZMC'];
            //申请单号
            $SQDH = $surgery['SQDH'];

            //根据手术名称查询SSCZ的SSLB
            $ssCZ = SSCZ::query()->where('SSMC', $surgeryName)->value('SSLB');
            if (empty($ssCZ)) {
                continue;
            }
            //检查是否是手术+介入
            if (!in_array($ssCZ, $excludeKeywords8047)) {
                continue;
            }
            //从staff表获取YGBH
            $surgeonBH = Staff::query()->where('code', $surgeonCode)->value('YGBH');
            $surgeon = $surgeon . "(" . $surgeonBH . ")";

            // 检查手术开始时间和术者是否有效
            if (
                empty($surgeryStartTime) || $surgeryStartTime == '1970-01-01 00:00:00' ||
                $surgeryStartTime == '0000-00-00 00:00:00' || empty($surgeon)
            ) {
                continue; // 跳过无效的手术时间或无术者的手术
            }

            // 计算查房时间范围
            $startCheckTime = date('Y-m-d H:i:s', strtotime($surgeryStartTime) - 24 * 3600);

            // 如果这不是第一次手术，检查上一次手术结束时间
            /* if ($i > 0) {
                $prevSurgeryEndTime = $ssapData[$i - 1]['JSRQ'];
                if (
                    !empty($prevSurgeryEndTime) && $prevSurgeryEndTime != '1970-01-01 00:00:00' &&
                    $prevSurgeryEndTime != '0000-00-00 00:00:00' &&
                    strtotime($prevSurgeryEndTime) > strtotime($startCheckTime)
                ) {
                    // 如果上一次手术结束时间晚于计算出的开始查房时间，则用上一次手术结束时间作为开始查房时间
                    $startCheckTime = $prevSurgeryEndTime;
                }
            } */


            //使用sql查询
            $startCheckTime = date('Y-m-d H:i:s', strtotime($startCheckTime));
            $surgeryStartTime = date('Y-m-d H:i:s', strtotime($surgeryStartTime));
            $records = EMR_BL_BL01::query()
                ->where('JZHM', $ZYH)
                ->where('BLLB', $ruleMap8010)
                ->whereNotIn('MBLB', $recordTypes)
                ->where($ruleMap8011, '>=', date('Y-m-d 00:00:00', strtotime($startCheckTime))) //精确到分钟就行
                ->where($ruleMap8011, '<=', $surgeryStartTime)
                ->get()->toArray();

            //打印sql

            // 创建当前手术的基础信息
            $surgeryBasis = [];
            $surgeryBasis[] = "手术名称【" . $surgeryName . "】";
            $surgeryBasis[] = "(手麻)手术开始时间【" . $surgeryStartTime . "】";
            $surgeryBasis[] = "(手麻)术者【" . $surgeon . "】";

            // 6. 检查病程记录是否有术者签名
            $hasSurgeonSignature = false;
            $surgeonSignedRecords = [];
            $unsignedRecords = [];
            $untimelySignedRecords = [];

            $jjbz = 0;
            // 检查是否有病程记录
            if (empty($records)) {
                //根据sqdh关联查询sssq表，查看jjbz是否为1
                $sssqData = SSSQ::query()->where('SQDH', $SQDH)->first();
                if (!empty($sssqData)) {
                    $jjbz = $sssqData['JJBZ'];
                    if ($jjbz == 1) {
                        //紧急手术，查询一下有没有术前小结
                        /* $records8016 = EMR_BL_BL01::query()
                            ->leftJoin('EMR_BL_BLXG', 'EMR_BL_BL01.BLBH', '=', 'EMR_BL_BLXG.BLBH')
                            ->where('JZHM', $ZYH)
                            ->whereIn('MBLB', $recordTypes8016)
                            ->where($ruleMap8011, '>=', $startCheckTime)
                            ->where($ruleMap8011, '<=', $surgeryStartTime)
                            ->get()->toArray(); */

                        // 从MySQL查询术前小结
                        $recordsQuery = EMR_BL_BL01::query()
                            ->leftJoin('EMR_BL_BLXG', 'EMR_BL_BL01.BLBH', '=', 'EMR_BL_BLXG.BLBH')
                            ->where('JZHM', $ZYH)
                            ->whereIn('MBLB', $recordTypes8016)
                            ->where($ruleMap8011, '>=', $startCheckTime)
                            ->where($ruleMap8011, '<=', $surgeryStartTime)
                            ->get(['EMR_BL_BL01.*', 'EMR_BL_BLXG.HJNR'])->toArray();

                        $records8016 = [];
                        foreach ($recordsQuery as $record) {
                            if (strpos($record['HJNR'], $surgeon1) !== false) {
                                $records8016[] = $record;
                            }
                        }

                        if (!empty($records8016)) {
                            $records = $records8016;
                        } else {
                            // 无病程记录情况
                            $surgeryBasis[] = "术者术前查房记录【无】";
                            $allBasisGroups[] = $surgeryBasis;
                            continue;
                        }
                    } else {
                        // 无病程记录情况
                        $surgeryBasis[] = "术者术前查房记录【无】";
                        $allBasisGroups[] = $surgeryBasis;
                        continue;
                    }
                } else {
                    // 无病程记录情况
                    $surgeryBasis[] = "术者术前查房记录【无】";
                    $allBasisGroups[] = $surgeryBasis;
                    continue;
                }
            }

            foreach ($records as $record) {
                // 查询病历名称中是否包含术者
                $blmc = $record['BLMC'] ?? '未知病历';
                //跳过会诊
                foreach ($excludeKeywords as $keyword) {
                    if (strpos($blmc, $keyword) !== false) {
                        continue 2;
                    }
                }
                $issign = false;
                if ($jjbz == 1 && strpos($blmc, '术前小结') !== false) {
                    $issign = true;
                }
                if (strpos($blmc, $surgeon1) !== false) {
                    $issign = true;
                }

                $blsy = EMR_BL_BLSY::query()->where('BLBH', $record['BLBH'])->where('FG_ACTIVE', 1)->get()->toArray();
                if (!empty($blsy)) {
                    foreach ($blsy as $item) {
                        if (empty($item['SYYS'])) {
                            continue;
                        }
                        $ysname = Staff::query()->where('code', $item['SYYS'])->first()['name'] ?? "";
                        if (strpos($ysname, $surgeon1) !== false) {
                            $issign = true;
                            break;
                        }
                    }
                }

                $hjnr = EMR_BL_BLXG::query()->where('BLBH', $record['BLBH'])->value('HJNR');
                if (!empty($hjnr) && strpos($hjnr, $surgeon1) !== false) {
                    $issign = true;
                }

                if ($issign) {
                    // 检查签名时间是否在规定时间内
                    $signTime = isset($record[$ruleMap8002]) ? $record[$ruleMap8002] : '';
                    if (!empty($signTime)) {
                        if (strtotime($startCheckTime) <= strtotime($signTime) && strtotime($signTime) <= strtotime($surgeryStartTime)) {
                            $hasSurgeonSignature = true;
                            $surgeonSignedRecords[] = $record;
                        } else {
                            //签名超时
                            $untimelySignedRecords[] = $record;
                        }
                    } else {
                        // 虽然有术者签名，但无签名时间
                        $unsignedRecords[] = $record;
                    }
                } /* else {
                    // 没有术者签名
                    $unsignedRecords[] = $record;
                } */
            }

            // 7. 根据检查结果生成质控依据
            if (!$hasSurgeonSignature) {
                // 创建一个记录所有问题的数组
                $problemRecords = [];

                // 如果有未签名记录
                if (!empty($unsignedRecords)) {
                    foreach ($unsignedRecords as $record) {
                        $problemRecords[] = "【" . $record['BLMC'] . "】【未签名】";
                    }
                }

                // 如果有超时签名记录
                if (!empty($untimelySignedRecords)) {
                    foreach ($untimelySignedRecords as $record) {
                        $problemRecords[] = "【" . $record['BLMC'] . "】";
                        $problemRecords[] = "首次签名时间【" . $record[$ruleMap8002] . "（超时）】";
                    }
                }

                // 如果既没有未签名记录也没有超时签名记录，说明没有病程记录
                if (empty($unsignedRecords) && empty($untimelySignedRecords) && empty($surgeonSignedRecords)) {
                    $problemRecords[] = "术者术前查房记录【无】";
                }

                // 将所有问题记录合并到手术基础信息中
                $surgeryBasis = array_merge($surgeryBasis, $problemRecords);

                // 添加BLBH（如果有）
                if (!empty($unsignedRecords)) {
                    $surgeryBasis["BLBH"] = $unsignedRecords[0]['BLBH'];
                } elseif (!empty($untimelySignedRecords)) {
                    $surgeryBasis["BLBH"] = $untimelySignedRecords[0]['BLBH'];
                }

                // 将当前手术的所有问题作为一组添加到结果中
                $allBasisGroups[] = $surgeryBasis;
            }
        }

        // 8. 将所有质控结果一次性插入到insertData
        if (!empty($allBasisGroups)) {
            $this->insertData[] = [
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title'],
                'basis' => json_encode($allBasisGroups, JSON_UNESCAPED_UNICODE)
            ];
        }

        return true;
    }

    /**
     * 术者术后24小时有无查房
     * @param $caseRule
     * @param $ruleId
     * @param $ZYH
     * @return bool
     * @author lzh
     * @datetime 2025-05-10
     */
    public function rule1005($caseRule, $ruleId, $ZYH)
    {

        // 1. 获取患者入院和出院信息
        $patientInfo = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->first();
        if (empty($patientInfo)) {
            return true; // 找不到患者信息，跳过质控
        }

        $enterTime = $patientInfo['AAB01'];
        $exitTime = $patientInfo['AAC01'];

        // 如果没有出院时间或出院时间为默认值，使用当前时间
        if (empty($exitTime)) {
            $exitTime = date('Y-m-d H:i:s');
        }

        // 3. 计算出院时间-入院时间
        $enterDate = date('Y-m-d', strtotime($enterTime));
        $exitDate = date('Y-m-d', strtotime($exitTime));
        $diffDays = (strtotime($exitDate) - strtotime($enterDate)) / (24 * 3600);

        if ($diffDays <= 1) {
            return true;
        }

        // 1. 查询手术信息，获取手术结束时间和术者
        try {
            $ssapData = SM_SSAP::query()->where('ZYH', '=', $ZYH)->get()->toArray();
            if (empty($ssapData)) {
                return true; // 没有手术记录，不进行质控
            }
        } catch (\Exception $e) {
            Log::error("rule-$ruleId-$ZYH-error: " . $e->getMessage());
            return true; // 查询出错，跳过质控
        }

        // 2. 获取病程记录类型配置
        $recordTypes = RuleWordMap::getArrayById(8046);

        // 3. 获取查询字段
        // 获取执行时间字段名
        $ruleMap8011 = RuleWordMap::query()->where('id', '=', 8011)->value('keyword');
        $ruleMap8011 = !empty($ruleMap8011) ? $ruleMap8011 : 'ZXSJ'; // 执行时间字段名

        // 获取首次签名时间字段名
        $firstBlsyTimeField = RuleWordMap::query()->where('id', '=', 8002)->value('keyword');
        $firstBlsyTimeField = !empty($firstBlsyTimeField) ? $firstBlsyTimeField : 'first_blsy_time';

        //获取8047,8048
        $excludeKeywords8047 = RuleWordMap::getArrayById(8047);

        // 收集所有质控结果
        $allBasisGroups = [];

        // 4. 处理每个手术记录
        foreach ($ssapData as $surgery) {
            if (empty($surgery['SSRQ']) || empty($surgery['ICD9_SSCZMC']) || empty($surgery['JSRQ']) || strpos($surgery['JSRQ'], '1970-01-01') !== false || $surgery['ICD9_SSCZMC'] == 'NULL') {
                continue;
            }
            // 获取手术结束时间和术者
            $surgeryEndTime = $surgery['JSRQ'];
            //获取当前时间
            $currentDate = Carbon::now()->toDateTimeString();
            $oneDayAfterSurgery = date('Y-m-d H:i:s', strtotime($surgeryEndTime) + 24 * 3600);
            //如果当前时间小于手术结束时间+24小时就跳过
            if (strtotime($currentDate) < strtotime($oneDayAfterSurgery)) {
                continue;
            }
            $surgeon = $surgery['SZ']; // 术者
            $surgeon1 = $surgery['SZ'];
            $surgeonCode = $surgery['SZDM']; // 术者代码
            //从staff表获取YGBH
            $surgeonBH = Staff::query()->where('code', $surgeonCode)->value('YGBH');
            $surgeon = $surgeon . "(" . $surgeonBH . ")";
            //手术名称
            $surgeryName = $surgery['ICD9_SSCZMC'];

            //根据手术名称查询SSCZ的SSLB
            $ssCZ = SSCZ::query()->where('SSMC', $surgeryName)->value('SSLB');
            if (empty($ssCZ)) {
                continue;
            }
            //检查是否是手术+介入
            if (!in_array($ssCZ, $excludeKeywords8047)) {
                continue;
            }

            // 检查手术结束时间和术者是否有效
            if (
                empty($surgeryEndTime) || $surgeryEndTime == '1970-01-01 00:00:00' ||
                $surgeryEndTime == '0000-00-00 00:00:00' || empty($surgeon)
            ) {
                continue; // 跳过无效的手术时间或无术者的手术
            }

            // 计算查房时间范围
            $endCheckTime = date('Y-m-d H:i:s', strtotime($surgeryEndTime) + 24 * 3600);

            // 创建当前手术的基础信息
            $surgeryBasis = [];
            $surgeryBasis[] = "手术名称【" . $surgeryName . "】";
            $surgeryBasis[] = "(手麻)手术结束时间【" . $surgeryEndTime . "】";
            $surgeryBasis[] = "(手麻)术者【" . $surgeon . "】";

            //使用sql查询
            $records = EMR_BL_BL01::query()
                ->where('JZHM', $ZYH)
                ->whereIn('MBLB', $recordTypes)
                ->where($ruleMap8011, '>=', date('Y-m-d H:i', strtotime($surgeryEndTime))) //精确到分钟就行
                ->where($ruleMap8011, '<=', $endCheckTime)
                ->get()->toArray();

            // 检查是否有病程记录
            if (empty($records)) {
                // 无病程记录情况
                $surgeryBasis[] = "手术结束后24小时内术者查房记录【无】";
                $allBasisGroups[] = $surgeryBasis;
            } else {
                $signatureData = false;
                foreach ($records as $record) {
                    // 查询病程记录的签名信息
                    $blmc = $record['BLMC'] ?? '未知病历';
                    //查看是否有术者签名
                    if (empty($blmc) || empty($surgeon1) || strpos($blmc, $surgeon1) !== false) {
                        $signatureData = true;
                        continue;
                    }

                    $blsy = EMR_BL_BLSY::query()->where('BLBH', $record['BLBH'])->where('FG_ACTIVE', 1)->get()->toArray();
                    if (!empty($blsy)) {
                        foreach ($blsy as $item) {
                            if (empty($item['SYYS'])) {
                                continue;
                            }
                            $ysname = Staff::query()->where('code', $item['SYYS'])->first()['name'] ?? "";
                            if (empty($ysname) || empty($surgeon1) || strpos($ysname, $surgeon1) !== false) {
                                $signatureData = true;
                                break;
                            }
                        }
                    }

                    $hjnr = EMR_BL_BLXG::query()->where('BLBH', $record['BLBH'])->value('HJNR');
                    if (!empty($hjnr) && strpos($hjnr, $surgeon1) !== false) {
                        $signatureData = true;
                        //break;
                    }
                }
                if ($signatureData) {
                    continue;
                } else {
                    $surgeryBasis[] = "手术结束后24小时内术者查房记录【无】";
                    $allBasisGroups[] = $surgeryBasis;
                }
            }
        }

        // 8. 将所有质控结果一次性插入到insertData
        if (!empty($allBasisGroups)) {
            $this->insertData[] = [
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title'],
                'basis' => json_encode($allBasisGroups, JSON_UNESCAPED_UNICODE)
            ];
        }

        return true;
    }

    /**
     * 术者术后24小时查房有无签名
     * @param $caseRule
     * @param $ruleId
     * @param $ZYH
     * @return bool
     * @author lzh
     * @datetime 2025-05-10
     */
    public function rule1060($caseRule, $ruleId, $ZYH)
    {

        // 1. 获取患者入院和出院信息
        $patientInfo = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->first();
        if (empty($patientInfo)) {
            return true; // 找不到患者信息，跳过质控
        }

        $enterTime = $patientInfo['AAB01'];
        $exitTime = $patientInfo['AAC01'];

        // 如果没有出院时间或出院时间为默认值，使用当前时间
        if (empty($exitTime)) {
            $exitTime = date('Y-m-d H:i:s');
        }

        // 3. 计算出院时间-入院时间
        $enterDate = date('Y-m-d', strtotime($enterTime));
        $exitDate = date('Y-m-d', strtotime($exitTime));
        $diffDays = (strtotime($exitDate) - strtotime($enterDate)) / (24 * 3600);

        if ($diffDays <= 1) {
            return true;
        }

        // 1. 查询手术信息，获取手术结束时间和术者
        try {
            $ssapData = SM_SSAP::query()->where('ZYH', '=', $ZYH)->get()->toArray();
            if (empty($ssapData)) {
                return true; // 没有手术记录，不进行质控
            }
        } catch (\Exception $e) {
            Log::error("rule-$ruleId-$ZYH-error: " . $e->getMessage());
            return true; // 查询出错，跳过质控
        }

        // 2. 获取病程记录类型配置
        $ruleMap8046 = RuleWordMap::query()->where('id', '=', 8046)->value('keyword');
        $ruleMap8046 = !empty($ruleMap8046) ? $ruleMap8046 : '50,296,42'; // 病程记录BLLB值
        //逗号分割
        if (strpos($ruleMap8046, ',') !== false) {
            $recordTypes = explode(',', $ruleMap8046);
        } else {
            $recordTypes = [$ruleMap8046];
        }

        // 3. 获取查询字段
        // 获取执行时间字段名
        $ruleMap8011 = RuleWordMap::query()->where('id', '=', 8011)->value('keyword');
        $ruleMap8011 = !empty($ruleMap8011) ? $ruleMap8011 : 'ZXSJ'; // 执行时间字段名

        // 获取首次签名时间字段名
        $firstBlsyTimeField = RuleWordMap::query()->where('id', '=', 8002)->value('keyword');
        $firstBlsyTimeField = !empty($firstBlsyTimeField) ? $firstBlsyTimeField : 'first_blsy_time';

        //获取8047,8048
        $excludeKeywords8047 = RuleWordMap::getArrayById(8047);

        // 收集所有质控结果
        $allBasisGroups = [];

        // 4. 处理每个手术记录
        foreach ($ssapData as $surgery) {
            if (empty($surgery['SSRQ']) || empty($surgery['ICD9_SSCZMC']) || empty($surgery['JSRQ']) || strpos($surgery['JSRQ'], '1970-01-01') !== false || $surgery['ICD9_SSCZMC'] == 'NULL') {
                continue;
            }
            // 获取手术结束时间和术者
            $surgeryEndTime = $surgery['JSRQ'];
            //获取当前时间
            $currentDate = Carbon::now()->toDateTimeString();
            $oneDayAfterSurgery = date('Y-m-d H:i:s', strtotime($surgeryEndTime) + 24 * 3600);
            //如果当前时间小于手术结束时间+24小时就跳过
            if (strtotime($currentDate) < strtotime($oneDayAfterSurgery)) {
                continue;
            }
            $surgeon = $surgery['SZ']; // 术者
            $surgeon1 = $surgery['SZ'];
            $surgeonCode = $surgery['SZDM']; // 术者代码
            //从staff表获取YGBH
            $surgeonBH = Staff::query()->where('code', $surgeonCode)->value('YGBH');
            $surgeon = $surgeon . "(" . $surgeonBH . ")";
            //手术名称
            $surgeryName = $surgery['ICD9_SSCZMC'];

            //根据手术名称查询SSCZ的SSLB
            $ssCZ = SSCZ::query()->where('SSMC', $surgeryName)->value('SSLB');
            if (empty($ssCZ)) {
                continue;
            }
            //检查是否是手术+介入
            if (!in_array($ssCZ, $excludeKeywords8047)) {
                continue;
            }

            // 检查手术结束时间和术者是否有效
            if (
                empty($surgeryEndTime) || $surgeryEndTime == '1970-01-01 00:00:00' ||
                $surgeryEndTime == '0000-00-00 00:00:00' || empty($surgeon)
            ) {
                continue; // 跳过无效的手术时间或无术者的手术
            }

            // 计算查房时间范围
            $endCheckTime = date('Y-m-d H:i:s', strtotime($surgeryEndTime) + 24 * 3600);
            //如果当前时间小于查房时间范围，跳过
            if (time() < strtotime($endCheckTime)) {
                continue;
            }

            // 创建当前手术的基础信息
            $surgeryBasis = [];
            $surgeryBasis[] = "手术名称【" . $surgeryName . "】";
            $surgeryBasis[] = "(手麻)手术结束时间【" . $surgeryEndTime . "】";
            $surgeryBasis[] = "(手麻)术者【" . $surgeon . "】";

            //使用sql查询
            $records = EMR_BL_BL01::query()
                ->where('JZHM', $ZYH)
                ->whereIn('MBLB', $recordTypes)
                ->where($ruleMap8011, '>=', date('Y-m-d H:i', strtotime($surgeryEndTime))) //精确到分钟就行
                ->where($ruleMap8011, '<=', $endCheckTime)
                ->get()->toArray();

            // 检查是否有病程记录
            if (empty($records)) {
                // 无查房记录
                continue;
            }

            // 6. 检查病程记录是否有术者签名
            $hasSurgeonSignature = false;
            $surgeonSignedRecords = [];
            $unsignedRecords = [];
            $untimelySignedRecords = [];

            foreach ($records as $record) {
                // 查询病程记录的签名信息
                $blmc = $record['BLMC'] ?? '未知病历';
                //查看是否有术者签名
                $signatureData = false;
                if (empty($blmc) || empty($surgeon1) || strpos($blmc, $surgeon1) !== false) {
                    $signatureData = true;
                }

                $blsy = EMR_BL_BLSY::query()->where('BLBH', $record['BLBH'])->where('FG_ACTIVE', 1)->get()->toArray();
                if (!empty($blsy)) {
                    foreach ($blsy as $item) {
                        if (empty($item['SYYS'])) {
                            continue;
                        }
                        $ysname = Staff::query()->where('code', $item['SYYS'])->first()['name'] ?? "";
                        if (empty($ysname) || empty($surgeon1) || strpos($ysname, $surgeon1) !== false) {
                            $signatureData = true;
                            break;
                        }
                    }
                }

                $hjnr = EMR_BL_BLXG::query()->where('BLBH', $record['BLBH'])->value('HJNR');
                if (!empty($hjnr) && strpos($hjnr, $surgeon1) !== false) {
                    $signatureData = true;
                    //break;
                }

                if ($signatureData) {
                    // 检查签名时间是否在规定时间内
                    $signTime = isset($record[$firstBlsyTimeField]) ? $record[$firstBlsyTimeField] : '';
                    if (!empty($signTime)) {
                        if (strtotime($signTime) <= strtotime($endCheckTime)) {
                            $hasSurgeonSignature = true;
                            $surgeonSignedRecords[] = $record;
                        } else {
                            // 签名超时
                            $untimelySignedRecords[] = $record;
                        }
                    } else {
                        // 虽然有术者签名，但无签名时间
                        $unsignedRecords[] = $record;
                    }
                }
            }

            // 7. 根据检查结果生成质控依据
            if (!$hasSurgeonSignature) {
                // 创建一个记录所有问题的数组
                $problemRecords = [];

                // 如果有未签名记录
                if (!empty($unsignedRecords)) {
                    foreach ($unsignedRecords as $record) {
                        $problemRecords[] = "【" . $record['BLMC'] . "】【术者未签名】";
                    }
                }

                // 如果有超时签名记录
                if (!empty($untimelySignedRecords)) {
                    foreach ($untimelySignedRecords as $record) {
                        $problemRecords[] = "【" . $record['BLMC'] . "】";
                        $problemRecords[] = "首次签名时间【" . $record[$firstBlsyTimeField] . "（超时）】";
                    }
                }

                //都是空
                if (empty($unsignedRecords) && empty($untimelySignedRecords)) {
                    $problemRecords[] = "手术结束后24小时内术者查房记录【无】";
                }


                // 将所有问题记录合并到手术基础信息中
                $surgeryBasis = array_merge($surgeryBasis, $problemRecords);

                // 添加BLBH（如果有）
                if (!empty($unsignedRecords)) {
                    $surgeryBasis["BLBH"] = $unsignedRecords[0]['BLBH'];
                } elseif (!empty($untimelySignedRecords)) {
                    $surgeryBasis["BLBH"] = $untimelySignedRecords[0]['BLBH'];
                }

                // 将当前手术的所有问题作为一组添加到结果中
                $allBasisGroups[] = $surgeryBasis;
            }
        }

        // 8. 将所有质控结果一次性插入到insertData
        if (!empty($allBasisGroups)) {
            $this->insertData[] = [
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title'],
                'basis' => json_encode($allBasisGroups, JSON_UNESCAPED_UNICODE)
            ];
        }

        return true;
    }

    /**
     * 医嘱名称含"抢救"，开嘱时间后6小时内需有抢救记录
     * @param $caseRule
     * @param $ruleId
     * @param $ZYH
     * @return bool
     * @author lzh
     * @datetime 2025-05-20
     */
    public function rule1006($caseRule, $ruleId, $ZYH)
    {

        // 1. 获取抢救关键词配置
        $ruleMap8026 = RuleWordMap::getArrayById(8026);
        $yzbData = Yzb::query()->where(['ZYH' => $ZYH])->get()->toArray();
        $qjYzb = [];
        foreach ($yzbData as $key => $item) {
            //判断是否包含死亡关键字
            $flag = false;
            foreach ($ruleMap8026 as $keyword) {
                if (strpos($item['YZMC'], $keyword) !== false) {
                    $qjYzb[] = $item;
                    break;
                }
            }
        }
        // 如果没有死亡医嘱，则不进行质控
        if (empty($qjYzb[0])) {
            return true;
        }

        // 3. 获取抢救记录MBLB值配置
        $ruleMap8027 = RuleWordMap::query()->where('id', '=', 8027)->value('keyword');
        $ruleMap8027 = !empty($ruleMap8027) ? $ruleMap8027 : '27'; // 抢救记录MBLB值

        // 判断是否包含逗号
        if (strpos($ruleMap8027, ',') !== false) {
            $recordTypes = explode(',', $ruleMap8027);
        } else {
            $recordTypes = [$ruleMap8027];
        }

        // 4. 获取查询字段
        // 获取执行时间字段名
        $ruleMap8011 = RuleWordMap::query()->where('id', '=', 8011)->value('keyword');
        $ruleMap8011 = !empty($ruleMap8011) ? $ruleMap8011 : 'ZXSJ'; // 执行时间字段名

        //签名时间
        $ruleMap8002 = RuleWordMap::query()->where('id', '=', 8002)->value('keyword');
        $ruleMap8002 = !empty($ruleMap8002) ? $ruleMap8002 : 'first_blsy_time'; // 签名时间字段名

        // 收集所有质控结果
        $allBasisGroups = [];

        // 5. 处理每条抢救医嘱
        foreach ($qjYzb as $order) {
            // 获取开嘱时间
            $startTime = isset($order['KZSJ']) ? $order['KZSJ'] : '';
            Log::info('rule1006医嘱名称' . $order['YZMC']);
            Log::info('rule1006开嘱时间' . $startTime);
            //获取当前时间
            $currentDate = Carbon::now()->toDateTimeString();
            $sixHoursAfterStart = date('Y-m-d H:i:s', strtotime($startTime) + 6 * 3600);
            //如果当前时间小于开嘱时间+6小时就跳过
            if (strtotime($currentDate) < strtotime($sixHoursAfterStart)) {
                continue;
            }
            $yzmc = isset($order['YZMC']) ? $order['YZMC'] : '未知医嘱';

            // 检查开嘱时间是否有效
            if (empty($startTime) || $startTime == '1970-01-01 00:00:00' || $startTime == '0000-00-00 00:00:00') {
                continue; // 跳过无效的开嘱时间
            }

            //sql查询
            $records = EMR_BL_BL01::query()
                ->where('JZHM', $ZYH)
                ->whereIn('MBLB', $recordTypes)
                ->where($ruleMap8011, '>=', $startTime)
                ->where($ruleMap8011, '<=', $sixHoursAfterStart)
                ->get()
                ->toArray();

            // 7. 检查是否有抢救记录
            $hasValidRecord = false;
            $invalidRecords = [];
            Log::info('rule1006抢救记录' . count($records));

            // 8. 检查是否有抢救记录
            if (empty($records)) {
                // 记录质控结果 - 无抢救记录
                $basis = [];
                $basis[] = "医嘱名称【" . $yzmc . "】";
                $basis[] = "开嘱时间【" . $startTime . "】";
                $basis[] = "抢救记录【无】";

                $allBasisGroups[] = $basis;
                continue;
            }


            foreach ($records as $record) {
                $signTime = isset($record[$ruleMap8002]) ? $record[$ruleMap8002] : '';
                if (!empty($signTime)) {
                    // 判断签名时间是否在6小时之内
                    if (strtotime($signTime) <= strtotime($sixHoursAfterStart)) {
                        $hasValidRecord = true;
                        break;
                    } else {
                        // 记录超时
                        $invalidRecords[] = $record;
                    }
                }
            }

            // 如果没有有效记录，记录质控结果
            if (!$hasValidRecord) {
                // 构建当前医嘱的基础信息
                $orderBasis = [];
                $orderBasis[] = "医嘱名称【" . $yzmc . "】";
                $orderBasis[] = "开嘱时间【" . $startTime . "】";

                // 添加详细信息
                $orderDetails = [];

                if (!empty($invalidRecords)) {
                    // 有超时记录，添加所有超时记录
                    foreach ($invalidRecords as $record) {
                        $orderDetails[] = "【" . $record['BLMC'] . "】";
                        $orderDetails[] = "首次签名时间【" . $record[$ruleMap8002] . "】（超6小时）";
                    }

                    // 添加第一条记录的BLBH
                    if (isset($invalidRecords[0]['BLBH'])) {
                        $orderBasis["BLBH"] = $invalidRecords[0]['BLBH'];
                    }
                } else {
                    // 没有任何抢救记录
                    $orderDetails[] = "抢救记录【无】";
                }

                // 合并医嘱基础信息和详细记录
                $orderBasisGroup = array_merge($orderBasis, $orderDetails);
                $allBasisGroups[] = $orderBasisGroup;
            }
        }

        // 9. 将所有质控结果一次性插入到insertData
        if (!empty($allBasisGroups)) {
            $this->insertData[] = [
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title'],
                'basis' => json_encode($allBasisGroups, JSON_UNESCAPED_UNICODE)
            ];
        }

        return true;
    }

    /**
     * 无术后第2天病程记录
     * @param $caseRule
     * @param $ruleId
     * @param $ZYH
     * @return bool
     * @author lzh
     * @date 2023-05-20
     */
    public function rule1007($caseRule, $ruleId, $ZYH)
    {

        // 1. 获取患者入院和出院信息
        $patientInfo = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->first();
        if (empty($patientInfo)) {
            return true; // 找不到患者信息，跳过质控
        }

        $enterTime = $patientInfo['AAB01'];
        $exitTime = $patientInfo['AAC01'];

        // 如果没有出院时间或出院时间为默认值，使用当前时间
        if (empty($exitTime)) {
            $exitTime = date('Y-m-d H:i:s');
        }

        // 3. 计算出院时间-入院时间
        $enterDate = date('Y-m-d', strtotime($enterTime));
        $exitDate = date('Y-m-d', strtotime($exitTime));
        $diffDays = (strtotime($exitDate) - strtotime($enterDate)) / (24 * 3600);

        if ($diffDays <= 1) {
            return true;
        }

        // 1. 查询手术信息
        try {
            $ssapData = SM_SSAP::query()->where('ZYH', '=', $ZYH)->get()->toArray();
            if (empty($ssapData)) {
                return true; // 没有手术记录，不进行质控
            }
        } catch (\Exception $e) {
            Log::error("rule-$ruleId-$ZYH-error: " . $e->getMessage());
            return true; // 查询出错，跳过质控
        }

        // 2. 获取病程记录类型配置
        $ruleMap8046 = RuleWordMap::query()->where('id', '=', 8046)->value('keyword');
        $ruleMap8046 = !empty($ruleMap8046) ? $ruleMap8046 : '50,296,42'; // 病程记录BLLB值
        //是否包含逗号
        if (strpos($ruleMap8046, ',') !== false) {
            $recordTypes = explode(',', $ruleMap8046);
        } else {
            $recordTypes = [$ruleMap8046];
        }

        // 3. 获取查询字段
        // 获取执行时间字段名
        $ruleMap8011 = RuleWordMap::query()->where('id', '=', 8011)->value('keyword');
        $ruleMap8011 = !empty($ruleMap8011) ? $ruleMap8011 : 'ZXSJ'; // 执行时间字段名

        // 获取签名时间字段名
        $signTimeField = RuleWordMap::query()->where('id', '=', 8002)->value('keyword');
        $signTimeField = !empty($signTimeField) ? $signTimeField : 'first_blsy_time';

        //获取8047,8048
        $excludeKeywords8047 = RuleWordMap::getArrayById(8047);

        // 4. 获取出院记录
        try {
            $brxxData = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->first();
            $cysj = $brxxData ? $brxxData->AAC01 : '';
        } catch (\Exception $e) {
            Log::error("rule-$ruleId-$ZYH-error: " . $e->getMessage());
            $cysj = '';
        }

        // 收集所有质控结果
        $allBasisGroups = [];

        // 5. 处理每个手术记录
        foreach ($ssapData as $surgery) {
            if (empty($surgery['SSRQ']) || empty($surgery['ICD9_SSCZMC']) || empty($surgery['JSRQ']) || strpos($surgery['JSRQ'], '1970-01-01') !== false || $surgery['ICD9_SSCZMC'] == 'NULL') {
                continue;
            }
            // 获取手术结束时间
            $surgeryEndTime = $surgery['JSRQ'];
            //手术名称
            $surgeryName = $surgery['ICD9_SSCZMC'];

            //根据手术名称查询SSCZ的SSLB
            $ssCZ = SSCZ::query()->where('SSMC', $surgeryName)->value('SSLB');
            if (empty($ssCZ)) {
                continue;
            }
            //检查是否是手术+介入
            if (!in_array($ssCZ, $excludeKeywords8047)) {
                continue;
            }

            // 检查手术结束时间是否有效
            if (
                empty($surgeryEndTime) ||
                $surgeryEndTime == '1970-01-01 00:00:00' ||
                $surgeryEndTime == '0000-00-00 00:00:00'
            ) {
                continue; // 跳过无效的手术时间
            }

            // 检查是否在手术当天出院
            $operationDay = date('Y-m-d', strtotime($surgeryEndTime));
            $dischargeDay = !empty($cysj) ? date('Y-m-d', strtotime($cysj)) : '';
            //手术完第1天也不质控
            $firstDay = date('Y-m-d', strtotime($operationDay . ' +1 day'));

            if (!empty($dischargeDay) && ($operationDay == $dischargeDay || $firstDay == $dischargeDay)) {
                continue; // 手术当天出院，跳过质控
            }


            // 计算术后第2天的时间范围
            $nextDay = date('Y-m-d', strtotime($operationDay . ' +2 day'));
            $startTime = $nextDay . ' 00:00:00';
            $endTime = $nextDay . ' 23:59:59';

            //如果当前时间小于第二天结束时间，跳过
            if (time() < strtotime($endTime)) {
                continue;
            }

            // 6. 使用数据表查询病程记录
            try {
                $records = EMR_BL_BL01::query()
                    ->where('JZHM', $ZYH)
                    ->whereIn('MBLB', $recordTypes)
                    ->where($ruleMap8011, '>=', $startTime)
                    ->where($ruleMap8011, '<=', $endTime)
                    ->get()
                    ->toArray();
            } catch (\Exception $e) {
                Log::error("rule-$ruleId-$ZYH-error: " . $e->getMessage());
                continue; // 查询出错，跳过当前手术记录
            }

            // 7. 检查是否有病程记录
            if (empty($records)) {
                // 记录质控结果 - 无病程记录
                $basis = [];
                $basis[] = "手术名称【" . $surgeryName . "】";
                $basis[] = "(手麻)手术结束时间【" . $surgeryEndTime . "】";
                $basis[] = "术后第2天病程记录【无】";
                $allBasisGroups[] = $basis;
            } else {
                continue;
            }
        }

        // 10. 将所有质控结果一次性插入到insertData
        if (!empty($allBasisGroups)) {
            $this->insertData[] = [
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title'],
                'basis' => json_encode($allBasisGroups, JSON_UNESCAPED_UNICODE)
            ];
        }

        return true;
    }

    /**
     * 术后第2天病程记录超时
     * @param $caseRule
     * @param $ruleId
     * @param $ZYH
     * @return bool
     * @author lzh
     * @date 2023-05-20
     */
    public function rule1061($caseRule, $ruleId, $ZYH)
    {

        // 1. 查询手术信息
        try {
            $ssapData = SM_SSAP::query()->where('ZYH', '=', $ZYH)->get()->toArray();
            if (empty($ssapData)) {
                return true; // 没有手术记录，不进行质控
            }
        } catch (\Exception $e) {
            Log::error("rule-$ruleId-$ZYH-error: " . $e->getMessage());
            return true; // 查询出错，跳过质控
        }

        // 2. 获取病程记录类型配置
        $ruleMap8046 = RuleWordMap::query()->where('id', '=', 8046)->value('keyword');
        $ruleMap8046 = !empty($ruleMap8046) ? $ruleMap8046 : '50,296,42'; // 病程记录BLLB值
        //是否包含逗号
        if (strpos($ruleMap8046, ',') !== false) {
            $recordTypes = explode(',', $ruleMap8046);
        } else {
            $recordTypes = [$ruleMap8046];
        }

        // 3. 获取查询字段
        // 获取执行时间字段名
        $ruleMap8011 = RuleWordMap::query()->where('id', '=', 8011)->value('keyword');
        $ruleMap8011 = !empty($ruleMap8011) ? $ruleMap8011 : 'ZXSJ'; // 执行时间字段名

        // 获取签名时间字段名
        $signTimeField = RuleWordMap::query()->where('id', '=', 8002)->value('keyword');
        $signTimeField = !empty($signTimeField) ? $signTimeField : 'first_blsy_time';

        //获取8047,8048
        $excludeKeywords8047 = RuleWordMap::getArrayById(8047);

        // 4. 获取出院记录
        try {
            $brxxData = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->first();
            $cysj = $brxxData ? $brxxData->AAC01 : '';
        } catch (\Exception $e) {
            Log::error("rule-$ruleId-$ZYH-error: " . $e->getMessage());
            $cysj = '';
        }

        // 收集所有质控结果
        $allBasisGroups = [];

        // 5. 处理每个手术记录
        foreach ($ssapData as $surgery) {
            if (empty($surgery['SSRQ']) || empty($surgery['ICD9_SSCZMC']) || empty($surgery['JSRQ']) || strpos($surgery['JSRQ'], '1970-01-01') !== false || $surgery['ICD9_SSCZMC'] == 'NULL') {
                continue;
            }
            // 获取手术结束时间
            $surgeryEndTime = $surgery['JSRQ'];
            //手术名称
            $surgeryName = $surgery['ICD9_SSCZMC'];

            //根据手术名称查询SSCZ的SSLB
            $ssCZ = SSCZ::query()->where('SSMC', $surgeryName)->value('SSLB');
            if (empty($ssCZ)) {
                continue;
            }
            //检查是否是手术+介入
            if (!in_array($ssCZ, $excludeKeywords8047)) {
                continue;
            }

            // 检查手术结束时间是否有效
            if (
                empty($surgeryEndTime) ||
                $surgeryEndTime == '1970-01-01 00:00:00' ||
                $surgeryEndTime == '0000-00-00 00:00:00'
            ) {
                continue; // 跳过无效的手术时间
            }

            // 检查是否在手术当天出院
            $operationDay = date('Y-m-d', strtotime($surgeryEndTime));
            $dischargeDay = !empty($cysj) ? date('Y-m-d', strtotime($cysj)) : '';
            //手术完第1天也不质控
            $firstDay = date('Y-m-d', strtotime($operationDay . ' +1 day'));

            if (!empty($dischargeDay) && ($operationDay == $dischargeDay || $firstDay == $dischargeDay)) {
                continue; // 手术当天出院，跳过质控
            }


            // 计算术后第2天的时间范围
            $nextDay = date('Y-m-d', strtotime($operationDay . ' +2 day'));
            $startTime = $nextDay . ' 00:00:00';
            $endTime = $nextDay . ' 23:59:59';
            //如果当前时间小于第三天结束时间，跳过
            if (time() < strtotime($endTime)) {
                continue;
            }

            // 6. 使用数据表查询病程记录
            try {
                $records = EMR_BL_BL01::query()
                    ->where('JZHM', $ZYH)
                    ->whereIn('MBLB', $recordTypes)
                    ->where($ruleMap8011, '>=', $startTime)
                    ->where($ruleMap8011, '<=', $endTime)
                    ->get()
                    ->toArray();
            } catch (\Exception $e) {
                Log::error("rule-$ruleId-$ZYH-error: " . $e->getMessage());
                continue; // 查询出错，跳过当前手术记录
            }

            // 7. 检查是否有病程记录
            if (empty($records)) {
                // 记录质控结果 - 无病程记录
                continue;
            }

            // 8. 检查病程记录的签名时间是否在规定范围内
            $hasValidRecord = false;
            $overtimeRecords = [];

            foreach ($records as $record) {

                // 获取签名时间
                $signTime = isset($record[$signTimeField]) ? $record[$signTimeField] : '';
                $blmc = isset($record['BLMC']) ? $record['BLMC'] : '未知病程';

                if (!empty($signTime)) {
                    if (strtotime($signTime) >= strtotime($startTime) && strtotime($signTime) <= strtotime($endTime)) {
                        // 只要有一条记录的签名时间在范围内，就认为是有效的
                        $hasValidRecord = true;
                        break;
                    } else {
                        // 签名超时
                        $overtimeRecords[] = [
                            'blmc' => $blmc,
                            'signTime' => $signTime,
                            'BLBH' => $record['BLBH']
                        ];
                    }
                } else {
                    // 无签名时间
                    $overtimeRecords[] = [
                        'blmc' => $blmc,
                        'signTime' => '',
                        'BLBH' => $record['BLBH']
                    ];
                }
            }
            $basis = [];
            $basis[] = "手术名称【" . $surgeryName . "】";
            $basis[] = "(手麻)手术结束时间【" . $surgeryEndTime . "】";

            // 9. 根据检查结果生成质控依据
            if (!$hasValidRecord) {
                if (!empty($overtimeRecords)) {
                    // 有病程记录但均超时或未签名
                    foreach ($overtimeRecords as $record) {

                        if (!empty($record['signTime'])) {
                            $basis[] = "【" . $record['blmc'] . "】【" . $record['signTime'] . "（超时）】";
                        } else {
                            $basis[] = "【" . $record['blmc'] . "】【未签名】";
                        }

                        /* // 添加BLBH
                        if (isset($record['BLBH'])) {
                            $basis["BLBH"] = $record['BLBH'];
                        } */
                    }
                    $allBasisGroups[] = $basis;
                }
            }
        }

        // 10. 将所有质控结果一次性插入到insertData
        if (!empty($allBasisGroups)) {
            $this->insertData[] = [
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title'],
                'basis' => json_encode($allBasisGroups, JSON_UNESCAPED_UNICODE)
            ];
        }

        return true;
    }

    /**
     * 无术后第3天病程记录
     * @param $caseRule
     * @param $ruleId
     * @param $ZYH
     * @return bool
     * @author lzh
     * @date 2023-05-20
     */
    public function rule1008($caseRule, $ruleId, $ZYH)
    {

        // 1. 获取患者入院和出院信息
        $patientInfo = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->first();
        if (empty($patientInfo)) {
            return true; // 找不到患者信息，跳过质控
        }

        $enterTime = $patientInfo['AAB01'];
        $exitTime = $patientInfo['AAC01'];

        // 如果没有出院时间或出院时间为默认值，使用当前时间
        if (empty($exitTime)) {
            $exitTime = date('Y-m-d H:i:s');
        }

        // 3. 计算出院时间-入院时间
        $enterDate = date('Y-m-d', strtotime($enterTime));
        $exitDate = date('Y-m-d', strtotime($exitTime));
        $diffDays = (strtotime($exitDate) - strtotime($enterDate)) / (24 * 3600);

        if ($diffDays <= 1) {
            return true;
        }

        // 1. 查询手术信息
        try {
            $ssapData = SM_SSAP::query()->where('ZYH', '=', $ZYH)->get()->toArray();
            if (empty($ssapData)) {
                return true; // 没有手术记录，不进行质控
            }
        } catch (\Exception $e) {
            Log::error("rule-$ruleId-$ZYH-error: " . $e->getMessage());
            return true; // 查询出错，跳过质控
        }

        // 2. 获取病程记录类型配置
        $ruleMap8046 = RuleWordMap::query()->where('id', '=', 8046)->value('keyword');
        $ruleMap8046 = !empty($ruleMap8046) ? $ruleMap8046 : '50,296,42'; // 病程记录BLLB值
        //是否包含逗号
        if (strpos($ruleMap8046, ',') !== false) {
            $recordTypes = explode(',', $ruleMap8046);
        } else {
            $recordTypes = [$ruleMap8046];
        }

        // 3. 获取查询字段
        // 获取执行时间字段名
        $ruleMap8011 = RuleWordMap::query()->where('id', '=', 8011)->value('keyword');
        $ruleMap8011 = !empty($ruleMap8011) ? $ruleMap8011 : 'ZXSJ'; // 执行时间字段名

        // 获取签名时间字段名
        $signTimeField = RuleWordMap::query()->where('id', '=', 8002)->value('keyword');
        $signTimeField = !empty($signTimeField) ? $signTimeField : 'first_blsy_time';

        //获取8047,8048
        $excludeKeywords8047 = RuleWordMap::getArrayById(8047);

        // 4. 获取出院记录
        try {
            $brxxData = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->first();
            $cysj = $brxxData ? $brxxData->AAC01 : '';
        } catch (\Exception $e) {
            Log::error("rule-$ruleId-$ZYH-error: " . $e->getMessage());
            $cysj = '';
        }

        // 收集所有质控结果
        $allBasisGroups = [];

        // 5. 处理每个手术记录
        foreach ($ssapData as $surgery) {
            if (empty($surgery['SSRQ']) || empty($surgery['ICD9_SSCZMC']) || empty($surgery['JSRQ']) || strpos($surgery['JSRQ'], '1970-01-01') !== false || $surgery['ICD9_SSCZMC'] == 'NULL') {
                continue;
            }
            // 获取手术结束时间
            $surgeryEndTime = $surgery['JSRQ'];
            //手术名称
            $surgeryName = $surgery['ICD9_SSCZMC'];


            //根据手术名称查询SSCZ的SSLB
            $ssCZ = SSCZ::query()->where('SSMC', $surgeryName)->value('SSLB');
            if (empty($ssCZ)) {
                continue;
            }
            //检查是否是手术+介入
            if (!in_array($ssCZ, $excludeKeywords8047)) {
                continue;
            }

            // 检查手术结束时间是否有效
            if (
                empty($surgeryEndTime) ||
                $surgeryEndTime == '1970-01-01 00:00:00' ||
                $surgeryEndTime == '0000-00-00 00:00:00'
            ) {
                continue; // 跳过无效的手术时间
            }

            // 检查是否在手术当天或第二天出院
            $operationDay = date('Y-m-d', strtotime($surgeryEndTime));
            $secondDay = date('Y-m-d', strtotime($operationDay . ' +1 day'));
            $dischargeDay = !empty($cysj) ? date('Y-m-d', strtotime($cysj)) : '';

            //手术完第2天也不质控
            $thirdDay = date('Y-m-d', strtotime($operationDay . ' +2 day'));

            if (!empty($dischargeDay) && ($operationDay == $dischargeDay || $secondDay == $dischargeDay || $thirdDay == $dischargeDay)) {
                continue; // 手术当天或第二天出院，跳过质控
            }

            // 计算术后第3天的时间范围
            $thirdDay = date('Y-m-d', strtotime($operationDay . ' +3 days'));
            $startTime = $thirdDay . ' 00:00:00';
            $endTime = $thirdDay . ' 23:59:59';

            //如果当前时间小于第三天结束时间，跳过
            if (time() < strtotime($endTime)) {
                continue;
            }

            // 6. 使用数据表查询病程记录
            try {
                $records = EMR_BL_BL01::query()
                    ->where('JZHM', $ZYH)
                    ->whereIn('MBLB', $recordTypes)
                    ->where($ruleMap8011, '>=', $startTime)
                    ->where($ruleMap8011, '<=', $endTime)
                    ->get()
                    ->toArray();
            } catch (\Exception $e) {
                Log::error("rule-$ruleId-$ZYH-error: " . $e->getMessage());
                continue; // 查询出错，跳过当前手术记录
            }

            // 7. 检查是否有病程记录
            if (empty($records)) {
                // 记录质控结果 - 无病程记录
                $basis = [];
                $basis[] = "手术名称【" . $surgeryName . "】";
                $basis[] = "(手麻)手术结束时间【" . $surgeryEndTime . "】";
                $basis[] = "术后第3天病程记录【无】";

                $allBasisGroups[] = $basis;
            } else {
                continue;
            }
        }

        // 10. 将所有质控结果一次性插入到insertData
        if (!empty($allBasisGroups)) {
            $this->insertData[] = [
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title'],
                'basis' => json_encode($allBasisGroups, JSON_UNESCAPED_UNICODE)
            ];
        }

        return true;
    }

    /**
     * 术后第3天病程记录超时
     * @param $caseRule
     * @param $ruleId
     * @param $ZYH
     * @return bool
     * @author lzh
     * @date 2023-05-20
     */
    public function rule1062($caseRule, $ruleId, $ZYH)
    {

        // 1. 查询手术信息
        try {
            $ssapData = SM_SSAP::query()->where('ZYH', '=', $ZYH)->get()->toArray();
            if (empty($ssapData)) {
                return true; // 没有手术记录，不进行质控
            }
        } catch (\Exception $e) {
            Log::error("rule-$ruleId-$ZYH-error: " . $e->getMessage());
            return true; // 查询出错，跳过质控
        }

        // 2. 获取病程记录类型配置
        $ruleMap8046 = RuleWordMap::query()->where('id', '=', 8046)->value('keyword');
        $ruleMap8046 = !empty($ruleMap8046) ? $ruleMap8046 : '50,296,42'; // 病程记录BLLB值
        //是否包含逗号
        if (strpos($ruleMap8046, ',') !== false) {
            $recordTypes = explode(',', $ruleMap8046);
        } else {
            $recordTypes = [$ruleMap8046];
        }

        // 3. 获取查询字段
        // 获取执行时间字段名
        $ruleMap8011 = RuleWordMap::query()->where('id', '=', 8011)->value('keyword');
        $ruleMap8011 = !empty($ruleMap8011) ? $ruleMap8011 : 'ZXSJ'; // 执行时间字段名

        // 获取签名时间字段名
        $signTimeField = RuleWordMap::query()->where('id', '=', 8002)->value('keyword');
        $signTimeField = !empty($signTimeField) ? $signTimeField : 'first_blsy_time';

        //获取8047,8048
        $excludeKeywords8047 = RuleWordMap::getArrayById(8047);

        // 4. 获取出院记录
        try {
            $brxxData = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->first();
            $cysj = $brxxData ? $brxxData->AAC01 : '';
        } catch (\Exception $e) {
            Log::error("rule-$ruleId-$ZYH-error: " . $e->getMessage());
            $cysj = '';
        }

        // 收集所有质控结果
        $allBasisGroups = [];

        // 5. 处理每个手术记录
        foreach ($ssapData as $surgery) {
            if (empty($surgery['SSRQ']) || empty($surgery['ICD9_SSCZMC']) || empty($surgery['JSRQ']) || strpos($surgery['JSRQ'], '1970-01-01') !== false || $surgery['ICD9_SSCZMC'] == 'NULL') {
                continue;
            }
            // 获取手术结束时间
            $surgeryEndTime = $surgery['JSRQ'];
            //手术名称
            $surgeryName = $surgery['ICD9_SSCZMC'];

            //根据手术名称查询SSCZ的SSLB
            $ssCZ = SSCZ::query()->where('SSMC', $surgeryName)->value('SSLB');
            if (empty($ssCZ)) {
                continue;
            }
            //检查是否是手术+介入
            if (!in_array($ssCZ, $excludeKeywords8047)) {
                continue;
            }

            // 检查手术结束时间是否有效
            if (
                empty($surgeryEndTime) ||
                $surgeryEndTime == '1970-01-01 00:00:00' ||
                $surgeryEndTime == '0000-00-00 00:00:00'
            ) {
                continue; // 跳过无效的手术时间
            }

            // 检查是否在手术当天或第二天出院
            $operationDay = date('Y-m-d', strtotime($surgeryEndTime));
            $secondDay = date('Y-m-d', strtotime($operationDay . ' +1 day'));
            $dischargeDay = !empty($cysj) ? date('Y-m-d', strtotime($cysj)) : '';

            //手术完第2天也不质控
            $thirdDay = date('Y-m-d', strtotime($operationDay . ' +2 day'));

            if (!empty($dischargeDay) && ($operationDay == $dischargeDay || $secondDay == $dischargeDay || $thirdDay == $dischargeDay)) {
                continue; // 手术当天或第二天出院，跳过质控
            }

            // 计算术后第3天的时间范围
            $thirdDay = date('Y-m-d', strtotime($operationDay . ' +3 days'));
            $startTime = $thirdDay . ' 00:00:00';
            $endTime = $thirdDay . ' 23:59:59';
            //如果当前时间小于第三天结束时间，跳过
            if (time() < strtotime($endTime)) {
                continue;
            }

            // 6. 使用数据表查询病程记录
            try {
                $records = EMR_BL_BL01::query()
                    ->where('JZHM', $ZYH)
                    ->whereIn('MBLB', $recordTypes)
                    ->where($ruleMap8011, '>=', $startTime)
                    ->where($ruleMap8011, '<=', $endTime)
                    ->get()
                    ->toArray();
            } catch (\Exception $e) {
                Log::error("rule-$ruleId-$ZYH-error: " . $e->getMessage());
                continue; // 查询出错，跳过当前手术记录
            }

            // 7. 检查是否有病程记录
            if (empty($records)) {
                // 记录质控结果 - 无病程记录
                continue;
            }

            // 8. 检查病程记录的签名时间是否在规定范围内
            $hasValidRecord = false;
            $overtimeRecords = [];

            foreach ($records as $record) {

                // 获取签名时间
                $signTime = isset($record[$signTimeField]) ? $record[$signTimeField] : '';
                $blmc = isset($record['BLMC']) ? $record['BLMC'] : '未知病程';

                if (!empty($signTime)) {
                    if (strtotime($signTime) >= strtotime($startTime) && strtotime($signTime) <= strtotime($endTime)) {
                        // 只要有一条记录的签名时间在范围内，就认为是有效的
                        $hasValidRecord = true;
                        break;
                    } else {
                        // 签名超时
                        $overtimeRecords[] = [
                            'blmc' => $blmc,
                            'signTime' => $signTime,
                            'BLBH' => $record['BLBH']
                        ];
                    }
                } else {
                    // 无签名时间
                    $overtimeRecords[] = [
                        'blmc' => $blmc,
                        'signTime' => '',
                        'BLBH' => $record['BLBH']
                    ];
                }
            }

            $basis = [];
            $basis[] = "手术名称【" . $surgeryName . "】";
            $basis[] = "(手麻)手术结束时间【" . $surgeryEndTime . "】";

            // 9. 根据检查结果生成质控依据
            if (!$hasValidRecord) {
                if (!empty($overtimeRecords)) {
                    // 有病程记录但均超时或未签名
                    foreach ($overtimeRecords as $record) {

                        if (!empty($record['signTime'])) {
                            $basis[] = "【" . $record['blmc'] . "】【" . $record['signTime'] . "（超时）】";
                        } else {
                            $basis[] = "【" . $record['blmc'] . "】【未签名】";
                        }

                        // 添加BLBH
                        /* if (isset($record['BLBH'])) {
                            $basis["BLBH"] = $record['BLBH'];
                        } */
                    }
                    $allBasisGroups[] = $basis;
                }
            }
        }

        // 10. 将所有质控结果一次性插入到insertData
        if (!empty($allBasisGroups)) {
            $this->insertData[] = [
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title'],
                'basis' => json_encode($allBasisGroups, JSON_UNESCAPED_UNICODE)
            ];
        }

        return true;
    }

    /**
     * 出院前一日或当日无上级医师查房记录
     * @param $caseRule
     * @param $ruleId
     * @param $ZYH
     * @return bool
     * @author lzh
     */
    public function rule1009($caseRule, $ruleId, $ZYH)
    {

        // 1. 获取患者信息，使用ZY_BRRY表
        $patientInfo = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->first();
        if (empty($patientInfo)) {
            return true;
        }

        // 2. 获取入院时间和出院时间
        $enterTime = $patientInfo['AAB01'];
        $exitTime = $patientInfo['AAC01'];

        // 如果没有出院时间或出院时间为默认值，使用当前时间
        if (empty($exitTime) || $exitTime == '1970-01-01 00:00:00' || $exitTime == '0000-00-00 00:00:00') {
            return true; // 没有有效的出院时间，不进行质控
        }

        // 3. 计算住院天数，如果住院不足1天则不质控
        $enterDate = date('Y-m-d', strtotime($enterTime));
        $exitDate = date('Y-m-d', strtotime($exitTime));
        $daysDiff = ceil((strtotime($exitDate) - strtotime($enterDate)) / (24 * 3600));

        if ($daysDiff <= 1) {
            return true; // 住院天数小于等于1天，不进行质控
        }

        // 2. 获取患者科室
        $brks = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->value('BRKS');
        if (empty($brks)) {
            return true; // 没有科室信息
        }

        // 4. 判断是否为产科且需要质控
        //获取产科代码id8025
        $id8057 = RuleWordMap::query()->where('id', '=', 8057)->value('keyword');
        //是否包含逗号
        if (strpos($id8057, ',') !== false) {
            $id8057 = explode(',', $id8057);
        } else {
            $id8057 = [$id8057];
        }
        //是否包含产科代码
        if (in_array($brks, $id8057)) {
            return true; // 是产科，不进行质控
        }

        // 4. 获取出院/离院医嘱配置项
        $ruleMap8000 = RuleWordMap::query()->where('id', '=', 8000)->value('keyword');
        $ruleMap8000 = !empty($ruleMap8000) ? $ruleMap8000 : "出院,离院";  // 医嘱名称包含的关键字

        // 判断是否包含逗号
        if (strpos($ruleMap8000, ',') !== false) {
            $exitKeywords = explode(',', $ruleMap8000);
        } else {
            $exitKeywords = [$ruleMap8000];
        }

        // 5. 从MySQL查询医嘱是否包含出院/离院关键字
        $yzbQuery = Yzb::query()->where('ZYH', $ZYH)->get()->toArray();
        $yzbData = [];
        foreach ($yzbQuery as $record) {
            foreach ($exitKeywords as $keyword) {
                if (strpos($record['YZMC'], $keyword) !== false) {
                    $yzbData[] = $record;
                    break;
                }
            }
        }

        // 如果没有出院/离院医嘱，则不进行质控
        if (empty($yzbData)) {
            return true;
        }

        // 6. 获取出院前后24小时的病程记录
        // 获取病程记录类型
        $ruleMap8049 = RuleWordMap::query()->where('id', '=', 8049)->value('keyword');
        $ruleMap8049 = !empty($ruleMap8049) ? $ruleMap8049 : '296,50'; // 病程记录类型BLLB值，确保是字符串

        // 判断是否包含逗号
        if (strpos($ruleMap8049, ',') !== false) {
            $blTypes = explode(',', $ruleMap8049);
        } else {
            $blTypes = [$ruleMap8049];
        }

        // 获取查房记录的时间字段
        $ruleMap8011 = RuleWordMap::query()->where('id', '=', 8011)->value('keyword');
        $ruleMap8011 = !empty($ruleMap8011) ? $ruleMap8011 : 'ZXSJ'; // 查房记录的时间字段名

        // 出院前后24小时的时间范围
        //$exitTimeStart = date('Y-m-d H:i:s', strtotime($exitTime) - 24 * 3600);
        //出院前一天的00:00:00
        $exitTimeStart = date('Y-m-d 00:00:00', strtotime($exitTime) - 24 * 3600);
        //出院当天的23:59:59
        $exitTimeEnd = date('Y-m-d 23:59:59', strtotime($exitTime));
        //$exitTimeEnd = date('Y-m-d H:i:s', strtotime($exitTime) + 24 * 3600);

        try {
            $bl01Data = EMR_BL_BL01::query()
                ->where('JZHM', $ZYH)
                ->whereIn('MBLB', $blTypes)
                ->where($ruleMap8011, '>=', $exitTimeStart)
                ->where($ruleMap8011, '<=', $exitTimeEnd)
                ->get()
                ->toArray();
        } catch (\Exception $e) {
            Log::error("rule-$ruleId-$ZYH-error: " . $e->getMessage());
            return true; // 查询出错时跳过
        }

        if (empty($bl01Data)) {
            $basis = [];
            $basis[] = "出院时间【" . $exitTime . "】";
            $basis[] = "上级医师查房记录【无】";
            $allBasisGroups[] = $basis;
            $this->insertData[] = [
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title'],
                'basis' => json_encode($allBasisGroups, JSON_UNESCAPED_UNICODE)
            ];
            return true;
        }

        // 7. 获取上级医师职称（主任/主治医师）
        $ruleMap8012 = RuleWordMap::query()->where('id', '=', 8012)->value('keyword');
        $ruleMap8012 = !empty($ruleMap8012) ? $ruleMap8012 : '主治,主任'; // 上级医师

        // 判断是否包含逗号
        if (strpos($ruleMap8012, ',') !== false) {
            $highLevelTitles = explode(',', $ruleMap8012);
        } else {
            $highLevelTitles = [$ruleMap8012];
        }

        // 获取首次签名时间字段名
        $firstBlsyTimeField = RuleWordMap::query()->where('id', '=', 8002)->value('keyword');
        $firstBlsyTimeField = !empty($firstBlsyTimeField) ? $firstBlsyTimeField : 'first_blsy_time';

        // 8. 检查每条病程记录是否有上级医师签名
        $validRecords = [];
        $invalidRecords = [];

        foreach ($bl01Data as $record) {
            try {
                $blsy = EMR_BL_BLSY::query()->where('BLBH', $record['BLBH'])->where('FG_ACTIVE', 1)->pluck('SYYS')->toArray();
                $blsy = array_unique($blsy);
            } catch (\Exception $e) {
                Log::error("rule-$ruleId-$ZYH-error: " . $e->getMessage());
                continue; // 查询出错时跳过当前记录
            }

            $hasHighLevelDoctor = false;
            $highLevelDoctors = [];

            foreach ($blsy as $doctor) {
                try {
                    $staffInfo = STAFF::query()->where('code', $doctor)->first();
                    if (!$staffInfo) continue;

                    $staff = $staffInfo->ygjb_text;
                    $doctorName = $staffInfo->name;

                    // 检查是否是上级医师
                    foreach ($highLevelTitles as $title) {
                        if (strpos($staff, $title) !== false) {
                            $hasHighLevelDoctor = true;
                            $highLevelDoctors[] = $doctorName . '（' . $staff . '）';
                            break;
                        }
                    }
                } catch (\Exception $e) {
                    Log::error("rule-$ruleId-$ZYH-error: " . $e->getMessage());
                    continue; // 查询出错时跳过当前医生
                }
            }

            if ($hasHighLevelDoctor) {
                $record['doctor_info'] = $highLevelDoctors;
                $firstBlsyTime = isset($record[$firstBlsyTimeField]) ? $record[$firstBlsyTimeField] : '';

                if (empty($firstBlsyTime)) {
                    $record['sign_status'] = '未签名';
                    $invalidRecords[] = $record;
                } else {
                    $record['sign_status'] = '正常';
                    $validRecords[] = $record;
                }
            }
        }

        // 9. 如果没有找到有效的上级医师查房记录，记录质控
        $allBasisGroups = [];

        if (empty($validRecords)) {
            $basis = [];
            $basis[] = "出院时间【" . $exitTime . "】";

            // 添加医师信息
            if (!empty($invalidRecords)) {
                foreach ($invalidRecords as $record) {
                    if (isset($record['doctor_info'])) {
                        $doctorInfo = implode("，", $record['doctor_info']);
                        $signInfo = "";

                        if ($record['sign_status'] == '未签名') {
                            $signInfo = "未签名";
                        } else {
                            $signInfo = $record[$firstBlsyTimeField] . "（超时）";
                        }

                        $basis[] = "【" . $record['BLMC'] . "】【" . $doctorInfo . "】首次签名时间【" . $signInfo . "】";
                    }
                }
            } else {
                // 当前出院前后24小时没有任何上级医师查房记录
                $basis[] = "上级医师查房记录【无】";
            }

            // 添加BLBH（如果有）
            if (!empty($invalidRecords)) {
                $basis["BLBH"] = $invalidRecords[0]['BLBH'];
            }

            $allBasisGroups[] = $basis;
        }

        // 10. 将所有质控结果一次性插入到insertData
        if (!empty($allBasisGroups)) {
            $this->insertData[] = [
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title'],
                'basis' => json_encode($allBasisGroups, JSON_UNESCAPED_UNICODE)
            ];
        }

        return true;
    }

    /**
     * 出院前一日或当日无上级医师查房记录 新（查blmc）
     * @param $caseRule
     * @param $ruleId
     * @param $ZYH
     * @return bool
     * @author lzh
     */
    public function rule1051($caseRule, $ruleId, $ZYH)
    {

        // 1. 获取患者信息，使用ZY_BRRY表
        $patientInfo = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->first();
        if (empty($patientInfo)) {
            return true;
        }

        // 2. 获取入院时间和出院时间
        $enterTime = $patientInfo['AAB01'];
        $exitTime = $patientInfo['AAC01'];

        // 如果没有出院时间或出院时间为默认值，使用当前时间
        if (empty($exitTime) || $exitTime == '1970-01-01 00:00:00' || $exitTime == '0000-00-00 00:00:00') {
            return true; // 没有有效的出院时间，不进行质控
        }

        // 3. 计算住院天数，如果住院不足1天则不质控
        $enterDate = date('Y-m-d', strtotime($enterTime));
        $exitDate = date('Y-m-d', strtotime($exitTime));
        $daysDiff = ceil((strtotime($exitDate) - strtotime($enterDate)) / (24 * 3600));

        if ($daysDiff <= 1) {
            return true; // 住院天数小于等于1天，不进行质控
        }

        //获取当前时间
        $currentDate = Carbon::now()->toDateTimeString();
        //获取出院时间的23:59:59
        $exitTimeEnd = date('Y-m-d 23:59:59', strtotime($exitTime));
        //如果当前时间小于出院时间+23:59:59就跳过
        if (strtotime($currentDate) < strtotime($exitTimeEnd)) {
            return true;
        }

        // 2. 获取患者科室
        $brks = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->value('BRKS');
        if (empty($brks)) {
            return true; // 没有科室信息
        }

        // 4. 判断是否为产科且需要质控
        //获取产科代码id8025
        $id8057 = RuleWordMap::query()->where('id', '=', 8057)->value('keyword');
        //是否包含逗号
        if (strpos($id8057, ',') !== false) {
            $id8057 = explode(',', $id8057);
        } else {
            $id8057 = [$id8057];
        }
        //是否包含产科代码
        if (in_array($brks, $id8057)) {
            return true; // 是产科，不进行质控
        }

        // 4. 获取出院/离院医嘱配置项
        $ruleMap8000 = RuleWordMap::getArrayById(8000);
        // 5. 查询医嘱是否包含出院/离院关键字
        $hasLyYzb = false;
        $hasSwYzb = false;
        $yzb = Yzb::query()->where('ZYH', '=', $ZYH)->get()->toArray();
        foreach ($yzb as $key => $value) {
            foreach ($ruleMap8000 as $v) {
                if (strpos($value['YZMC'], $v) !== false) {
                    $hasLyYzb = true;
                    break;
                }
                if (strpos($value['YZMC'], '死亡') !== false) {
                    $hasSwYzb = true;
                    break;
                }
            }
        }
        if (!$hasLyYzb || $hasSwYzb) {
            return true; // 如果没有有效的关键词，跳过
        }
        // 6. 获取出院前后24小时的病程记录
        // 获取病程记录类型
        $blTypes = RuleWordMap::getArrayById(8049);
        // 获取查房记录的时间字段
        $ruleMap8011 = RuleWordMap::query()->where('id', '=', 8011)->value('keyword');
        $ruleMap8011 = !empty($ruleMap8011) ? $ruleMap8011 : 'ZXSJ'; // 查房记录的时间字段名

        // 出院前后24小时的时间范围
        //$exitTimeStart = date('Y-m-d H:i:s', strtotime($exitTime) - 24 * 3600);
        //出院前一天的00:00:00
        $exitTimeStart = date('Y-m-d 00:00:00', strtotime($exitTime) - 24 * 3600);
        //出院当天的23:59:59
        $exitTimeEnd = date('Y-m-d 23:59:59', strtotime($exitTime));
        //$exitTimeEnd = date('Y-m-d H:i:s', strtotime($exitTime) + 24 * 3600);

        $bl01Data = EMR_BL_BL01::query()
            ->where('JZHM', $ZYH)
            ->whereIn('MBLB', $blTypes)
            ->where($ruleMap8011, '>=', $exitTimeStart)
            ->where($ruleMap8011, '<=', $exitTimeEnd)
            ->get()
            ->toArray();
        if (empty($bl01Data)) {
            $basis = [];
            $basis[] = "出院时间【" . $exitTime . "】";
            $basis[] = "上级医师查房记录【无】";
            $allBasisGroups[] = $basis;
            $this->insertData[] = [
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title'],
                'basis' => json_encode($allBasisGroups, JSON_UNESCAPED_UNICODE)
            ];
            return true;
        }

        // 7. 获取上级医师职称（主任/主治医师）
        $highLevelTitles = RuleWordMap::getArrayById(8012);
        // 获取首次签名时间字段名
        $firstBlsyTimeField = RuleWordMap::query()->where('id', '=', 8002)->value('keyword');
        $firstBlsyTimeField = !empty($firstBlsyTimeField) ? $firstBlsyTimeField : 'first_blsy_time';

        // 8. 检查每条病程记录是否有上级医师签名
        $validRecords = [];
        $invalidRecords = [];

        $hasHighLevelDoctor = false;

        $staff = Staff::query()->get()->toArray();
        $staff = array_column($staff, null, 'code');

        foreach ($bl01Data as $record) {
            $blmc = $record['BLMC'];

            // 检查是否是上级医师
            foreach ($highLevelTitles as $title) {
                if (strpos($blmc, $title) !== false) {
                    $hasHighLevelDoctor = true;
                    break;
                }
            }
            if ($hasHighLevelDoctor == true) {
                break;
            }

            $blsy = EMR_BL_BLSY::query()->where('BLBH', $record['BLBH'])->where('FG_ACTIVE', 1)->get()->toArray();
            foreach ($blsy as $item) {
                if (!empty($staff[$item['SYYS']])) {
                    foreach ($highLevelTitles as $keyword) {
                        if (strpos($staff[$item['SYYS']]['ygjb_text'], $keyword) === false) {
                            $hasHighLevelDoctor = true;
                            break;
                        }
                    }
                }
            }
        }
        if (!$hasHighLevelDoctor) {
            $basis = [];
            $basis[] = "出院时间【" . $exitTime . "】";
            $basis[] = "上级医师查房记录【无】";
            $allBasisGroups[] = $basis;
            $this->insertData[] = [
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title'],
                'basis' => json_encode($allBasisGroups, JSON_UNESCAPED_UNICODE)
            ];
        }

        return true;
    }

    /**
     * 患者死亡后7天内未完成死亡病例讨论结论记录
     * @param $caseRule
     * @param $ruleId
     * @param $ZYH
     * @return bool
     * @author lzh
     */
    public function rule1010($caseRule, $ruleId, $ZYH)
    {

        // 1. 获取死亡医嘱关键字
        $exitKeywords = RuleWordMap::getArrayById(8003);

        // 2. 查询医嘱是否包含死亡关键字
        $yzb = Yzb::query()->where('ZYH', '=', $ZYH)->get()->toArray();
        $hasSwYzb = false;
        foreach ($yzb as $key => $value) {
            if (strpos($value['YZMC'], '死亡') !== false) {
                $hasSwYzb = true;
                break;
            }
        }
        if (empty($hasSwYzb)) {
            return true;
        }

        // 获取死亡日期
        $deathTime = "";

        // 如果医嘱中没有开嘱时间，则从出院记录中获取
        $exitTime = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->value('AAC01');
        if (empty($exitTime)) {
            return true; // 无法确定死亡时间，跳过质控
        }
        $blData = new BlDataFormatService();
        $bl01 = EMR_BL_BL01::query()->where("JZHM", $ZYH)->where('MBLB', 288)->get()->toArray();
        if (empty($bl01)) {

            $this->insertData[] = [
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title'],
                'basis' => json_encode([['死亡记录【无】']], JSON_UNESCAPED_UNICODE)
            ];
            return false;
        }
        $swHJNR = EMR_BL_BLXG::query()->where("BLBH", $bl01[0]['BLBH'])->get()->toArray();
        if (empty($swHJNR)) {

            $this->insertData[] = [
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title'],
                'basis' => json_encode([['死亡记录【无】']], JSON_UNESCAPED_UNICODE)
            ];
            return false;
        }
        // 获取$swHJNR中的死亡时间
        $swDeathTime = '';
        if (preg_match('/死亡时间：\s*(\d{4}年\d{1,2}月\d{1,2}日 \d{1,2}:\d{2}|\d{4}-\d{1,2}-\d{1,2} \d{1,2}:\d{2})/i', $swHJNR[0]['HJNR'], $matches)) {
            $swDeathTime = $matches[1];
            $swDeathTime = $blData->formatTime($swDeathTime);
        }
        if (empty($swDeathTime)) {

            $this->insertData[] = [
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title'],
                'basis' => json_encode([['死亡时间获取失败']], JSON_UNESCAPED_UNICODE)
            ];
            return false;
        }

        $discussionMBLB = RuleWordMap::getArrayById(8029);
        $records = EMR_BL_BL01::query()->where("JZHM", $ZYH)->whereIn('MBLB', $discussionMBLB)
            ->whereBetween('ZXSJ', [$swDeathTime, date('Y-m-d H:i:s', strtotime($swDeathTime . ' +7 day'))])
            ->get()->toArray();
        if (empty($records)) {
            $basis = [];
            $basis[] = "死亡时间【{$deathTime}】";
            $basis[] = "死亡病例讨论结论记录【无】";

            $allBasisGroups[] = $basis;
        } else {
            // 7. 检查记录的签名时间是否在7天期限内
            $hasValidRecord = false;

            //根据blbh获取hjnr

            foreach ($records as $record) {
                // 获取签名时间字段
                $signTimeField = RuleWordMap::query()->where('id', '=', 8002)->value('keyword');
                $signTimeField = !empty($signTimeField) ? $signTimeField : 'first_blsy_time';

                // 获取签名信息
                $signInfo = EMR_BL_BLSY::query()
                    ->where('BLBH', $record['BLBH'])
                    ->where('FG_ACTIVE', 1)
                    ->first();

                $signTime = "";
                if ($signInfo) {
                    $signTime = $signInfo['SYSJ'] ?? '';
                }

                // 如果没有签名时间，使用病程记录时间
                if (empty($signTime)) {
                    $signTime = $record[$signTimeField] ?? '';
                }

                // 检查签名时间是否在期限内
                if (!empty($signTime)) {
                    if (strtotime($signTime) <= strtotime($swDeathTime)) {
                        $hasValidRecord = true;
                        break;
                    } else {
                        // 签名时间超过期限
                        $basis = [];
                        $basis[] = "死亡时间【{$deathTime}】";
                        $basis[] = "【{$record['BLMC']}（{$signTime}）】（超时）";

                        if (isset($record['BLBH'])) {
                            $basis["BLBH"] = $record['BLBH'];
                        }

                        $allBasisGroups[] = $basis;
                    }
                } else {
                    // 无签名
                    $basis = [];
                    $basis[] = "死亡时间【{$deathTime}】";
                    $basis[] = "【{$record['BLMC']}（未签名）】";

                    if (isset($record['BLBH'])) {
                        $basis["BLBH"] = $record['BLBH'];
                    }

                    $allBasisGroups[] = $basis;
                }
            }

            // 如果所有记录都没有有效签名，且没有收集到任何异常记录，添加一条总体提示
            if (!$hasValidRecord && empty($allBasisGroups)) {
                $basis = [];
                $basis[] = "死亡时间【{$deathTime}】";
                $basis[] = "死亡病例讨论结论记录【未按时完成】";

                $allBasisGroups[] = $basis;
            }
        }

        // 8. 将所有质控结果一次性插入到insertData
        if (!empty($allBasisGroups)) {
            $this->insertData[] = [
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title'],
                'basis' => json_encode($allBasisGroups, JSON_UNESCAPED_UNICODE)
            ];
            return false;
        }

        return true;
    }

    /**
     * 危急值接收后24小时内未完成危急值记录
     * @param $caseRule
     * @param $ruleId
     * @param $ZYH
     * @return bool
     * @author claude
     */
    public function rule1011($caseRule, $ruleId, $ZYH)
    {

        // 1. 获取危急值记录相关配置
        // 获取病程记录类型
        $ruleMap8010 = RuleWordMap::query()->where('id', '=', 8010)->value('keyword');
        $ruleMap8010 = !empty($ruleMap8010) ? $ruleMap8010 : '294'; // 病程记录类型BLLB值，确保是字符串

        // 判断是否包含逗号
        if (strpos($ruleMap8010, ',') !== false) {
            $blTypes = explode(',', $ruleMap8010);
        } else {
            $blTypes = [$ruleMap8010];
        }

        // 获取危急值记录的时间字段
        $ruleMap8011 = RuleWordMap::query()->where('id', '=', 8011)->value('keyword');
        $ruleMap8011 = !empty($ruleMap8011) ? $ruleMap8011 : 'ZXSJ'; // 执行时间字段名

        // 获取危急值记录的名称关键词
        $ruleMap8030 = RuleWordMap::query()->where('id', '=', 8030)->value('keyword');
        $ruleMap8030 = !empty($ruleMap8030) ? $ruleMap8030 : '危急值记录'; // 危急值记录关键词

        // 获取首次签名时间字段名
        $firstBlsyTimeField = RuleWordMap::query()->where('id', '=', 8002)->value('keyword');
        $firstBlsyTimeField = !empty($firstBlsyTimeField) ? $firstBlsyTimeField : 'first_blsy_time';

        // 2. 获取危急值数据,按照WJZSJ去重
        try {
            $wjzData = WJZ::query()->where('ZYH', '=', $ZYH)->groupBy('WJZSJ')->get(['WJZSJ'])->toArray();
            if (empty($wjzData)) {
                return true; // 没有危急值数据，不进行质控
            }
        } catch (\Exception $e) {
            Log::error("rule-$ruleId-$ZYH-error: " . $e->getMessage());
            return true; // 查询出错，跳过质控
        }

        // 3. 收集所有质控结果
        $allBasisGroups = [];

        // 4. 处理每条危急值记录
        foreach ($wjzData as $wjz) {
            // 提取危急值信息
            $wjzsj = isset($wjz['WJZSJ']) ? $wjz['WJZSJ'] : '';
            //获取当前时间
            $currentDate = Carbon::now()->toDateTimeString();
            $oneDayAfterSurgery = date('Y-m-d H:i:s', strtotime($wjzsj) + 24 * 3600);
            //如果当前时间小于危急值推送时间+24小时就跳过
            if (strtotime($currentDate) < strtotime($oneDayAfterSurgery)) {
                continue;
            }
            //根据wjzsj在重新获取危急值内容,多个危急值内容用逗号分割
            $wjznr = WJZ::query()->where('WJZSJ', '=', $wjzsj)->get(['WJZNR'])->toArray();
            $wjznr = !empty($wjznr) ? $wjznr : '';
            //使用(危急值内容)分割
            $wjznr_str = '';
            if (!empty($wjznr)) {
                foreach ($wjznr as $wjz) {
                    $wjznr_str .= '(' . $wjz['WJZNR'] . ')';
                }
            }

            // 检查危急值时间是否有效
            if (empty($wjzsj) || $wjzsj == '1970-01-01 00:00:00' || $wjzsj == '0000-00-00 00:00:00') {
                continue; // 跳过无效的危急值时间
            }

            // 为当前危急值创建基础信息
            $wjzBasis = [];
            $wjzBasis[] = "危急值内容【" . $wjznr_str . "】";
            $wjzBasis[] = "危急值推送时间【" . $wjzsj . "】";

            // 计算24小时期限
            $timeLimit = date('Y-m-d H:i:s', strtotime($wjzsj) + 24 * 3600);

            // 查询病历记录
            try {
                $bl01Data = EMR_BL_BL01::query()
                    ->where('JZHM', $ZYH)
                    ->whereIn('BLLB', $blTypes)
                    ->where('BLMC', 'like', '%' . $ruleMap8030 . '%')
                    ->where($ruleMap8011, '>=', date('Y-m-d H:i', strtotime($wjzsj))) //精确到分钟就行
                    ->where($ruleMap8011, '<=', $timeLimit)
                    ->orderBy($ruleMap8011, 'asc')
                    ->get()
                    ->toArray();
            } catch (\Exception $e) {
                Log::error("rule-$ruleId-$ZYH-error: " . $e->getMessage());
                continue; // 查询出错时跳过当前危急值记录
            }

            if (!empty($bl01Data)) {
                $bl01Data = [$bl01Data[0]];
            }

            // 5. 检查是否有有效的危急值记录
            $validRecords = [];
            $invalidRecords = [];

            if (!empty($bl01Data)) {
                foreach ($bl01Data as $record) {
                    // 首次签名时间检查
                    $firstBlsyTime = isset($record[$firstBlsyTimeField]) ? $record[$firstBlsyTimeField] : '';

                    if (empty($firstBlsyTime)) {
                        // 未签名
                        $record['sign_status'] = '未签名';
                        $invalidRecords[] = $record;
                    } else {
                        // 检查签名时间是否在24小时期限内
                        if (strtotime($firstBlsyTime) <= strtotime($timeLimit)) {
                            // 有效记录
                            $validRecords[] = $record;
                        } else {
                            // 签名时间超过24小时
                            $record['sign_status'] = '超时';
                            $invalidRecords[] = $record;
                        }
                    }
                }
            }

            // 6. 只有当没有有效记录时，才添加质控结果
            if (empty($validRecords)) {
                // 创建详情记录
                $detailItems = [];

                if (!empty($invalidRecords)) {
                    foreach ($invalidRecords as $record) {
                        $signInfo = "";

                        if (isset($record['sign_status']) && $record['sign_status'] == '未签名') {
                            $signInfo = "未签名";
                        } else {
                            $signInfo = isset($record[$firstBlsyTimeField]) ? $record[$firstBlsyTimeField] . "（超时）" : "（超时）";
                        }

                        $detailItems[] = "危急值记录【" . $record['BLMC'] . "】首次签名时间【" . $signInfo . "】";

                        // 只保存第一个病历号，与rule133和rule134保持一致
                        if (empty($detailItems["BLBH"]) && isset($record['BLBH'])) {
                            $detailItems["BLBH"] = $record['BLBH'];
                        }
                    }
                } else {
                    // 没有任何危急值记录
                    $detailItems[] = "危急值记录【无】";
                }

                // 合并基础信息和详情
                $wjzGroup = array_merge($wjzBasis, $detailItems);

                // 将这条危急值记录的质控结果添加到总结果中
                $allBasisGroups[] = $wjzGroup;
            }
        }

        // 7. 将所有质控结果添加到insertData
        if (!empty($allBasisGroups)) {
            $this->insertData[] = [
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title'],
                'basis' => json_encode($allBasisGroups, JSON_UNESCAPED_UNICODE)
            ];
        }

        return true;
    }

    /**
     * 输血结束后24小时内未完成输血记录
     * @param $caseRule
     * @param $ruleId
     * @param $ZYH
     * @return bool
     * @author claude
     */
    public function rule1012($caseRule, $ruleId, $ZYH)
    {

        // 1. 获取输血病程记录相关配置
        // 获取输血病程记录MBLB值
        $blTypes = RuleWordMap::getArrayById(8031);

        // 获取执行时间字段名
        $ruleMap8011 = RuleWordMap::query()->where('id', '=', 8011)->value('keyword');
        $ruleMap8011 = !empty($ruleMap8011) ? $ruleMap8011 : 'ZXSJ'; // 执行时间字段名
        // 获取首次签名时间字段名
        $firstBlsyTimeField = RuleWordMap::query()->where('id', '=', 8002)->value('keyword');
        $firstBlsyTimeField = !empty($firstBlsyTimeField) ? $firstBlsyTimeField : 'first_blsy_time';
        //8053
        $sxExcludeKeywords = RuleWordMap::getArrayById(8053);
        //8054
        $sxBl01ExcludeKeywords = RuleWordMap::getArrayById(8054);
        //8055 红细胞,血浆,凝血因子,血小板
        $sxBl01ExcludeKeywords2 = RuleWordMap::getArrayById(8055);
        //8056 输血
        $sxBl01ExcludeKeywords3 = RuleWordMap::getArrayById(8056);

        // 2. 获取输血记录数据
        try {
            $sxData = ZY_SS::query()->where('ZYH', '=', $ZYH)->get()->toArray();
            if (empty($sxData)) {
                return true; // 没有输血记录，不进行质控
            }
        } catch (\Exception $e) {
            Log::error("rule-$ruleId-$ZYH-error: " . $e->getMessage());
            return true; // 查询出错，跳过质控
        }

        // 3. 收集所有质控结果
        $allBasisGroups = [];

        // 4. 处理每条输血记录
        foreach ($sxData as $sx) {
            // 提取输血结束时间
            $jssj = isset($sx['JSSJ']) ? $sx['JSSJ'] : '';
            // 检查输血结束时间是否有效
            if (empty($jssj) || $jssj == '1970-01-01 00:00:00' || $jssj == '0000-00-00 00:00:00') {
                continue; // 跳过无效的输血结束时间
            }
            //获取当前时间
            $currentDate = Carbon::now()->toDateTimeString();
            $oneDayAfterSurgery = date('Y-m-d H:i:s', strtotime($jssj) + 24 * 3600);
            //如果当前时间小于输血结束时间+24小时就跳过
            if (strtotime($currentDate) < strtotime($oneDayAfterSurgery)) {
                continue;
            }
            //开始时间
            $kssj = isset($sx['KSSJ']) ? $sx['KSSJ'] : '';
            //输血项目CFX
            $cfx = isset($sx['CFX']) ? $sx['CFX'] : '';
            //输血量SZL
            $szl = isset($sx['SZL']) ? $sx['SZL'] : '';
            $isExclude = false;
            foreach ($sxExcludeKeywords as $keyword) {
                if (strpos($cfx, $keyword) !== false) {
                    $isExclude = true;
                }
            }

            //查询开始时间当天的手术记录,病历bl01表,MBLB=306,ZXSJ和kssj对比在一天的
            $sxkssj = date('Y-m-d 00:00:00', strtotime($kssj));
            $sxjssj = date('Y-m-d 23:59:59', strtotime($kssj));
            $bl01Data = EMR_BL_BL01::query()
                ->where('EMR_BL_BL01.JZHM', $ZYH)
                ->where('EMR_BL_BL01.MBLB', 306)
                ->where('EMR_BL_BL01.ZXSJ', '>=', $sxkssj)
                ->where('EMR_BL_BL01.ZXSJ', '<=', $sxjssj)
                ->leftJoin('EMR_BL_BLXG', 'EMR_BL_BL01.BLBH', '=', 'EMR_BL_BLXG.BLBH')
                ->get(['EMR_BL_BLXG.HJNR'])
                ->toArray();

            if (!empty($bl01Data)) {
                foreach ($bl01Data as $record) {
                    //先查看是否包含8056,如果包含就是ture,如果不包含查看是否同时包含8054和8055
                    foreach ($sxBl01ExcludeKeywords3 as $keyword) {
                        if (strpos($record['HJNR'], $keyword) !== false) {
                            $isExclude = true;
                            break;
                        }
                    }
                    $isExclude2 = false;
                    $isExclude3 = false;
                    foreach ($sxBl01ExcludeKeywords as $keyword) {
                        if (strpos($record['HJNR'], $keyword) !== false) {
                            $isExclude2 = true;
                        }
                    }
                    foreach ($sxBl01ExcludeKeywords2 as $keyword) {
                        if (strpos($record['HJNR'], $keyword) !== false) {
                            $isExclude3 = true;
                        }
                    }
                    if ($isExclude2 && $isExclude3) {
                        $isExclude = true;
                        break;
                    }
                }
            }

            if ($isExclude) {
                continue;
            }



            // 为当前输血记录创建基础信息
            $sxBasis = [];
            $sxBasis[] = $cfx . "【" . $szl . "】";
            $sxBasis[] = "输血结束时间【" . $jssj . "】";


            // 计算24小时期限
            $timeLimit = date('Y-m-d H:i:s', strtotime($jssj) + 24 * 3600);

            // 查询病历记录
            try {
                $bl01Data = EMR_BL_BL01::query()
                    ->where('JZHM', $ZYH)
                    ->whereIn('MBLB', $blTypes)
                    ->where($ruleMap8011, '>=', $jssj)
                    ->where($ruleMap8011, '<=', $timeLimit)
                    ->get()
                    ->toArray();
            } catch (\Exception $e) {
                Log::error("rule-$ruleId-$ZYH-error: " . $e->getMessage());
                continue; // 查询出错时跳过当前输血记录
            }

            // 5. 检查是否有有效的输血病程记录
            $validRecords = [];
            $invalidRecords = [];

            if (!empty($bl01Data)) {
                foreach ($bl01Data as $record) {
                    // 首次签名时间检查
                    $firstBlsyTime = isset($record[$firstBlsyTimeField]) ? $record[$firstBlsyTimeField] : '';

                    if (empty($firstBlsyTime)) {
                        // 未签名
                        $record['sign_status'] = '未签名';
                        $invalidRecords[] = $record;
                    } else {
                        // 检查签名时间是否在24小时期限内
                        if (strtotime($firstBlsyTime) <= strtotime($timeLimit)) {
                            // 有效记录
                            $validRecords[] = $record;
                        } else {
                            // 签名时间超过24小时
                            $record['sign_status'] = '超时';
                            $invalidRecords[] = $record;
                        }
                    }
                }
            }

            // 6. 只有当没有有效记录时，才添加质控结果
            if (empty($validRecords)) {
                // 创建详情记录
                $detailItems = [];

                if (!empty($invalidRecords)) {
                    foreach ($invalidRecords as $record) {
                        $signInfo = "";

                        if (isset($record['sign_status']) && $record['sign_status'] == '未签名') {
                            $signInfo = "未签名";
                        } else {
                            $signInfo = isset($record[$firstBlsyTimeField]) ? $record[$firstBlsyTimeField] . "（超时）" : "（超时）";
                        }

                        $detailItems[] = "【" . $record['BLMC'] . "】首次签名时间【" . $signInfo . "】";

                        // 只保存第一个病历号
                        if (empty($detailItems["BLBH"]) && isset($record['BLBH'])) {
                            $detailItems["BLBH"] = $record['BLBH'];
                        }
                    }
                } else {
                    // 没有任何输血病程记录
                    $detailItems[] = "输血病程【无】";
                }

                // 合并基础信息和详情
                $sxGroup = array_merge($sxBasis, $detailItems);

                // 将这条输血记录的质控结果添加到总结果中
                $allBasisGroups[] = $sxGroup;
            }
        }

        // 7. 将所有质控结果添加到insertData
        if (!empty($allBasisGroups)) {
            $this->insertData[] = [
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title'],
                'basis' => json_encode($allBasisGroups, JSON_UNESCAPED_UNICODE)
            ];
        }

        return true;
    }

    /**
     * 住院超30天患者未及时完成阶段小结
     * 住院时间（出院时间/当前时间 - 入院时间）≥30天，每30天一个周期
     * 不足30天不质控
     * @param $caseRule
     * @param $ruleId
     * @param $ZYH
     * @return bool
     * @author lzh
     */
    public function rule1013($caseRule, $ruleId, $ZYH)
    {

        // 1. 获取患者入院和出院信息
        $patientInfo = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->first();
        if (empty($patientInfo)) {
            return true; // 找不到患者信息，跳过质控
        }

        // 获取入院时间
        $ryTime = $patientInfo['AAB01'];
        if (empty($ryTime) || $ryTime == '1970-01-01 00:00:00' || $ryTime == '0000-00-00 00:00:00') {
            return true; // 没有有效的入院时间
        }

        // 获取出院时间
        $cyTime = $patientInfo['AAC01'];

        // 如果没有出院时间或出院时间无效，使用当前时间
        if (empty($cyTime) || $cyTime == '1970-01-01 00:00:00' || $cyTime == '0000-00-00 00:00:00') {
            $cyTime = date('Y-m-d H:i:s');
        }

        // 2. 计算住院天数
        $ryTimestamp = strtotime($ryTime);
        $cyTimestamp = strtotime($cyTime);
        $hospitalDays = floor(($cyTimestamp - $ryTimestamp) / (24 * 3600));

        // 住院天数不足30天，不进行质控
        if ($hospitalDays < 30) {
            return true;
        }



        // 3. 计算周期数量
        $cycleTotalCount = floor($hospitalDays / 30);


        // 4. 获取阶段小结和转科记录的配置
        // 阶段小结MBLB配置
        $ruleMap8032 = RuleWordMap::query()->where('id', '=', 8032)->value('keyword');
        $ruleMap8032 = !empty($ruleMap8032) ? $ruleMap8032 : '26'; // 阶段小结MBLB

        // 转科记录MBLB配置
        $ruleMap8033 = RuleWordMap::query()->where('id', '=', 8033)->value('keyword');
        $ruleMap8033 = !empty($ruleMap8033) ? $ruleMap8033 : '30'; // 转科记录MBLB

        // 转科医嘱配置
        $yzKeywords = RuleWordMap::getArrayById(8034);

        // 获取签名时间字段
        $firstBlsyTimeField = RuleWordMap::query()->where('id', '=', 8002)->value('keyword');
        $firstBlsyTimeField = !empty($firstBlsyTimeField) ? $firstBlsyTimeField : 'first_blsy_time';

        $ruleMap8011 = RuleWordMap::query()->where('id', '=', 8011)->value('keyword');
        $ruleMap8011 = !empty($ruleMap8011) ? $ruleMap8011 : 'ZXSJ';

        // 5. 创建存储质控结果的数组
        $allBasisGroups = [];

        // 6. 处理每个30天周期
        $daysPerCycle = 30; // 添加明确的周期天数常量
        for ($cycleIndex = 0; $cycleIndex < $cycleTotalCount; $cycleIndex++) {
            // 计算周期的开始和结束时间
            $cycleStartTime = date('Y-m-d H:i:s', strtotime("+" . ($cycleIndex * $daysPerCycle) . " days", $ryTimestamp));
            $cycleEndTime = date('Y-m-d H:i:s', strtotime("+" . (($cycleIndex + 1) * $daysPerCycle) . " days", $ryTimestamp));


            // 超过住院时间和周期不满30天不再检查
            if (strtotime($cycleEndTime) > $cyTimestamp) {
                continue;
            }
            //如果出院时间小于这个周期结束时间+48小时，不再检查
            if (strtotime($cyTime) < strtotime($cycleEndTime) + 48 * 3600) {
                continue;
            }

            // 创建当前周期的基础信息
            $cycleBasis = [];
            $cycleBasis[] = "住院周期【{$cycleStartTime} 至 {$cycleEndTime}】";

            //阶段结束前三天endtime-3天
            $cycleEndTime3DaysBefore = date('Y-m-d H:i:s', strtotime("-3 days", strtotime($cycleEndTime)));

            // 从MySQL查询转科医嘱
            $yzbQuery = Yzb::query()
                ->where('ZYH', $ZYH)
                ->where('KZSJ', '>=', $cycleEndTime3DaysBefore)
                ->where('KZSJ', '<=', $cycleEndTime)->get()->toArray();
            $yzbData = [];
            foreach ($yzbQuery as $record) {
                foreach ($yzKeywords as $keyword) {
                    if (strpos($record['YZMC'], $keyword) !== false) {
                        $yzbData[] = $record;
                        break;
                    }
                }
            }

            $hasValidTransferRecord = false;
            // 检查是否有转科医嘱
            // 在每个周期开始时初始化
            $allDetailItems = [];
            $hasAnyError = false;
            if (!empty($yzbData)) {
                // 有转科医嘱，检查是否有对应的转科记录

                $transferMedicalOrderErrors = [];

                foreach ($yzbData as $yz) {
                    if (empty($yz['YZMC']) || empty($yz['KZSJ'])) {
                        continue;
                    }

                    // 医嘱开始时间
                    $yzStartTime = $yz['KZSJ'];
                    // 医嘱开始时间前3天
                    $yzStartTime3DaysBefore = date('Y-m-d H:i:s', strtotime("-3 days", strtotime($yzStartTime)));

                    // 查询是否有转科记录
                    try {
                        $transferRecords = EMR_BL_BL01::query()
                            ->where('JZHM', $ZYH)
                            ->where('MBLB', $ruleMap8033)
                            ->where($ruleMap8011, '>=', $yzStartTime3DaysBefore)
                            ->where($ruleMap8011, '<=', $yzStartTime)
                            ->get()
                            ->toArray();
                    } catch (\Exception $e) {
                        Log::error("rule-$ruleId-$ZYH-error: " . $e->getMessage());
                        continue; // 查询出错时跳过当前医嘱
                    }

                    if (empty($transferRecords)) {
                        // 没有任何转科记录
                        $transferMedicalOrderErrors[] = [
                            'yzmc' => $yz['YZMC'],
                            'kzsj' => $yz['KZSJ'],
                            'status' => 'missing'
                        ];
                    } else {

                        $hasValidTransferRecord = true;
                    }
                }

                // 如果有有效的转科记录，则跳过此周期的质控
                if ($hasValidTransferRecord) {
                    continue;
                }

                // 无有效转科记录，添加质控结果

                if (!empty($transferMedicalOrderErrors)) {
                    foreach ($transferMedicalOrderErrors as $error) {
                        if ($error['status'] == 'missing') {
                            $allDetailItems[] = "医嘱名称【{$error['yzmc']}】";
                            $allDetailItems[] = "开嘱时间【{$error['kzsj']}】";
                            $allDetailItems[] = "阶段小结/转科记录【无】";
                        } else {
                            $allDetailItems[] = "医嘱名称【{$error['yzmc']}】";
                            $allDetailItems[] = "【{$error['blmc']}】【{$error['time']} （超时）】";
                        }
                    }
                    $hasAnyError = true;
                }
            }
            // 没有转科医嘱，检查是否有阶段小结
            // 计算第30天+72小时的期限
            $cycleEndTimestamp = strtotime($cycleEndTime);
            //第三十天前72小时
            $beforeCycleEndTime = date('Y-m-d H:i:s', $cycleEndTimestamp - 72 * 3600);
            $timeLimit = date('Y-m-d H:i:s', $cycleEndTimestamp + 72 * 3600);



            // 查询阶段小结
            try {
                $stageSummaryRecords = EMR_BL_BL01::query()
                    ->where('JZHM', $ZYH)
                    ->where('MBLB', $ruleMap8032)
                    ->where($ruleMap8011, '>=', $beforeCycleEndTime)
                    ->where($ruleMap8011, '<=', $timeLimit)
                    ->get()
                    ->toArray();
            } catch (\Exception $e) {
                Log::error("rule-$ruleId-$ZYH-error: " . $e->getMessage());
                continue; // 查询出错时跳过当前周期
            }


            // 检查是否有有效的阶段小结

            if (!empty($stageSummaryRecords)) {
                // 添加质控结果
                // 有阶段小结但签名时间不在有效范围内
                foreach ($stageSummaryRecords as $record) {
                    if (isset($record[$firstBlsyTimeField]) && !empty($record[$ruleMap8011])) {
                        // 跟阶段结束时间比较，签名是否超时
                        $firstBlsyTime = $record[$firstBlsyTimeField];
                        if (strtotime($firstBlsyTime) >= strtotime($beforeCycleEndTime) && strtotime($firstBlsyTime) <= strtotime($timeLimit)) {
                            $hasValidTransferRecord = true;
                            break;
                        } elseif (strtotime($firstBlsyTime) < strtotime($beforeCycleEndTime)) {
                            $allDetailItems[] = "【{$record['BLMC']}】首次签名时间【{$firstBlsyTime} （提前）】";
                            $hasAnyError = true;
                        } elseif (strtotime($firstBlsyTime) > strtotime($timeLimit)) {
                            $allDetailItems[] = "【{$record['BLMC']}】首次签名时间【{$firstBlsyTime} （超时）】";
                            $hasAnyError = true;
                        }
                    }
                }
            } else {
                // 添加质控结果
                $allDetailItems[] = "阶段小结【无】";
                // 没有阶段小结
                $hasAnyError = true;
                //$allBasisGroups[] = $cycleGroup;
            }

            // 统一合并（只执行一次）
            if ($hasAnyError && !$hasValidTransferRecord) {
                $cycleGroup = array_merge($cycleBasis, $allDetailItems);
                $allBasisGroups[] = $cycleGroup;
            }
        }

        // 7. 将所有质控结果添加到insertData
        if (!empty($allBasisGroups)) {
            $this->insertData[] = [
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title'],
                'basis' => json_encode($allBasisGroups, JSON_UNESCAPED_UNICODE)
            ];
        }

        return true;
    }

    /**
     * 患者转出前未完成转出记录
     * 检查规则：
     * 1. 医嘱名称含"转出"
     * 2. 如果24小时前医嘱名称含"抢救,病危,急会诊"不需要质控
     * 3. 转出记录MBLB=30且blmc包含"转出",首次签名时间在[转出的开嘱时间-24小时]至[转出的开嘱时间]之间
     * @param $caseRule
     * @param $ruleId
     * @param $ZYH
     * @return bool
     * @author lzh
     */
    public function rule1014($caseRule, $ruleId, $ZYH)
    {

        // 1. 获取配置字段映射
        // 获取转出医嘱关键词
        $zcKeywords = RuleWordMap::getArrayById(8035);

        // 获取抢救/病危/急会诊关键词
        $ruleMap8037 = RuleWordMap::query()->where('id', '=', 8037)->value('keyword');
        $ruleMap8037 = !empty($ruleMap8037) ? $ruleMap8037 : '抢救,病危,急会诊';

        // 获取转出记录MBLB值
        $ruleMap8033 = RuleWordMap::query()->where('id', '=', 8033)->value('keyword');
        $ruleMap8033 = !empty($ruleMap8033) ? $ruleMap8033 : '30'; // 转出记录MBLB值

        // 获取转出记录名称关键词
        $ruleMap8036 = RuleWordMap::query()->where('id', '=', 8036)->value('keyword');
        $ruleMap8036 = !empty($ruleMap8036) ? $ruleMap8036 : '转出';

        // 获取首次签名时间字段
        $firstBlsyTimeField = RuleWordMap::query()->where('id', '=', 8002)->value('keyword');
        $firstBlsyTimeField = !empty($firstBlsyTimeField) ? $firstBlsyTimeField : 'first_blsy_time';

        // 获取执行时间字段
        $ruleMap8011 = RuleWordMap::query()->where('id', '=', 8011)->value('keyword');
        $ruleMap8011 = !empty($ruleMap8011) ? $ruleMap8011 : 'ZXSJ'; // 执行时间字段


        // 2. 从MySQL查询医嘱名称含"转出"的医嘱
        // 准备转出医嘱查询条件
        $yzbQuery = Yzb::query()->where('ZYH', $ZYH)->get()->toArray();
        $yzbData = [];
        foreach ($yzbQuery as $record) {
            if (strpos($record['YZMC'], '好转') !== false) {
                continue;
            }
            foreach ($zcKeywords as $keyword) {
                if (strpos($record['YZMC'], $keyword) !== false) {
                    $yzbData[] = $record;
                    break;
                }
            }
        }

        if (empty($yzbData)) {
            return true; // 没有符合条件的转出医嘱，不进行质控
        }

        // 3. 收集所有质控结果，按医嘱分组
        $allBasisGroups = [];

        // 4. 处理每条转出医嘱
        foreach ($yzbData as $order) {
            // 获取开嘱时间，添加字段存在检查
            $startTime = isset($order['KZSJ']) ? $order['KZSJ'] : '';
            $yzmc = isset($order['YZMC']) ? $order['YZMC'] : '未知医嘱';
            //开嘱时间
            $kzsj = isset($order['KZSJ']) ? $order['KZSJ'] : '';

            // 检查开嘱时间是否有效
            if (empty($startTime) || $startTime == '1970-01-01 00:00:00' || $startTime == '0000-00-00 00:00:00') {
                continue; // 跳过无效的开嘱时间
            }

            // 计算开嘱时间24小时前的时间点，用于检查是否存在抢救/病危/急会诊医嘱
            $beforeStartTime = date('Y-m-d H:i:s', strtotime($startTime) - 24 * 3600);

            // 5. 检查24小时前是否存在抢救/病危/急会诊医嘱，如果存在则跳过质控
            // 处理可能有多个关键词的情况
            if (strpos($ruleMap8037, ',') !== false) {
                $excludeKeywords = explode(',', $ruleMap8037);
            } else {
                $excludeKeywords = [$ruleMap8037];
            }

            // 从MySQL查询是否存在抢救/病危/急会诊医嘱
            $yzbQuery = Yzb::query()
                ->where('ZYH', $ZYH)
                ->where('KZSJ', '>=', $beforeStartTime)
                ->where('KZSJ', '<=', $startTime)->get()->toArray();
            $excludeData = [];
            foreach ($yzbQuery as $record) {
                foreach ($excludeKeywords as $keyword) {
                    if (strpos($record['YZMC'], $keyword) !== false) {
                        $excludeData[] = $record;
                        break;
                    }
                }
            }

            if (!empty($excludeData)) {
                // 存在抢救/病危/急会诊医嘱，跳过此医嘱的质控
                continue;
            }

            // 6. 计算开嘱前24小时的截止时间
            $endCheckTime = date('Y-m-d H:i:s', strtotime($startTime) - 24 * 3600);

            //startTime+8小时
            $startTimePlus8Hours = date('Y-m-d H:i:s', strtotime($startTime) + 8 * 3600);
            // 7. 查询是否存在满足条件的转出记录
            try {
                // 查询MBLB=30且blmc包含"转出"的记录
                $bl01Data = EMR_BL_BL01::query()
                    ->where('JZHM', $ZYH)
                    ->where('MBLB', $ruleMap8033)
                    ->where('BLMC', 'like', '%' . $ruleMap8036 . '%')
                    ->where($ruleMap8011, '<=', $startTimePlus8Hours) // 执行时间小于等于开嘱时间
                    ->where($ruleMap8011, '>=', $endCheckTime) // 执行时间大于等于开嘱前24小时
                    ->get()
                    ->toArray();

                // 8. 如果没有找到符合条件的转出记录，记录质控结果
                if (empty($bl01Data)) {
                    // 医嘱信息
                    $orderBasis = [];
                    $orderBasis[] = "医嘱名称【" . $yzmc . "】";
                    $orderBasis[] = "开嘱时间【" . $kzsj . "】";

                    // 添加无转出记录信息
                    $orderBasis[] = "转出记录【无】";
                    //$orderBasis[] = "首次签名时间【无】";

                    // 将这个医嘱的质控结果添加到总结果中
                    $allBasisGroups[] = $orderBasis;
                } else {
                    // 检查是否有在时间窗口内完成签名的记录
                    $validRecord = false;
                    $invalidRecords = [];

                    foreach ($bl01Data as $record) {
                        $firstBlsyTime = isset($record[$firstBlsyTimeField]) ? $record[$firstBlsyTimeField] : '';
                        $blmc = isset($record['BLMC']) ? $record['BLMC'] : '未知记录';

                        if (
                            !empty($firstBlsyTime) &&
                            strtotime($firstBlsyTime) <= strtotime($startTimePlus8Hours) &&
                            strtotime($firstBlsyTime) >= strtotime($endCheckTime)
                        ) {
                            $validRecord = true; // 找到有效记录，不需要质控
                            break;
                        } else {
                            // 收集无效记录信息
                            $invalidRecords[] = [
                                'blmc' => $blmc,
                                'time' => $firstBlsyTime
                            ];
                        }
                    }

                    // 如果没有有效记录，添加质控结果
                    if (!$validRecord) {
                        // 医嘱信息
                        $orderBasis = [];
                        $orderBasis[] = "医嘱名称【" . $yzmc . "】";
                        $orderBasis[] = "开嘱时间【" . $kzsj . "】";

                        // 添加无效记录信息
                        if (!empty($invalidRecords)) {
                            foreach ($invalidRecords as $invalid) {
                                $signTimeInfo = empty($invalid['time']) ? "未签名" : $invalid['time'] . "（超时）";
                                $orderBasis[] = "转出记录【" . $invalid['blmc'] . "】";
                                $orderBasis[] = "首次签名时间【" . $signTimeInfo . "】";
                            }
                        } else {
                            $orderBasis[] = "转出记录【无】";
                            //$orderBasis[] = "首次签名时间【无】";
                        }

                        // 将这个医嘱的质控结果添加到总结果中
                        $allBasisGroups[] = $orderBasis;
                    }
                }
            } catch (\Exception $e) {
                Log::error("rule-$ruleId-$ZYH-record-check-error: " . $e->getMessage());
                continue; // 查询出错时跳过当前医嘱
            }
        }

        // 9. 将所有质控结果一次性插入到insertData
        if (!empty($allBasisGroups)) {
            $this->insertData[] = [
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title'],
                'basis' => json_encode($allBasisGroups, JSON_UNESCAPED_UNICODE)
            ];
        }

        return true;
    }

    /**
     * 患者转入后未完成转出记录
     * 检查规则：
     * 1. 医嘱名称含"转入"
     * 2. 转出记录MBLB=30且blmc包含"转出",首次签名时间在【转入的开嘱时间】至【转入的开嘱时间】+24小时之间
     * @param $caseRule
     * @param $ruleId
     * @param $ZYH
     * @return bool
     * @author lzh
     */
    public function rule1015($caseRule, $ruleId, $ZYH)
    {

        // 1. 获取配置字段映射
        // 获取转出医嘱关键词
        $ruleMap8038 = RuleWordMap::query()->where('id', '=', 8038)->value('keyword');
        $ruleMap8038 = !empty($ruleMap8038) ? $ruleMap8038 : '转入';


        // 获取转出记录MBLB值
        $ruleMap8033 = RuleWordMap::query()->where('id', '=', 8033)->value('keyword');
        $ruleMap8033 = !empty($ruleMap8033) ? $ruleMap8033 : '30'; // 转出记录MBLB值

        // 获取转出记录名称关键词
        $ruleMap8039 = RuleWordMap::query()->where('id', '=', 8039)->value('keyword');
        $ruleMap8039 = !empty($ruleMap8039) ? $ruleMap8039 : '转入';

        // 获取首次签名时间字段
        $firstBlsyTimeField = RuleWordMap::query()->where('id', '=', 8002)->value('keyword');
        $firstBlsyTimeField = !empty($firstBlsyTimeField) ? $firstBlsyTimeField : 'first_blsy_time';

        // 获取执行时间字段
        $ruleMap8011 = RuleWordMap::query()->where('id', '=', 8011)->value('keyword');
        $ruleMap8011 = !empty($ruleMap8011) ? $ruleMap8011 : 'ZXSJ'; // 执行时间字段

        //8052
        $ruleMap8052 = RuleWordMap::query()->where('id', '=', 8052)->value('keyword');
        $ruleMap8052 = !empty($ruleMap8052) ? $ruleMap8052 : 'EICU过渡病区';
        //是否包含逗号
        $ruleMap8052 = strpos($ruleMap8052, ',') !== false ? explode(',', $ruleMap8052) : [$ruleMap8052];

        /* // 2. 从ES查询医嘱名称含"转出"的医嘱

        // 准备转出医嘱查询条件
        $must = [
            ['match_phrase' => ['ZYH' => $ZYH]]
        ];

        // 处理可能有多个关键词的情况
        if (strpos($ruleMap8038, ',') !== false) {
            $zcKeywords = explode(',', $ruleMap8038);
            $should = [];
            foreach ($zcKeywords as $keyword) {
                if (!empty(trim($keyword))) {
                    $yzbQuery->orWhere('YZMC', 'like', '%' . trim($keyword) . '%');
                }
            }

        } else {
            $must[] = ['match_phrase' => ['YZMC' => $ruleMap8038]];
        }

        // 执行查询获取转入医嘱
        try {
            $params = $esService->clearMust()
                ->queryByMustBatch($must)
                ->getParams();
            $res = app('es')->search($params);
            $yzbData = $esService->getDataByEs($res);

            if (empty($yzbData[0])) {
                return true; // 没有符合条件的转入医嘱，不进行质控
            }
        } catch (\Exception $e) {
            Log::error("rule-$ruleId-$ZYH-error: " . $e->getMessage());
            return true; // 查询出错，跳过质控
        }
 */
        try {
            $hcmx = ZY_HCMX::query()
                ->where('ZYH', $ZYH)
                ->where('HCLX', '=', 2)
                ->get()
                ->toArray();

            if (empty($hcmx)) {
                return true; // 没有符合条件的换床记录，不进行质控
            }
        } catch (\Exception $e) {
            Log::error("rule-$ruleId-$ZYH-error: " . $e->getMessage());
        }

        // 3. 收集所有质控结果，按医嘱分组
        $allBasisGroups = [];

        // 4. 处理每条转入医嘱
        //foreach ($yzbData as $order) {
        foreach ($hcmx as $hcmxitem) {
            // 获取开嘱时间，添加字段存在检查
            //$startTime = isset($order['KZSJ']) ? $order['KZSJ'] : '';
            //$yzmc = isset($order['YZMC']) ? $order['YZMC'] : '未知医嘱';
            //开嘱时间
            //$kzsj = isset($order['KZSJ']) ? $order['KZSJ'] : '';
            $startTime = isset($hcmxitem['HCRQ']) ? $hcmxitem['HCRQ'] : '';
            //获取当前时间
            $currentDate = Carbon::now()->toDateTimeString();
            $oneDayAfterSurgery = date('Y-m-d H:i:s', strtotime($startTime) + 24 * 3600);
            //如果当前时间小于换床时间+24小时就跳过
            if (strtotime($currentDate) < strtotime($oneDayAfterSurgery)) {
                continue;
            }
            $hhbqid = isset($hcmxitem['HHBQ']) ? $hcmxitem['HHBQ'] : '';
            //根据科室表转换
            $hhbqname = Department::query()->where('dep_id', $hhbqid)->value('dep_name');
            $hhbq = $hhbqname . ' ' . $hhbqid;
            //$hasYzb = false;
            /* foreach ($ruleMap8052 as $keyword) {
                if (strpos($yzmc, $keyword) !== false) {
                    $hasYzb = true;
                    break;
                }
            } */
            /* if ($hasYzb) {
                continue; //跳过EICU过渡病区的医嘱
            } */

            // 检查开嘱时间是否有效
            if (empty($startTime) || $startTime == '1970-01-01 00:00:00' || $startTime == '0000-00-00 00:00:00') {
                continue; // 跳过无效的开嘱时间
            }

            // 6. 计算开嘱后24小时的截止时间
            $endCheckTime = date('Y-m-d H:i:s', strtotime($startTime) + 24 * 3600);

            // 7. 查询是否存在满足条件的转出记录
            try {
                // 查询MBLB=30且blmc包含"转入"的记录
                $bl01Data = EMR_BL_BL01::query()
                    ->where('JZHM', $ZYH)
                    ->where('MBLB', $ruleMap8033)
                    ->where('BLMC', 'like', '%' . $ruleMap8039 . '%')
                    ->where($ruleMap8011, '>=', $startTime) // 执行时间大于等于开嘱时间
                    ->where($ruleMap8011, '<=', $endCheckTime) // 执行时间小于等于开嘱后24小时
                    ->get()
                    ->toArray();

                // 8. 如果没有找到符合条件的转入记录，记录质控结果
                if (empty($bl01Data)) {
                    // 医嘱信息
                    $orderBasis = [];
                    //$orderBasis[] = "医嘱名称【" . $yzmc . "】";
                    //$orderBasis[] = "开嘱时间【" . $kzsj . "】";
                    $orderBasis[] = "换床病区【" . $hhbq . "】";
                    $orderBasis[] = "换床时间【" . $startTime . "】";

                    // 添加无转入记录信息
                    $orderBasis[] = "转入记录【无】";
                    //$orderBasis[] = "首次签名时间【无】";

                    // 将这个医嘱的质控结果添加到总结果中
                    $allBasisGroups[] = $orderBasis;
                } else {
                    // 检查是否有在时间窗口内完成签名的记录
                    $validRecord = false;
                    $invalidRecords = [];

                    foreach ($bl01Data as $record) {
                        $firstBlsyTime = isset($record[$firstBlsyTimeField]) ? $record[$firstBlsyTimeField] : '';
                        $blmc = isset($record['BLMC']) ? $record['BLMC'] : '未知记录';

                        if (
                            !empty($firstBlsyTime) &&
                            strtotime($firstBlsyTime) >= strtotime($startTime) &&
                            strtotime($firstBlsyTime) <= strtotime($endCheckTime)
                        ) {
                            $validRecord = true; // 找到有效记录，不需要质控
                            break;
                        } else {
                            // 收集无效记录信息
                            $invalidRecords[] = [
                                'blmc' => $blmc,
                                'time' => $firstBlsyTime
                            ];
                        }
                    }

                    // 如果没有有效记录，添加质控结果
                    if (!$validRecord) {
                        // 医嘱信息
                        $orderBasis = [];
                        //$orderBasis[] = "医嘱名称【" . $yzmc . "】";
                        $orderBasis[] = "换床病区【" . $hhbq . "】";
                        $orderBasis[] = "换床时间【" . $startTime . "】";

                        // 添加无效记录信息
                        if (!empty($invalidRecords)) {
                            foreach ($invalidRecords as $invalid) {
                                $signTimeInfo = empty($invalid['time']) ? "未签名" : $invalid['time'] . "（超时）";
                                $orderBasis[] = "转入记录【" . $invalid['blmc'] . "】";
                                $orderBasis[] = "首次签名时间【" . $signTimeInfo . "】";
                            }
                        } else {
                            $orderBasis[] = "转入记录【无】";
                            //$orderBasis[] = "首次签名时间【无】";
                        }

                        // 将这个医嘱的质控结果添加到总结果中
                        $allBasisGroups[] = $orderBasis;
                    }
                }
            } catch (\Exception $e) {
                Log::error("rule-$ruleId-$ZYH-record-check-error: " . $e->getMessage());
                continue; // 查询出错时跳过当前医嘱
            }
        }

        // 9. 将所有质控结果一次性插入到insertData
        if (!empty($allBasisGroups)) {
            $this->insertData[] = [
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title'],
                'basis' => json_encode($allBasisGroups, JSON_UNESCAPED_UNICODE)
            ];
        }

        return true;
    }

    /**
     * 检查普通会诊是否在发出后24小时内完成
     * @param array $caseRule 规则配置
     * @param int $ruleId 规则ID
     * @param string $ZYH 住院号
     * @return bool
     * @author lzh
     */
    public function rule1016($caseRule, $ruleId, $ZYH)
    {

        try {
            // 紧急会诊标志值配置
            $ruleMap8040 = RuleWordMap::query()->where('id', '=', 8040)->value('keyword');
            $ruleMap8040 = !empty($ruleMap8040) ? $ruleMap8040 : '2';

            // 获取字段配置
            $hzsjField = RuleWordMap::query()->where('id', '=', 8041)->value('keyword') ?? 'HZSJ';  // 申请会诊时间
            $sxsjField = RuleWordMap::query()->where('id', '=', 8042)->value('keyword') ?? 'SXSJ';  // 会诊意见书写时间
            //会诊申请时间
            $sqsjField = RuleWordMap::query()->where('id', '=', 8077)->value('keyword') ?? 'SQSJ';  // 申请会诊时间
            //签名时间
            $qmsjField = RuleWordMap::query()->where('id', '=', 8076)->value('keyword') ?? 'QMSJ';  // 签名时间

            // 1. 查询会诊申请表中的普通会诊申请（非紧急且未作废）
            $consultRequests = YS_ZY_HZSQ::query()
                ->where('JZHM', $ZYH)
                ->where('JJBZ', '!=', $ruleMap8040) // 非紧急会诊
                ->where('ZFBZ', 0)                  // 未作废
                ->get()->toArray();


            if (empty($consultRequests)) {
                return true;
            }


            // 收集所有质控结果
            $allBasisGroups = [];

            // 2. 循环处理每条会诊申请
            foreach ($consultRequests as $request) {
                $sqxh = $request['SQXH'];
                $requestTime = $request[$sqsjField] ?? ''; // 申请会诊时间
                $yqdx = $request['YQDX'] ?? '无';      // 邀请科室


                // 跳过无会诊时间的记录
                if (empty($requestTime) || $requestTime == '1970-01-01 00:00:00' || $requestTime == '0000-00-00 00:00:00') {
                    continue;
                }


                // 计算24小时期限
                $deadline = date('Y-m-d H:i:s', strtotime($requestTime) + 24 * 3600);

                //获取当前时间
                $currentDate = Carbon::now()->toDateTimeString();
                //如果当前时间小于会诊申请时间+24小时就跳过
                if (strtotime($currentDate) < strtotime($deadline)) {
                    continue;
                }

                // 3. 查询该会诊申请对应的会诊意见
                $opinions = YS_ZY_HZYJ::query()
                    ->where('SQXH', $sqxh)
                    ->get()->first();


                // 当前会诊申请的质控结果
                $basisForOrder = [];
                $hasViolation = false;

                // 添加基础信息
                $basisForOrder[] = "申请会诊时间【{$requestTime}】";
                $basisForOrder[] = "邀请科室【{$yqdx}】";

                if (empty($opinions)) {
                    // 没有会诊意见
                    //跟当前时间对比，如果不超过24小时就跳过
                    continue;
                } else {
                    // 检查意见书写时间是否在24小时内
                    $hasInTimeOpinion = false;
                    $latestOpinionTime = null;


                    if (empty($opinions[$qmsjField]) && empty($opinions[$sxsjField])) {
                        continue;
                    }
                    $opinionTime = null;
                    if (!empty($opinions[$qmsjField])) {
                        $opinionTime = $opinions[$qmsjField];
                    } else {
                        $opinionTime = $opinions[$sxsjField];
                    }

                    if (empty($opinionTime) || $opinionTime == '1970-01-01 00:00:00' || $opinionTime == '0000-00-00 00:00:00') {
                        continue;
                    }

                    if (!empty($opinionTime)) {
                        if (strtotime($opinionTime) > strtotime($deadline)) {
                            $latestOpinionTime = $opinionTime;
                        }

                        if (strtotime($opinionTime) <= strtotime($deadline)) {
                            $hasInTimeOpinion = true;
                            break;
                        }
                    }


                    if (!$hasInTimeOpinion) {
                        // 超时完成会诊或未完成
                        $timeInfo = !empty($latestOpinionTime) ? "{$latestOpinionTime}（超24小时）" : "无";
                        $basisForOrder[] = "会诊意见书写时间【{$timeInfo}】";
                        $hasViolation = true;
                    }
                }

                // 如果有违规情况，添加到结果集
                if ($hasViolation) {
                    $allBasisGroups[] = $basisForOrder;
                }
            }

            // 4. 将所有质控结果一次性插入到insertData
            if (!empty($allBasisGroups)) {
                $this->insertData[] = [
                    'JZHM' => $ZYH,
                    'rule_id' => $ruleId,
                    'code' => 'rule_' . $ruleId,
                    'error_field' => $caseRule[$ruleId]['title'],
                    'basis' => json_encode($allBasisGroups, JSON_UNESCAPED_UNICODE)
                ];
            }

            return true;
        } catch (\Exception $e) {
            Log::error("rule-$ruleId-$ZYH-error: " . $e->getMessage());
            return true; // 发生错误时也返回true，避免中断质控流程
        }
    }

    /**
     * 检查紧急会诊是否在发出后10分钟内到达
     * @param array $caseRule 规则配置
     * @param int $ruleId 规则ID
     * @param string $ZYH 住院号
     * @return bool
     * @author lzh
     */
    public function rule1017($caseRule, $ruleId, $ZYH)
    {

        try {
            // 紧急会诊标志值配置
            $ruleMap8040 = RuleWordMap::query()->where('id', '=', 8040)->value('keyword');
            $ruleMap8040 = !empty($ruleMap8040) ? $ruleMap8040 : '2';

            // 获取字段配置
            $hzsjField = RuleWordMap::query()->where('id', '=', 8041)->value('keyword') ?? 'HZSJ';  // 申请会诊时间
            $sxsjField = RuleWordMap::query()->where('id', '=', 8043)->value('keyword') ?? 'JHZDDSJ';  // 紧急会诊到达时间
            $sqsjField = RuleWordMap::query()->where('id', '=', 8077)->value('keyword') ?? 'SQSJ';  // 申请会诊时间

            // 1. 查询会诊申请表中的普通会诊申请（非紧急且未作废）
            $consultRequests = YS_ZY_HZSQ::query()
                ->where('JZHM', $ZYH)
                ->where('JJBZ', $ruleMap8040) // 紧急会诊
                ->where('ZFBZ', 0)                  // 未作废
                ->get()->toArray();

            if (empty($consultRequests)) {
                return true;
            }

            // 收集所有质控结果
            $allBasisGroups = [];

            // 2. 循环处理每条会诊申请
            foreach ($consultRequests as $request) {
                $sqxh = $request['SQXH'];
                $requestTime = $request[$sqsjField] ?? ''; // 申请会诊时间
                $yqdx = $request['YQDX'] ?? '无';      // 邀请科室

                // 跳过无会诊时间的记录
                if (empty($requestTime) || $requestTime == '1970-01-01 00:00:00' || $requestTime == '0000-00-00 00:00:00') {
                    continue;
                }

                // 计算十分钟期限
                $deadline = date('Y-m-d H:i:s', strtotime($requestTime) + 10 * 60);

                //获取当前时间
                $currentDate = Carbon::now()->toDateTimeString();
                //如果当前时间小于会诊申请时间+10分钟就跳过
                if (strtotime($currentDate) < strtotime($deadline)) {
                    continue;
                }

                // 3. 查询该会诊申请对应的会诊意见
                $opinions = YS_ZY_HZYJ::query()
                    ->where('SQXH', $sqxh)
                    ->get()->first();

                // 当前会诊申请的质控结果
                $basisForOrder = [];
                $hasViolation = false;

                // 添加基础信息
                $basisForOrder[] = "申请会诊时间【{$requestTime}】";
                $basisForOrder[] = "邀请科室【{$yqdx}】";
                $latestOpinionTime = null;
                $hasInTimeOpinion = false;
                $hasViolation = false;

                if (empty($opinions)) {
                    // 没有会诊意见
                    //跟当前时间对比，如果不超过10分钟就跳过
                    continue;
                } else {
                    // 检查意见书写时间是否在24小时内
                    $opinionTime = $opinions[$sxsjField] ?? '';
                    if (empty($opinionTime) || $opinionTime == '1970-01-01 00:00:00' || $opinionTime == '0000-00-00 00:00:00') {
                        continue;
                    }

                    if (!empty($opinionTime)) {
                        if (strtotime($opinionTime) > strtotime($deadline)) {
                            $latestOpinionTime = $opinionTime;
                        }

                        if (strtotime($opinionTime) <= strtotime($deadline)) {
                            $hasInTimeOpinion = true;
                            break;
                        }
                    }


                    if (!$hasInTimeOpinion) {
                        // 超时完成会诊或未完成
                        $timeInfo = !empty($latestOpinionTime) ? "{$latestOpinionTime}（超10分钟）" : "无";
                        $basisForOrder[] = "会诊到达时间【{$timeInfo}】";
                        $hasViolation = true;
                    }
                }

                // 如果有违规情况，添加到结果集
                if ($hasViolation) {
                    $allBasisGroups[] = $basisForOrder;
                }
            }

            // 4. 将所有质控结果一次性插入到insertData
            if (!empty($allBasisGroups)) {
                $this->insertData[] = [
                    'JZHM' => $ZYH,
                    'rule_id' => $ruleId,
                    'code' => 'rule_' . $ruleId,
                    'error_field' => $caseRule[$ruleId]['title'],
                    'basis' => json_encode($allBasisGroups, JSON_UNESCAPED_UNICODE)
                ];
            }

            return true;
        } catch (\Exception $e) {
            Log::error("rule-$ruleId-$ZYH-error: " . $e->getMessage());
            return true; // 发生错误时也返回true，避免中断质控流程
        }
    }

    /**
     * 检查普通/急会诊结束后24小时内未完成会诊记录
     * @param array $caseRule 规则配置
     * @param int $ruleId 规则ID
     * @param string $ZYH 住院号
     * @return bool
     * @author lzh
     */
    public function rule1018($caseRule, $ruleId, $ZYH)
    {

        try {
            // 1. 获取配置项
            // 会诊结束时间字段
            $jssjField = RuleWordMap::query()->where('id', '=', 8044)->value('keyword') ?? 'JSSJ';

            // 会诊记录mblb值
            $hzjlMblb = RuleWordMap::query()->where('id', '=', 8045)->value('keyword') ?? '32';
            if (strpos($hzjlMblb, ',') !== false) {
                $mblbTypes = explode(',', $hzjlMblb);
            } else {
                $mblbTypes = [$hzjlMblb];
            }

            $rule8010 = RuleWordMap::query()->where('id', '=', 8010)->value('keyword') ?? 'ZXSJ';
            if (strpos($rule8010, ',') !== false) {
                $rule8010Types = explode(',', $rule8010);
            } else {
                $rule8010Types = [$rule8010];
            }

            // 执行时间字段
            $zxsjField = RuleWordMap::query()->where('id', '=', 8011)->value('keyword') ?? 'ZXSJ';

            // 首次签名时间字段
            $firstBlsyTimeField = RuleWordMap::query()->where('id', '=', 8002)->value('keyword') ?? 'first_blsy_time';

            //签名时间
            $qmsjField = RuleWordMap::query()->where('id', '=', 8076)->value('keyword') ?? 'QMSJ';
            //书写时间
            $sxsjField = RuleWordMap::query()->where('id', '=', 8042)->value('keyword') ?? 'SXSJ';

            // 2. 查询所有未作废的会诊申请
            $consultRequests = YS_ZY_HZSQ::query()
                ->where('JZHM', $ZYH)
                ->where('ZFBZ', 0) // 未作废
                ->get()
                ->toArray();

            if (empty($consultRequests)) {
                return true;
            }


            // 收集所有质控结果
            $allBasisGroups = [];

            // 3. 处理每条会诊申请
            foreach ($consultRequests as $request) {
                $sqxh = $request['SQXH'];
                $endTime = '';
                //查询会诊意见
                $opinions = YS_ZY_HZYJ::query()
                    ->where('SQXH', $sqxh)
                    ->get()
                    ->first();
                if (empty($opinion)) {
                    continue;
                } else {
                    $opinionTime = null;
                    if (empty($opinions[$qmsjField]) && empty($opinions[$sxsjField])) {
                        continue;
                    }
                    if (!empty($opinions[$qmsjField])) {
                        $opinionTime = $opinions[$qmsjField];
                    } else {
                        $opinionTime = $opinions[$sxsjField];
                    }
                    if (empty($opinionTime) || $opinionTime == '1970-01-01 00:00:00' || $opinionTime == '0000-00-00 00:00:00') {
                        continue;
                    } else {
                        $endTime = $opinionTime;
                    }
                }

                if (empty($endTime)) {
                    continue;
                }
                //$endTime = $request[$jssjField] ?? '';
                $yqdx = $request['YQDX'] ?? '未知科室';

                // 检查会诊结束时间是否有效
                if (empty($endTime) || $endTime == '1970-01-01 00:00:00' || $endTime == '0000-00-00 00:00:00') {
                    continue; // 跳过无效的会诊结束时间
                }

                // 计算截止时间（会诊结束后24小时）
                $deadline = date('Y-m-d H:i:s', strtotime($endTime) + 24 * 3600);

                //获取当前时间
                $currentDate = Carbon::now()->toDateTimeString();
                //如果当前时间小于会诊结束时间+24小时就跳过
                if (strtotime($currentDate) < strtotime($deadline)) {
                    continue;
                }

                // 4. 查询会诊记录
                $records = EMR_BL_BL01::query()
                    ->where('JZHM', $ZYH)
                    ->whereIn('MBLB', $mblbTypes)
                    ->where($zxsjField, '>=', $endTime)
                    ->where($zxsjField, '<=', $deadline)
                    ->get()
                    ->toArray();

                // 当前会诊申请的质控记录
                $basisForOrder = [];
                $hasViolation = false;

                // 基础信息
                $basisForOrder[] = "邀请科室【{$yqdx}】";
                $basisForOrder[] = "会诊签名时间【{$endTime}】";

                // 5. 检查是否有及时签名的会诊记录
                $hasValidRecord = false;
                $invalidRecords = [];
                if (empty($records)) {
                    // 没有会诊记录查bllb =8010，blmc包含会诊
                    $records = EMR_BL_BL01::query()
                        ->where('JZHM', $ZYH)
                        ->whereIn('BLLB', $rule8010Types)
                        ->where('BLMC', 'like', '%会诊%')
                        ->where($zxsjField, '>=', $endTime)
                        ->where($zxsjField, '<=', $deadline)
                        ->get()
                        ->toArray();
                }
                if (empty($records)) {
                    //跟当前时间对比，如果不超过24小时就跳过
                    $nowTime = date('Y-m-d H:i:s');
                    if (strtotime($nowTime) < strtotime($deadline)) {
                        continue;
                    }
                    $basisForOrder[] = "会诊记录【无】";
                    $hasViolation = true;
                } else {
                    foreach ($records as $record) {
                        $blmc = $record['BLMC'] ?? '未知记录';
                        $firstBlsyTime = $record[$firstBlsyTimeField] ?? '';

                        if (!empty($firstBlsyTime)) {
                            if (strtotime($firstBlsyTime) <= strtotime($deadline)) {
                                // 有及时签名的记录
                                $hasValidRecord = true;
                                break;
                            } else {
                                // 签名超时
                                $invalidRecords[] = [
                                    'blmc' => $blmc,
                                    'time' => $firstBlsyTime,
                                    'status' => 'late'
                                ];
                            }
                        } else {
                            // 未签名
                            $invalidRecords[] = [
                                'blmc' => $blmc,
                                'time' => '',
                                'status' => 'unsigned'
                            ];
                        }
                    }

                    // 如果没有及时签名的记录
                    if (!$hasValidRecord) {
                        $hasViolation = true;
                        foreach ($invalidRecords as $record) {
                            if ($record['status'] == 'unsigned') {
                                $basisForOrder[] = "会诊记录【{$record['blmc']}】";
                                $basisForOrder[] = "首次签名时间【未签名】";
                            } else {
                                $basisForOrder[] = "会诊记录【{$record['blmc']}】";
                                $basisForOrder[] = "首次签名时间【{$record['time']}（超24小时）】";
                            }
                        }
                    }
                }

                // 如果有违规情况，添加到结果集
                if ($hasViolation) {
                    $allBasisGroups[] = $basisForOrder;
                }
            }

            // 6. 将所有质控结果一次性插入到insertData
            if (!empty($allBasisGroups)) {
                $this->insertData[] = [
                    'JZHM' => $ZYH,
                    'rule_id' => $ruleId,
                    'code' => 'rule_' . $ruleId,
                    'error_field' => $caseRule[$ruleId]['title'],
                    'basis' => json_encode($allBasisGroups, JSON_UNESCAPED_UNICODE)
                ];
            }

            return true;
        } catch (\Exception $e) {
            Log::error("rule-$ruleId-$ZYH-error: " . $e->getMessage());
            return true; // 发生错误时也返回true，避免中断质控流程
        }
    }

    /**
     * 抢救记录新规则
     * @param array $caseRule 规则配置
     * @param int $ruleId 规则ID
     * @param string $ZYH 住院号
     * @return bool
     * @author lzh
     */
    public function rule1021($caseRule, $ruleId, $ZYH)
    {
        $allBasisGroups = [];

        try {
            // 1. 配置检索
            $mblbRescueRecordRaw = RuleWordMap::query()->where('id', '=', 8027)->value('keyword');
            $mblbRescueRecordConfig = !empty($mblbRescueRecordRaw) ? $mblbRescueRecordRaw : '27'; // Default MBLB for rescue records
            //是否是逗号分割
            if (strpos($mblbRescueRecordConfig, ',') !== false) {
                $mblbRescueRecordTypes = explode(',', $mblbRescueRecordConfig);
            } else {
                $mblbRescueRecordTypes = [$mblbRescueRecordConfig];
            }

            $zxsjField = RuleWordMap::query()->where('id', '=', 8011)->value('keyword');
            $zxsjField = !empty($zxsjField) ? $zxsjField : 'ZXSJ'; // Default ZXSJ field

            $signatureTimeField = RuleWordMap::query()->where('id', '=', 8002)->value('keyword');
            $signatureTimeField = !empty($signatureTimeField) ? $signatureTimeField : 'first_blsy_time'; // Default signature time field

            // 2. 查询EMR_BL_BL01抢救记录
            $bl01Records = EMR_BL_BL01::query()
                ->where('JZHM', $ZYH)
                ->whereIn('MBLB', $mblbRescueRecordTypes)
                ->get()
                ->toArray();

            if (empty($bl01Records)) {
                return true;
            }

            foreach ($bl01Records as $bl01Record) {
                $recordBasis = [];
                $blmc = $bl01Record['BLMC'] ?? '未知病历';
                $blbh = $bl01Record['BLBH'] ?? null;
                $zxsj_datetime_str = $bl01Record[$zxsjField] ?? null;
                $signature_datetime_str = $bl01Record[$signatureTimeField] ?? null;

                // 添加抢救记录信息
                $recordBasis[] = "抢救记录【{$blmc}】";

                // 解析抢救结束时间
                $parsedRescueEndDateTimeStr = "";
                $parsedRescueEndObj = null;

                if ($blbh && $zxsj_datetime_str) {
                    try {
                        $hjnr = EMR_BL_BLXG::query()->where('BLBH', $blbh)->value('HJNR');
                        $match_datetime_str = '';
                        if ($hjnr) {
                            // 统一匹配 YYYY-MM-DD HH:MM 格式，保留多种关键词
                            $keywords = [
                                '抢救成功',
                                '病情',
                                '血压',
                                '测血压',
                                '使用',
                                '氧',
                                '仍无自主呼吸心跳',
                                '心电图示直线',
                                '临床死亡',
                                '宣布',
                                '转入',
                                '死亡',
                                '出现意识不清',
                                '心电图示无心电',
                                '呼吸及血压'
                            ];

                            // 从标题时间中提取年份，用于拼接短格式日期
                            $year = date('Y', strtotime($zxsj_datetime_str));

                            foreach ($keywords as $keyword) {
                                // 匹配格式：于YYYY-MM-DD HH:MM[:SS] + 关键词（秒为可选参数）
                                if (preg_match('/于\s*(\d{4}-\d{1,2}-\d{1,2}\s+\d{1,2}:\d{1,2}(?::\d{1,2})?)\s*' . preg_quote($keyword, '/') . '/', $hjnr, $matches)) {
                                    $match_datetime_str = $matches[1];
                                    break;
                                }
                            }

                            if (empty($match_datetime_str)) {

                                // 匹配格式：于MM-DD HH时MM分 + 关键词（年份从zxsj中提取）
                                if (empty($match_datetime_str)) {
                                    foreach ($keywords as $keyword) {
                                        if (preg_match('/于\s*(\d{2}-\d{2})\s+(\d{1,2})时(\d{1,2})分\s*' . preg_quote($keyword, '/') . '/', $hjnr, $matches)) {
                                            $monthDay = $matches[1];
                                            $hour = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
                                            $minute = str_pad($matches[3], 2, '0', STR_PAD_LEFT);
                                            $match_datetime_str = "{$year}-{$monthDay} {$hour}:{$minute}";
                                            break;
                                        }
                                    }
                                }
                            }

                            if (empty($match_datetime_str)) {

                                // 匹配抢救结束时间字段格式（秒为可选参数）
                                if (empty($match_datetime_str)) {
                                    if (preg_match('/抢救结束时间[：:]\s*(\d{4}-\d{1,2}-\d{1,2}\s+\d{1,2}:\d{1,2}(?::\d{1,2})?)/', $hjnr, $matches)) {
                                        $match_datetime_str = $matches[1];
                                    }
                                }

                                if ($match_datetime_str) {
                                    $parsedRescueEndDateTimeStr = $match_datetime_str;
                                }
                            }
                        }
                    } catch (\Exception $e) {
                        Log::error("rule-{$ruleId}-{$ZYH}: Error fetching HJNR for BLBH {$blbh}: " . $e->getMessage());
                    }
                }

                // 如果没有解析到抢救结束时间，跳过
                if (empty($parsedRescueEndDateTimeStr)) {
                    continue;
                }

                $parsedRescueEndObj = strtotime($parsedRescueEndDateTimeStr);

                // 添加抢救时间信息
                $recordBasis[] = "抢救时间【{$parsedRescueEndDateTimeStr}】";

                // 判断是否需要质控
                $needControl = true; // 默认需要质控
                $signatureStatusOutput = "";

                // 计算抢救时间加6小时的时间点（用于判断是否超时）
                $deadlineTime = null;
                if ($parsedRescueEndObj !== null) {
                    $deadlineTime = $parsedRescueEndObj + 6 * 3600;
                }
                //获取当前时间
                $currentDate = Carbon::now()->toDateTimeString();
                //$sixHoursAfterSurgery = date('Y-m-d H:i:s', strtotime($parsedRescueEndObj) + 6 * 3600);
                //如果当前时间小于抢救结束时间+6小时就跳过
                if (strtotime($currentDate) < $deadlineTime) {
                    continue;
                }

                // 检查签名情况
                if (empty($signature_datetime_str) || $signature_datetime_str == '1970-01-01 00:00:00' || $signature_datetime_str == '0000-00-00 00:00:00') {
                    $signatureStatusOutput = "首次签名【（无）】";
                } else {
                    $signature_datetime_obj = strtotime($signature_datetime_str);

                    if ($parsedRescueEndObj !== null && $deadlineTime !== null) {
                        // 检查是否需要调整跨天情况：签名时间在0-3点，抢救结束时间在23点之后
                        $signature_hour = (int)date('H', $signature_datetime_obj);
                        $rescue_end_hour = (int)date('H', $parsedRescueEndObj);
                        if ($signature_hour >= 0 && $signature_hour <= 5 && $rescue_end_hour >= 22 && $parsedRescueEndObj > $signature_datetime_obj) {
                            // 签名时间在凌晨0-1点，抢救结束时间在晚上23点之后，且抢救结束时间大于签名时间时，调整截止时间减去一天
                            $deadlineTime = $deadlineTime - 24 * 3600;
                            $parsedRescueEndObj = $parsedRescueEndObj - 24 * 3600;
                        }
                        if ($signature_datetime_obj > $deadlineTime) {
                            $signatureStatusOutput = "首次签名【{$signature_datetime_str}（超时）】";
                        } elseif ($signature_datetime_obj < $parsedRescueEndObj) {
                            $signatureStatusOutput = "首次签名【{$signature_datetime_str}（提前）】";
                        } else {
                            $signatureStatusOutput = "首次签名【{$signature_datetime_str}（正常）】";
                            $needControl = false; // 签名时间正常，不需要质控
                        }
                    } else {
                        // 无法确定抢救结束时间，只展示签名时间
                        $signatureStatusOutput = "首次签名【{$signature_datetime_str}】";
                        if ($parsedRescueEndDateTimeStr === "无") {
                            $needControl = true; // 无法确定抢救结束时间，需要质控
                        } else {
                            $needControl = false; // 签名存在但无法判断是否超时，不质控
                        }
                    }
                }

                // 添加签名状态信息
                $recordBasis[] = $signatureStatusOutput;

                // 如果需要质控，将这条记录添加到结果集
                if ($needControl) {
                    $allBasisGroups[] = $recordBasis;
                }
            }

            // 将所有质控结果一次性插入到insertData
            if (!empty($allBasisGroups)) {
                $this->insertData[] = [
                    'JZHM' => $ZYH,
                    'rule_id' => $ruleId,
                    'code' => 'rule_' . $ruleId,
                    'error_field' => $caseRule[$ruleId]['title'],
                    'basis' => json_encode($allBasisGroups, JSON_UNESCAPED_UNICODE)
                ];
            }

            return true;
        } catch (\Exception $e) {
            Log::error("rule-{$ruleId}-{$ZYH}-error (NewRescueTimeCheck): " . $e->getMessage());
            return true; // 出错时也返回true，避免中断质控流程
        }
    }

    public function rule1022($caseRule, $ruleId, $ZYH)
    {

        // 1. 获取出院时间和入院时间AAB01，如果没有取当前时间，用这个时间-入院时间如果小与24小时，则不质控
        $exitTime = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->value('AAC01');
        if (empty($exitTime)) {
            $exitTime = Carbon::now()->toDateTimeString();
        }

        // 2. 获取入院时间AAB01
        $enterTime = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->value('AAB01');
        if (empty($enterTime)) {
            return true;
        }

        // 3. 计算出院时间-入院时间
        $enterDate = date('Y-m-d', strtotime($enterTime));
        $exitDate = date('Y-m-d', strtotime($exitTime));
        $diffDays = (strtotime($exitDate) - strtotime($enterDate)) / (24 * 3600);

        //获取当前时间
        $currentDate = Carbon::now()->toDateTimeString();
        $exitTimeEnd = strtotime($exitTime) + 24 * 3600;
        //如果当前时间小于出院时间+24小时就跳过
        if (strtotime($currentDate) < $exitTimeEnd) {
            return true;
        }

        //查询cyjl
        $cyjl = EMR_BL_BL01::query()->where('JZHM', $ZYH)->where('BLLB', 1)->get()->toArray();
        if ($diffDays > 1 || ($diffDays <= 1 && !empty($cyjl))) {
            return true;
        }

        //4. 查询医嘱
        $ruleMap8000 = RuleWordMap::query()->where('id', '=', 8000)->value('keyword');
        $ruleMap8000 = !empty($ruleMap8000) ? $ruleMap8000 : "出院,离院";  // 医嘱名称包含的关键字
        //判断是否包含逗号
        if (strpos($ruleMap8000, ',') !== false) {
            $exitKeywords = explode(',', $ruleMap8000);
        } else {
            $exitKeywords = [$ruleMap8000];
        }

        // 5. 从MySQL查询医嘱是否包含出院/离院关键字
        $yzbQuery = Yzb::query()->where('ZYH', $ZYH)->get()->toArray();
        $yzbData = [];
        foreach ($yzbQuery as $record) {
            if (strpos($record['YZMC'], '死亡') !== false) {
                continue;
            }
            foreach ($exitKeywords as $keyword) {
                if (strpos($record['YZMC'], $keyword) !== false) {
                    $yzbData[] = $record;
                    break;
                }
            }
        }

        // 如果没有出院/离院医嘱，则不进行质控
        if (empty($yzbData)) {
            return true;
        }

        //6. 查询出院记录
        $ruleMap8051 = RuleWordMap::query()->where('id', '=', 8051)->value('keyword');
        $ruleMap8051 = !empty($ruleMap8051) ? $ruleMap8051 : '18,1';  // 病案首页BLLB

        //判断是否包含逗号
        if (strpos($ruleMap8051, ',') !== false) {
            $exitKeywords = explode(',', $ruleMap8051);
        } else {
            $exitKeywords = [$ruleMap8051];
        }
        $basis = [];
        // 出院时间依据
        $basis[] = "出院时间【" . $exitTime . "】";
        // 4. 查询病案首页数据，注意病案首页BLLB可能包含多个值
        $bl01Data = EMR_BL_BL01::query()->where('JZHM', $ZYH)->whereIn('BLLB', $exitKeywords)->get()->toArray();

        if (empty($bl01Data)) {
            $basis[] = "出院记录【未创建】";
            $this->insertData[] = [
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title'],
                'basis' => json_encode([$basis], JSON_UNESCAPED_UNICODE)
            ];

            return true;
        }

        $bl01_mc = $bl01Data[0]['BLMC'];
        //8023
        $ruleMap8023 = RuleWordMap::query()->where('id', '=', 8023)->value('keyword');
        $ruleMap8023 = !empty($ruleMap8023) ? $ruleMap8023 : '日间';
        //判断是否包含逗号
        if (strpos($ruleMap8023, ',') !== false) {
            $exitKeywords8023 = explode(',', $ruleMap8023);
        } else {
            $exitKeywords8023 = [$ruleMap8023];
        }
        //8024
        $ruleMap8024 = RuleWordMap::query()->where('id', '=', 8024)->value('keyword');
        $ruleMap8024 = !empty($ruleMap8024) ? $ruleMap8024 : '入出院记录,出入院记录';
        //判断是否包含逗号
        if (strpos($ruleMap8024, ',') !== false) {
            $exitKeywords8024 = explode(',', $ruleMap8024);
        } else {
            $exitKeywords8024 = [$ruleMap8024];
        }
        $isExclude = false;
        foreach ($exitKeywords8023 as $keyword) {
            if (strpos($bl01_mc, $keyword) !== false) {
                foreach ($exitKeywords8024 as $keyword8024) {
                    if (strpos($bl01_mc, $keyword8024) !== false) {
                        $isExclude = true;
                        break;
                    }
                }
            }
        }

        if (!$isExclude) {
            return true;
        }

        //取id8002的keyword
        $ruleMap8002 = RuleWordMap::query()->where('id', '=', 8002)->value('keyword');
        $ruleMap8002 = !empty($ruleMap8002) ? $ruleMap8002 : 'frist_blsy_time';

        $firstBlsyTime = $bl01Data[0][$ruleMap8002];
        //如果为空设置basis
        if (empty($firstBlsyTime)) {
            $basis[] = "【" . $bl01_mc . "】";
            $basis[] = "首次签名时间【未签名】";
            $basis["BLBH"] = $bl01Data[0]['BLBH']; //加入被质控病程BLBH
            $this->insertData[] = [
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title'],
                'basis' => json_encode([$basis], JSON_UNESCAPED_UNICODE)
            ];
            //发送预警
            // 检查死亡时间+24小时-当前之间是否大于0小于2小时
            $exitTimeEnd = strtotime($exitTime) + 24 * 3600;
            $diffTime = $exitTimeEnd - time();

            return true;
        }

        //如果firstBlsyTime大于出院时间+24小时，则质控
        if (strtotime($firstBlsyTime) > strtotime($exitTime) + 24 * 3600) {
            $basis[] = "【" . $bl01_mc . "】";
            $basis[] = "首次签名时间【" . $firstBlsyTime . "（超24小时）】";
            $basis["BLBH"] = $bl01Data[0]['BLBH'];
            $this->insertData[] = [
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title'],
                'basis' => json_encode([$basis], JSON_UNESCAPED_UNICODE)
            ];
            return true;
        }
        return true;
    }

    /**
     * 出院（死亡）记录诊疗过程中要的“手术名称” ，与手术记录中的不一致
     * 诊疗过程中要有“手术名称”和手术记录中一致
     */
    public function rule1044($caseRule, $ruleId, $ZYH)
    {

        //查询是否有手术记录bllb303
        $ssjl = Bllb303::query()->where('ZYH', $ZYH)->get()->toArray();
        if (empty($ssjl)) {
            return true;
        }

        //查询是否有出院记录bllb1
        $cxjl = null;
        $cyjl = Bllb1::query()->where('ZYH', $ZYH)->get()->toArray();
        if (empty($cyjl)) {
            //查询是否有死亡记录bllb288
            $swsj = Bllb288::query()->where('ZYH', $ZYH)->get()->toArray();
            if (empty($swsj)) {
                return true;
            } else {
                $cxjl = $swsj;
            }
        } else {
            $cxjl = $cyjl;
        }
        $basis = [];
        $zljg = $cxjl[0]['ZLJG'];
        foreach ($ssjl as $v) {
            //获取ssmc
            $ssmc = $v['SSMC'];
            if (strpos($zljg, $ssmc) == false) {
                $basis[] = "手术名称【{$ssmc}】，与诊疗过程中的不一致";
            }
        }
        if (!empty($basis)) {
            $this->insertData[] = [
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title'],
                'basis' => json_encode([$basis], JSON_UNESCAPED_UNICODE)
            ];
        }
        return true;
    }

    /**
     * 术后首次病程与手术记录中的手术名称不一致
     */
    public function rule1045($caseRule, $ruleId, $ZYH)
    {

        //获取出入院时间
        $enterTime = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->value('AAB01');
        $exitTime = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->value('AAC01');

        // 如果没有出院时间或出院时间为默认值，使用当前时间
        if (empty($exitTime) || $exitTime == '1970-01-01 00:00:00' || $exitTime == '0000-00-00 00:00:00') {
            $exitTime = date('Y-m-d H:i:s');
        }

        // 3. 计算出院时间-入院时间
        $enterDate = date('Y-m-d', strtotime($enterTime));
        $exitDate = date('Y-m-d', strtotime($exitTime));
        $diffDays = (strtotime($exitDate) - strtotime($enterDate)) / (24 * 3600);
        if ($diffDays <= 1) {
            return true;
        }

        //从bllb303中查询手术记录
        $ssjl = Bllb303::query()->where('ZYH', $ZYH)->get()->toArray();
        if (empty($ssjl)) {
            return true;
        }

        $ruleMap8074 = RuleWordMap::query()->where('id', 8074)->value('keyword');
        $ruleMap8074 = !empty($ruleMap8074) ? $ruleMap8074 : '42';
        //判断是否包含逗号
        if (strpos($ruleMap8074, ',') !== false) {
            $ruleMap8074 = explode(',', $ruleMap8074);
        } else {
            $ruleMap8074 = [$ruleMap8074];
        }

        //收集所有质控结果
        $allBasisGroups = [];

        //对每条手术记录单独进行质控检查
        foreach ($ssjl as $surgery) {
            $ssmc = $surgery['SSMC'] ?? '';
            if (empty($ssmc)) {
                continue;
            }

            //从bl01查询术后首次病程
            $bl01 = null;
            $bl01 = EMR_BL_BL01::query()->where('JZHM', $ZYH)->whereIn('MBLB', $ruleMap8074)->get()->toArray();
            if (empty($bl01)) {
                $bl011 = EMR_BL_BL01::query()->where('JZHM', $ZYH)->where('BLLB', 294)->where('BLMC', 'like', '%术后首次病程%')->get()->toArray();
                if (empty($bl011)) {
                    // 记录质控结果 - 未找到记录
                    $basis = [];
                    $basis[] = "手术名称【" . $ssmc . "】";
                    $basis[] = "【术后首次病程】【无】";
                    $allBasisGroups[] = $basis;
                    continue;
                }
                $bl01 = $bl011;
            }

            $isMatch = false;
            $matchedRecord = null;

            //检查每条病程记录的HJNR字段是否包含手术名称
            foreach ($bl01 as $record) {
                $blbh = $record['BLBH'] ?? '';
                if (empty($blbh)) {
                    continue;
                }

                //通过BLBH获取HJNR字段
                $blxgData = EMR_BL_BLXG::getById($blbh);
                $hjnr = $blxgData[0]['HJNR'] ?? '';

                if (!empty($hjnr) && strpos($hjnr, $ssmc) !== false) {
                    $isMatch = true;
                    $matchedRecord = $record;
                    break;
                }
            }

            //如果没有匹配的记录，记录质控结果
            if (!$isMatch) {
                $basis = [];
                $basis[] = "手术名称【" . $ssmc . "】";
                $allBasisGroups[] = $basis;
            }
        }

        //将所有质控结果一次性插入到insertData
        if (!empty($allBasisGroups)) {
            $this->insertData[] = [
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title'],
                'basis' => json_encode($allBasisGroups, JSON_UNESCAPED_UNICODE)
            ];
        }

        return true;
    }

    /**
     * 规则1063: 具"培养"的医嘱，未在检验报告单结果出具后的72小时内完成相关病程记录
     * @param array $caseRule 规则配置
     * @param int $ruleId 规则ID
     * @param string $ZYH 住院号
     * @return bool
     */
    public function rule1063($caseRule, $ruleId, $ZYH)
    {

        $rule8049 = RuleWordMap::query()->where('id', 8049)->value('keyword');
        if (strpos($rule8049, ",")) {
            $rule8049 = explode(",", $rule8049);
        } else {
            $rule8049 = [$rule8049];
        }
        $rule8080 = RuleWordMap::query()->where('id', 8080)->value('keyword');
        if (strpos($rule8080, ",")) {
            $rule8080 = explode(",", $rule8080);
        } else {
            $rule8080 = [$rule8080];
        }
        $rule8002 = RuleWordMap::query()->where('id', 8002)->value('keyword');
        $rule8002 = !empty($rule8002) ? $rule8002 : 'first_blsy_time';
        $rule8011 = RuleWordMap::query()->where('id', 8011)->value('keyword');
        $rule8011 = !empty($rule8011) ? $rule8011 : 'ZXSJ';
        $basisList = [];

        // 1. 查询V_JMGS_YMresult表中有培养结果的记录
        $ymResults = V_JMGS_YMresult::query()
            ->where('ZYH', $ZYH)
            ->whereNotNull('PYJG')
            ->where('PYJG', '!=', '')
            ->get(['EXAMINAIM', 'BGSJ', 'XJMC', 'PYJG'])
            ->toArray();

        if (empty($ymResults)) {
            return true;
        }

        // 2. 筛选医嘱名称包含"培养"的记录
        $cultivationResults = [];
        foreach ($ymResults as $result) {
            if (strpos($result['EXAMINAIM'], '培养') !== false) {
                $cultivationResults[] = $result;
            }
        }

        if (empty($cultivationResults)) {
            return true;
        }

        // 3. 获取病程记录相关配置（删除ES服务）

        // 病程记录类型配置 (BLLB = 294, MBLB = 296,50 上级医师查房记录，日常病程记录)
        $bcRecordTypes = $rule8049; // 上级医师查房记录，日常病程记录

        // 4. 检查每个培养结果
        foreach ($cultivationResults as $result) {
            $basis = [];
            $reportTime = $result['BGSJ']; // 报告时间
            $examinaim = $result['EXAMINAIM']; // 医嘱名称
            $xjmc = $result['XJMC']; // 细菌名称


            if (empty($reportTime)) {
                continue;
            }

            $basis[] = '报告时间【' . $reportTime . '】';
            $basis[] = '医嘱名称【' . $examinaim . '】';

            // 计算72小时后的时间
            $deadline = date('Y-m-d H:i:s', strtotime($reportTime) + 72 * 3600);

            //获取当前时间
            $currentDate = Carbon::now()->toDateTimeString();
            //如果当前时间小于报告时间+72小时就跳过
            if (strtotime($currentDate) < strtotime($deadline)) {
                continue;
            }

            // 5. 从MySQL查询报告时间到72小时内的病程记录
            $bl01Query = EMR_BL_BL01::query()
                ->leftJoin('EMR_BL_BLXG', 'EMR_BL_BLXG.BLBH', '=', 'EMR_BL_BL01.BLBH')
                ->where('JZHM', $ZYH)
                ->whereIn('MBLB', $bcRecordTypes)
                ->where($rule8011, '>=', $reportTime)
                ->where($rule8011, '<=', $deadline)->get(['EMR_BL_BL01.*', 'EMR_BL_BLXG.HJNR'])->toArray();

            $resData = [];
            foreach ($bl01Query as $record) {
                if (!empty($xjmc) && strpos($record['HJNR'], $xjmc) !== false) {
                    $resData[] = $record;
                    break;
                }
                foreach ($rule8080 as $v) {
                    if (strpos($record['HJNR'], $v) !== false) {
                        $resData[] = $record;
                        break;
                    }
                }
            }

            $errorRecord = [];

            // 6. 检查是否有符合条件的病程记录
            if (empty($resData)) {
                $basis[] = '病程记录【无】';
                $basisList[] = $basis;
            } else {
                // 检查签名时间是否在规定时间内
                $hasValidRecord = false;
                foreach ($resData as $record) {
                    $signTime = $record[$rule8002] ?? null;
                    if ($signTime && strtotime($signTime) >= strtotime($reportTime) && strtotime($signTime) <= strtotime($deadline)) {
                        $hasValidRecord = true;
                        break;
                    } else {
                        $errorRecord[] = $record;
                    }
                }

                if (!$hasValidRecord && !empty($errorRecord)) {
                    // 找到最近的一条记录作为示例
                    foreach ($errorRecord as $record) {
                        $signTime = $record[$rule8002] ?? '无';
                        $basis[] = '【' . $record['BLMC'] . '】首次签名时间【' . $signTime . '（超时）】';
                    }
                    $basisList[] = $basis;
                }
            }
        }

        // 7. 如果有缺陷，记录到数据库
        if (!empty($basisList)) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, JSON_UNESCAPED_UNICODE),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'pyjg',
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }

        return true;
    }

    /**
     * @param $caseRule
     * @param $ruleId
     * @param $ZYH
     * @return array
     * 操作完成后未在24小时内完成操作记录
     */
    public function rule1064($caseRule = [], $ruleId, $ZYH = "")
    {

        $rule8010 = RuleWordMap::query()->where('id', 8010)->value('keyword');
        if (strpos($rule8010, ",")) {
            $rule8010 = explode(",", $rule8010);
        } else {
            $rule8010 = [$rule8010];
        }
        $rule8081 = RuleWordMap::query()->where('id', 8081)->value('keyword');
        if (strpos($rule8081, ",")) {
            $rule8081 = explode(",", $rule8081);
        } elseif (!empty($rule8081)) {
            $rule8081 = [$rule8081];
        }
        $rule8002 = RuleWordMap::query()->where('id', 8002)->value('keyword');
        $rule8002 = !empty($rule8002) ? $rule8002 : 'first_blsy_time';
        $rule8011 = RuleWordMap::query()->where('id', 8011)->value('keyword');
        $rule8011 = !empty($rule8011) ? $rule8011 : 'ZXSJ';
        // 从MySQL查询符合条件的病程记录：BLLB=294且BLMC含"操作记录"
        $bl01Query = EMR_BL_BL01::query()
            ->where('JZHM', $ZYH)
            ->whereIn('BLLB', $rule8010)->get()->toArray();
        $resData = [];
        foreach ($bl01Query as $record) {
            foreach ($rule8081 as $v) {
                if (strpos($record['BLMC'], $v) !== false) {
                    $resData[] = $record;
                    break;
                }
            }
        }

        if (empty($resData) || empty($resData[0]) || empty($resData[0]['BLBH'])) {
            return true;
        }

        $bl01 = $resData;
        $basisList = [];

        foreach ($bl01 as $record) {

            $HJNR = $record['HJNR'];
            $BLBH = $record['BLBH'];
            $BLMC = $record['BLMC'];
            $ZXSJ = $record['ZXSJ'];


            // 提取操作时间（今日{XX时XX分}患者）
            preg_match('/今日\{(\d{2}时\d{2}分)\}/', $HJNR, $timeMatches);
            //新增匹配（{00时30分}）
            if (empty($timeMatches[1])) {
                preg_match('/\{(\d{2}时\d{2}分)\}/', $HJNR, $timeMatches2);
            }
            //新增匹配 于今日13:24
            if (empty($timeMatches[1]) && empty($timeMatches2[1])) {
                preg_match('/于今日(\d{2}:\d{2})/', $HJNR, $timeMatches4);
            }
            //新增匹配（00时30分）
            if (empty($timeMatches[1]) && empty($timeMatches2[1]) && empty($timeMatches4[1])) {
                preg_match('/(\d{2}时\d{2}分)/', $HJNR, $timeMatches3);
            }

            if (empty($timeMatches[1]) && empty($timeMatches2[1]) && empty($timeMatches3[1]) && empty($timeMatches4[1])) {
                continue;
            }

            $operationTimeStr = $timeMatches[1] ?? $timeMatches2[1] ?? $timeMatches3[1] ?? $timeMatches4[1] ?? ''; // 例如：04时40分
            // 从ZXSJ提取年月日
            if (empty($ZXSJ)) {
                $basis = [];
                $basis[] = $BLMC;
                $basis[] = '操作时间【无法解析，ZXSJ为空】';
                $basisList[] = $basis;
                continue;
            }

            try {
                $zxsjDate = date('Y-m-d', strtotime($ZXSJ));
                $operationTime = $zxsjDate . ' ' . str_replace(['时', '分'], ':', $operationTimeStr) . ':00';
            } catch (\Exception $e) {
                $basis = [];
                $basis[] = $BLMC;
                $basis[] = '操作时间【解析失败: ' . $operationTimeStr . '】';
                $basisList[] = $basis;
                continue;
            }


            // 获取首次签名时间
            $firstSignTime = $record[$rule8002];

            if (empty($firstSignTime)) {
                $basis = [];
                $basis[] = $BLMC;
                $basis[] = '操作时间【' . $operationTime . '】';
                $basis[] = '首次签名时间【无】';
                $basisList[] = $basis;
                continue;
            }


            // 检查是否在24小时内
            $deadline = date('Y-m-d H:i:s', strtotime($operationTime) + 24 * 3600);

            //获取当前时间
            $currentDate = Carbon::now()->toDateTimeString();
            //$oneDayAfterSurgery = date('Y-m-d H:i:s', strtotime($firstSignTime) + 24 * 3600);
            //如果当前时间小于首次签名时间+24小时就跳过
            if (strtotime($currentDate) < strtotime($deadline)) {
                continue;
            }
            if (strtotime($firstSignTime) <= strtotime($operationTime) && strtotime($firstSignTime) >= strtotime($deadline)) {
                $basis = [];
                $basis[] = $BLMC;
                $basis[] = '操作时间【' . $operationTime . '】';
                $basis[] = '首次签名时间【' . $firstSignTime->format('Y-m-d H:i:s') . '】（超24小时）';
                $basisList[] = $basis;
            }
        }

        if (!empty($basisList)) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, JSON_UNESCAPED_UNICODE),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => '',
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }

        return [];
    }

    public function rule1213($caseRule = [], $ruleId, $ZYH = "")
    {
        //获取出入院时间
        $enterTime = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->value('AAB01');

        //获取首次病程zxsj
        $SCBC = EMR_BL_BL01::query()->where('JZHM', '=', $ZYH)->where('MBLB', '=', 295)->get()->toArray();
        if (empty($SCBC)) {
            return true;
        }
        $SCBC1 = $SCBC[0]['ZXSJ'] ?? '';
        if (empty($SCBC1) || strpos($SCBC1, '0000-00-00') !== false) {
            $SCBC1 = $SCBC[0]['CJSJ'] ?? '';
        }

        if (empty($SCBC1)) {
            return true;
        }


        //如果zxsj早于入院时间就质控
        if (strtotime($SCBC1) < strtotime($enterTime)) {
            $basis = [];
            $basis[] = '首次病程时间【' . $SCBC1 . '】不应早于入院时间【' . $enterTime . '】';
            $basisList[] = $basis;
            $this->insertData[] = [
                'basis' => json_encode($basisList, JSON_UNESCAPED_UNICODE),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }



        return [];
    }

    //抢救记录时间不能早于抢救结束时间
    public function rule1214($caseRule = [], $ruleId, $ZYH = "")
    {


        //获取抢救记录
        $QJJL = EMR_BL_BL01::query()->leftJoin('EMR_BL_BLXG', 'EMR_BL_BL01.BLBH', '=', 'EMR_BL_BLXG.BLBH')->where('JZHM', '=', $ZYH)->where('MBLB', '=', 27)->get()->toArray();
        if (empty($QJJL)) {
            return true;
        }

        $basisList = [];

        foreach ($QJJL as $record) {
            $QJJLSJ = $record['ZXSJ'];
            $hjnr = $record['HJNR'];
            $match_time_str = '';
            $basis = [];
            $basis[] = '抢救记录【' . $record['BLMC'] . '】';
            if (empty($hjnr)) {
                $basis[] = '抢救记录时间【无】';
                $basisList[] = $basis;
                continue;
            }
            if (preg_match('/抢救结束时间：\s*(\d{4}-\d{1,2}-\d{1,2})\s+(\d{1,2}:\d{1,2})/', $hjnr, $matches)) {
                // 新增匹配 抢救结束时间：2025-10-23 00:23 取2025-10-23 00:23 (全角冒号)
                $match_time_str = $matches[1] . ' ' . $matches[2];
            } elseif (preg_match('/抢救结束时间:\s*(\d{4}-\d{1,2}-\d{1,2})\s+(\d{1,2}:\d{1,2})/', $hjnr, $matches)) {
                // 新增匹配 抢救结束时间:2025-10-23 00:23 取2025-10-23 00:23 (半角冒号)
                $match_time_str = $matches[1] . ' ' . $matches[2];
            }

            if (empty($match_time_str)) {
                // 通过正则获取$hjnr中的所有时间，并且检查所有的时间是否都小于$QJJLSJ
                // 匹配所有日期时间 yyyy-MM-dd HH:mm 或 yyyy-MM-dd HH:mm:ss，时间后紧跟【检验结果】的不匹配
                preg_match_all('/\d{4}-\d{1,2}-\d{1,2}[\sT]+\d{1,2}:\d{1,2}(?::\d{1,2})?/u', $hjnr, $allTimes);
                // preg_match_all('/(?!\d{4}-\d{1,2}-\d{1,2}[\sT]+\d{1,2}:\d{1,2}(?::\d{1,2})?检验结果)\d{4}-\d{1,2}-\d{1,2}[\sT]+\d{1,2}:\d{1,2}(?::\d{1,2})?/u', $hjnr, $allTimes);
                if (!empty($allTimes[0])) {
                    $allEarlier = true;
                    foreach ($allTimes[0] as $timeStr) {
                        // 统一去掉中文、全角、额外空格，并做格式兼容
                        $compareTime = trim($timeStr);
                        if (strtotime($compareTime) >= strtotime($QJJLSJ)) {
                            $allEarlier = false;
                            break;
                        }
                    }
                    if (!$allEarlier) {
                        $basis[] = '有抢救记录相关时间不早于抢救记录时间【' . $QJJLSJ . '】';
                        $basisList[] = $basis;
                        continue;
                    }
                } else {
                    $basis[] = '抢救结束时间【无】';
                    $basisList[] = $basis;
                    continue;
                }
            } else {
                if (strtotime($QJJLSJ) < strtotime($match_time_str)) {
                    $basis[] = '抢救记录时间【' . $QJJLSJ . '】不应早于抢救结束时间【' . $match_time_str . '】';
                    $basisList[] = $basis;
                }
            }
        }

        if (!empty($basisList)) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, JSON_UNESCAPED_UNICODE),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }

        return [];
    }

    //抢救记录--无主治或主治以上医师参与抢救或无审核签名
    public function rule488($caseRule = [], $ruleId, $ZYH = "")
    {
        $keyword8012 = RuleWordMap::getArrayById(8012);

        //获取抢救记录
        $QJJL = EMR_BL_BL01::query()->leftJoin('EMR_BL_BLXG', 'EMR_BL_BL01.BLBH', '=', 'EMR_BL_BLXG.BLBH')->where('EMR_BL_BL01.JZHM', '=', $ZYH)->where('MBLB', '=', 27)->get()->toArray();
        if (empty($QJJL)) {
            return true;
        }

        $basisList = [];

        $staff = Staff::query()->get()->toArray();
        $staff = array_column($staff, null, 'code');
        foreach ($QJJL as $record) {
            $flag = false;
            $blsy = EMR_BL_BLSY::query()->where('BLBH', $record['BLBH'])->where('FG_ACTIVE', 1)->get()->toArray();
            foreach ($blsy as $item) {
                if (!empty($staff[$item['SYYS']])) {
                    foreach ($keyword8012 as $keyword) {
                        if (strpos($staff[$item['SYYS']]['ygjb_text'], $keyword) === false) {
                            $flag = true;
                            break;
                        }
                    }
                }
            }
            if (!$flag) {
                $hjnr = $record['HJNR'];
                foreach ($keyword8012 as $item) {
                    if (strpos($hjnr, $item) !== false) {
                        $flag = true;
                        break;
                    }
                }
            }

            if (!$flag) {
                $basis[] = '病历名称：【' . $record['BLMC'] . '】，不包含主治或主治以上医师';
                $basisList[] = $basis;
                continue;
            }
        }

        if (!empty($basisList)) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, JSON_UNESCAPED_UNICODE),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }

        return [];
    }

    //死亡记录死亡时间与死亡讨论记录时间不一致
    public function rule1215($caseRule = [], $ruleId, $ZYH = "")
    {

        //查询死亡记录
        $SWJL = EMR_BL_BL01::query()->leftJoin('EMR_BL_BLXG', 'EMR_BL_BL01.BLBH', '=', 'EMR_BL_BLXG.BLBH')->where('JZHM', '=', $ZYH)->where('MBLB', '=', 288)->get()->toArray();
        if (empty($SWJL)) {
            return true;
        }

        $hjnr = $SWJL[0]['HJNR'];

        $deathTime = '';

        $basisList = [];

        $basis = [];

        //提取死亡记录死亡时间
        //死亡时间：2025年8月28日 22:35:00
        if (preg_match('/死亡时间：(\d{4}年\d{1,2}月\d{1,2}日 \d{1,2}:\d{1,2}:\d{1,2})/', $hjnr, $matches)) {
            $deathTime = $matches[1];

            //先转换
            $deathTime = str_replace('年', '-', $deathTime);
            $deathTime = str_replace('月', '-', $deathTime);
            $deathTime = str_replace('日', '', $deathTime);
            // 标准化日期格式
            //$deathTime = date('Y-m-d H:i', strtotime($deathTime));
        }

        //死亡时间：2025年8月28日 22:35
        if (preg_match('/死亡时间：(\d{4}年\d{1,2}月\d{1,2}日 \d{1,2}:\d{1,2})/', $hjnr, $matches)) {
            $deathTime = $matches[1];

            //先转换
            $deathTime = str_replace('年', '-', $deathTime);
            $deathTime = str_replace('月', '-', $deathTime);
            $deathTime = str_replace('日', '', $deathTime);
            // 标准化日期格式
            //$deathTime = date('Y-m-d H:i', strtotime($deathTime));
        }

        if (empty($deathTime)) {
            return true;
        }

        $basis[] = '死亡记录死亡时间【' . $deathTime . '】';

        //查询死亡讨论记录
        $SWJL = EMR_BL_BL01::query()->leftJoin('EMR_BL_BLXG', 'EMR_BL_BL01.BLBH', '=', 'EMR_BL_BLXG.BLBH')->where('JZHM', '=', $ZYH)->where('MBLB', '=', 4302)->get()->toArray();
        if (empty($SWJL)) {
            $basis[] = '死亡讨论记录【无】';
        }

        $swtlswsj = '';

        $hjnr = $SWJL[0]['HJNR'];
        //提取死亡讨论记录时间
        //死亡讨论记录时间：2025-8-28 22:35:00
        if (preg_match('/死亡时间：(\d{4}-\d{1,2}-\d{1,2} \d{1,2}:\d{1,2}:\d{1,2})/', $hjnr, $matches)) {
            $swtlswsj = $matches[1];
            // 标准化日期格式
            //$deathTime = date('Y-m-d H:i', strtotime($deathTime));
        }

        //死亡讨论记录时间：2025-8-28 22:35
        elseif (preg_match('/死亡时间：(\d{4}-\d{1,2}-\d{1,2} \d{1,2}:\d{1,2})/', $hjnr, $matches)) {
            $swtlswsj = $matches[1];
            // 标准化日期格式
            //$deathTime = date('Y-m-d H:i', strtotime($deathTime));
        }

        if (empty($swtlswsj)) {
            $basis[] = '死亡讨论记录时间【无】';
        } else {
            if ($deathTime != $swtlswsj) {
                $basis[] = '死亡讨论记录时间【' . $swtlswsj . '】';
            } else {
                return true;
            }
        }



        $basisList[] = $basis;

        if (!empty($basisList)) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, JSON_UNESCAPED_UNICODE),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }

        return [];
    }

    public function rule1216($caseRule = [], $ruleId, $ZYH = "")
    {
        //获取出入院时间
        $enterTime = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->value('AAB01');

        $exitTime = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->value('AAC01');
        if (empty($exitTime)) {
            //使用当前时间
            $exitTime = Carbon::now()->toDateTimeString();
            $exitTime = date('Y-m-d 23:59:59', strtotime($exitTime));
        }

        //获取首次病程zxsj
        $BCJL = EMR_BL_BL01::query()->where('JZHM', '=', $ZYH)->where('BLLB', '=', 294)->get()->toArray();
        if (empty($BCJL)) {
            return true;
        }

        $basisList = [];
        $exitTime = date('Y-m-d H:i:s', strtotime($exitTime) + 24 * 3600); //+24小时
        foreach ($BCJL as $item) {
            $ZXSJ = $item['ZXSJ'] ?? '';
            if (empty($ZXSJ) || strpos($ZXSJ, '0000-00-00') !== false) {
                $ZXSJ = $item['CJSJ'] ?? '';
            }
            if (empty($ZXSJ)) {
                continue;
            }
            if (strtotime($ZXSJ) > strtotime($exitTime) || strtotime($ZXSJ) < strtotime($enterTime)) {
                $basis = [];
                $basis[] = '病程记录【' . $item['BLMC'] . '】标题时间【' . $ZXSJ . '】应在入院时间和出院时间或当天之间';
                $basisList[] = $basis;
            }
        }

        if (!empty($basisList)) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, JSON_UNESCAPED_UNICODE),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }

        return true;
    }

    public function rule1218($caseRule = [], $ruleId, $ZYH = "")
    {
        // 2. 获取患者科室
        $brks = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->value('BRKS');
        if (empty($brks)) {
            return []; // 没有科室信息
        }

        // 4. 判断是否为产科且需要质控
        //获取产科代码id8025
        $id8025 = RuleWordMap::query()->where('id', '=', 8025)->value('keyword');
        //是否包含逗号
        if (strpos($id8025, ',') !== false) {
            $id8025 = explode(',', $id8025);
        } else {
            $id8025 = [$id8025];
        }
        //是否包含产科代码
        if (in_array($brks, $id8025)) {
            return []; // 是产科，不进行质控
        }
        //获取brry
        $brry = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->get()->toArray();
        if (empty($brry)) {
            return true;
        }
        $brry = $brry[0];
        //获取入院时间AAB01和出院时间AAC01，如果出院时间为空取当前时间
        $enterTime = $brry['AAB01'];
        $exitTime = $brry['AAC01'];
        if (empty($exitTime)) {
            $exitTime = Carbon::now()->toDateTimeString();
            $exitTime = date('Y-m-d 23:59:59', strtotime($exitTime));
        }
        //如果入院时间为空就不质控
        if (empty($enterTime)) {
            return true;
        }
        //计算出院时间-入院时间是否大于4天，只计算日，当天不算
        //取到日期的年月日
        $enterDate = date('Y-m-d', strtotime($enterTime));
        $exitDate = date('Y-m-d', strtotime($exitTime));
        $diffDays = (strtotime($exitDate) - strtotime($enterDate)) / (24 * 3600);
        if ($diffDays < 4) {
            return true;
        }

        $rule8083 = RuleWordMap::query()->where('id', 8083)->value('keyword');
        $rule8083 = !empty($rule8083) ? $rule8083 : '50,296';
        if (strpos($rule8083, ',') !== false) {
            $rule8083 = explode(',', $rule8083);
        } else {
            $rule8083 = [$rule8083];
        }

        $rule8084 = RuleWordMap::query()->where('id', 8084)->value('keyword');
        $rule8084 = !empty($rule8084) ? $rule8084 : '主任';
        $rule8085 = RuleWordMap::query()->where('id', 8085)->value('keyword');
        $rule8085 = !empty($rule8085) ? $rule8085 : '副主任';
        $rule8086 = RuleWordMap::query()->where('id', 8086)->value('keyword');
        $rule8086 = !empty($rule8086) ? $rule8086 : '主治';


        $basisList = [];
        $basis = [];
        //天数向上取整
        $diffDays = ceil($diffDays);
        $basis[] = '当前住院天数【' . $diffDays . '天】';
        //需排除的工号
        $delgh = [];
        //高级职称数量
        $gjzc = 0;
        //中级职称数量
        $zjzc = 0;
        //三级职称数量
        $sjzc = 0;
        //获取所有病程记录
        $BCJL = EMR_BL_BL01::query()->where('JZHM', '=', $ZYH)->whereIn('MBLB', $rule8083)->get()->toArray();
        if (empty($BCJL)) {
            return true;
        }
        foreach ($BCJL as $item) {
            //根据BLBH查询BLSY
            $BLSY = EMR_BL_BLSY::query()->where('BLBH', '=', $item['BLBH'])->where('FG_ACTIVE', 1)->groupBy('SYYS')->get()->toArray();
            if (empty($BLSY)) {
                continue;
            }
            foreach ($BLSY as $item2) {
                //根据syys关联staff的code，取name
                $code = $item2['SYYS'];
                $staff = Staff::query()->where('code', '=', $code)->get()->toArray();
                if (empty($staff)) {
                    continue;
                }
                $staff = $staff[0];
                $name = $staff['name'];
                //查看item['BLMC']是否包含name
                $blmc = $item['BLMC'];
                if (strpos($blmc, $name) !== false) {
                    //再看blmc是否包含8084，8085，8086
                    if (strpos($blmc, $rule8085) !== false) {
                        $gjzc++;
                        $basis[] = '【' . $name . '/' . $code . '】职称：' . $rule8085;
                        //添加到排除的工号
                        $delgh[] = $code;
                    } else if (strpos($blmc, $rule8084) !== false) {
                        $gjzc++;
                        $basis[] = '【' . $name . '/' . $code . '】职称：' . $rule8084;
                        //添加到排除的工号
                        $delgh[] = $code;
                    } else if (strpos($blmc, $rule8086) !== false) {
                        $zjzc++;
                        $basis[] = '【' . $name . '/' . $code . '】职称：' . $rule8086;
                        //添加到排除的工号
                        $delgh[] = $code;
                    }
                }
            }
        }

        $num = $gjzc + $zjzc + $sjzc;
        if ($gjzc >= 1 && $num >= 3) {
            return true;
        }
        $blsy_list = EMR_BL_BLSY::query()->leftJoin('EMR_BL_BL01', 'EMR_BL_BLSY.BLBH', '=', 'EMR_BL_BL01.BLBH')->where('FG_ACTIVE', 1)->where('EMR_BL_BL01.JZHM', '=', $ZYH)->whereIn('EMR_BL_BL01.MBLB', $rule8083)->whereNotIn('EMR_BL_BLSY.SYYS', $delgh)->groupBy('EMR_BL_BLSY.SYYS')->get()->toArray();
        if ($gjzc >= 1 && $num >= 2) {
            $blsy_num = count($blsy_list);
            if ($blsy_num > 0) {
                return true;
            }
        }

        foreach ($blsy_list as $item) {
            $code = $item['SYYS'];
            $staff = Staff::query()->where('code', '=', $code)->get()->toArray();
            if (empty($staff)) {
                continue;
            }
            $staff = $staff[0];
            $name = $staff['name'];
            $ygjb = $staff['ygjb_text'];

            if (strpos($ygjb, $rule8085) !== false) {
                $gjzc++;
                $basis[] = '【' . $name . '/' . $code . '】职称：' . $rule8085;
            } else if (strpos($ygjb, $rule8084) !== false) {
                $gjzc++;
                $basis[] = '【' . $name . '/' . $code . '】职称：' . $rule8084;
            } else if (strpos($ygjb, $rule8086) !== false) {
                $zjzc++;
                $basis[] = '【' . $name . '/' . $code . '】职称：' . $rule8086;
            } else {
                $sjzc++;
                $basis[] = '【' . $name . '/' . $code . '】职称：' . $ygjb;
            }
        }
        $num = $gjzc + $zjzc + $sjzc;
        if ($gjzc >= 1 && ($zjzc >= 1 || $gjzc >= 2) && $num >= 3) {
            return true;
        } else {
            $basisList[] = $basis;
        }


        if (!empty($basisList)) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, JSON_UNESCAPED_UNICODE),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }

        return [];
    }

    public function rule1219($caseRule = [], $ruleId, $ZYH = "")
    {
        //获取brry
        $brry = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->get()->toArray();
        if (empty($brry)) {
            return true;
        }

        //rule8087
        $rule8087 = RuleWordMap::query()->where('id', 8087)->value('keyword');
        $rule8087 = !empty($rule8087) ? $rule8087 : '50';
        if (strpos($rule8087, ',') !== false) {
            $rule8087 = explode(',', $rule8087);
        } else {
            $rule8087 = [$rule8087];
        }

        $rule8084 = RuleWordMap::query()->where('id', 8084)->value('keyword');
        $rule8084 = !empty($rule8084) ? $rule8084 : '主任';
        $rule8085 = RuleWordMap::query()->where('id', 8085)->value('keyword');
        $rule8085 = !empty($rule8085) ? $rule8085 : '副主任';
        $rule8086 = RuleWordMap::query()->where('id', 8086)->value('keyword');
        $rule8086 = !empty($rule8086) ? $rule8086 : '主治';

        $basisList = [];
        //从MySQL获取所有病程记录
        $newData = EMR_BL_BL01::query()
            ->where('JZHM', $ZYH)
            ->whereIn('MBLB', $rule8087)
            ->get()->toArray();
        if (empty($newData) || empty($newData[0])) {
            return [];
        }


        foreach ($newData as $item) {
            $iszc = false;
            $blmc = $item['BLMC'];
            $zcmc = '';
            if (strpos($blmc, $rule8084) !== false) {
                $iszc = true;
            } else if (strpos($blmc, $rule8085) !== false) {
                $iszc = true;
            } else if (strpos($blmc, $rule8086) !== false) {
                $iszc = true;
            }

            if (!$iszc) {
                continue;
            }

            //提取医生姓名，示例 【2025-01-03 12:32:00 张三主任医师查房记录】，提取张三 职称是zcmc
            $doctorName = '';
            // 查找职称在字符串中的位置
            /* $zcmcPos = strpos($blmc, $zcmc);
            if ($zcmcPos !== false) {
                // 从职称位置向前查找第一个汉字的开始位置
                for ($i = $zcmcPos - 1; $i >= 0; $i--) {
                    $char = mb_substr($blmc, $i, 1, 'UTF-8');
                    // 如果是汉字，继续向前查找
                    if (preg_match('/[\x{4e00}-\x{9fa5}]/u', $char)) {
                        continue;
                    } else {
                        // 找到非汉字字符，汉字从下一个位置开始
                        $doctorName = mb_substr($blmc, $i + 1, $zcmcPos - $i - 1, 'UTF-8');
                        break;
                    }
                }
                // 如果从头开始都是汉字
                if (empty($doctorName) && $i < 0) {
                    $doctorName = mb_substr($blmc, 0, $zcmcPos, 'UTF-8');
                }
            } */
            preg_match("/(\d{4}年\d{2}月\d{2}日|\d{4}年\d{2}月\d{2}|\d{4}-\d{2}-\d{2})+\s+(\d{2}:\d{2}(:\d{2})?)\s+(.*?)(副主任|科主任|主治|主任)+/", $blmc, $timeMatches);

            $doctorName = $timeMatches[4] ?? '';
            if (empty($doctorName)) {
                continue;
            }

            $hjnr = $item['HJNR'];

            //hjnr中是否包含doctorname
            if (strpos($hjnr, $doctorName) === false) {
                $basis = [];
                $basis[] = '病历标题【' . $blmc . '】';
                $basis[] = '标题中医师姓名【' . $doctorName . '】，病程记录中未记录';
                $basisList[] = $basis;
            }
        }


        if (!empty($basisList)) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, JSON_UNESCAPED_UNICODE),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }

        return [];
    }

    public function rule1220($caseRule = [], $ruleId, $ZYH = "")
    {
        //获取brry
        $brry = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->get()->toArray();
        if (empty($brry)) {
            return true;
        }

        //rule8087
        $rule8087 = RuleWordMap::query()->where('id', 8087)->value('keyword');
        $rule8087 = !empty($rule8087) ? $rule8087 : '50';
        if (strpos($rule8087, ',') !== false) {
            $rule8087 = explode(',', $rule8087);
        } else {
            $rule8087 = [$rule8087];
        }

        $rule8084 = RuleWordMap::query()->where('id', 8084)->value('keyword');
        $rule8084 = !empty($rule8084) ? $rule8084 : '主任';
        $rule8085 = RuleWordMap::query()->where('id', 8085)->value('keyword');
        $rule8085 = !empty($rule8085) ? $rule8085 : '副主任';
        $rule8086 = RuleWordMap::query()->where('id', 8086)->value('keyword');
        $rule8086 = !empty($rule8086) ? $rule8086 : '主治';

        $basisList = [];
        //从MySQL获取所有病程记录
        $newData = EMR_BL_BL01::query()
            ->where('JZHM', $ZYH)
            ->whereIn('MBLB', $rule8087)
            ->get()->toArray();
        if (empty($newData) || empty($newData[0])) {
            return [];
        }


        foreach ($newData as $item) {
            $iszc = false;
            $blmc = $item['BLMC'];
            $zcmc = '';
            if (strpos($blmc, $rule8084) !== false) {
                $iszc = true;
                $zcmc = $rule8084;
            } else if (strpos($blmc, $rule8085) !== false) {
                $iszc = true;
                $zcmc = $rule8085;
            } else if (strpos($blmc, $rule8086) !== false) {
                $iszc = true;
                $zcmc = $rule8086;
            } else if (strpos($blmc, '住院医师') !== false) {
                $iszc = true;
                $zcmc = '住院医师';
            }

            if (!$iszc) {
                $basis = [];
                $basis[] = '病历标题【' . $blmc . '】';
                $basis[] = '医师职称【无】';
                $basisList[] = $basis;
            }
        }


        if (!empty($basisList)) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, JSON_UNESCAPED_UNICODE),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }

        return [];
    }

    public function rule1221($caseRule = [], $ruleId, $ZYH = "")
    {
        //获取brry
        $brry = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->get()->toArray();
        if (empty($brry)) {
            return true;
        }

        $rule8089 = RuleWordMap::query()->where('id', 8089)->value('keyword');
        $rule8089 = !empty($rule8089) ? $rule8089 : '294';
        if (strpos($rule8089, ',') !== false) {
            $rule8089 = explode(',', $rule8089);
        } else {
            $rule8089 = [$rule8089];
        }

        $basisList = [];

        $BCJL = EMR_BL_BL01::query()->where('JZHM', '=', $ZYH)->whereIn('BLLB', $rule8089)->get()->toArray();
        if (empty($BCJL)) {
            return true;
        }


        foreach ($BCJL as $item) {
            $blmc = $item['BLMC'];
            $blbh = $item['BLBH'];
            if (strpos($blmc, '产后记录') !== false) {
                continue;
            }

            $blsy = EMR_BL_BLSY::query()->where('BLBH', '=', $blbh)->where('FG_ACTIVE', 1)->groupBy('SYYS')->get()->toArray();
            if (empty($blsy)) {
                $basis = [];
                $basis[] = '病历标题【' . $blmc . '】，未签名';
                $basisList[] = $basis;
            }
        }

        if (!empty($basisList)) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, JSON_UNESCAPED_UNICODE),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }

        return [];
    }

    public function rule1222($caseRule = [], $ruleId, $ZYH = "")
    {
        //获取brry
        $brry = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->get()->toArray();
        if (empty($brry)) {
            return true;
        }

        $rule8090 = RuleWordMap::query()->where('id', 8090)->value('keyword');
        $rule8090 = !empty($rule8090) ? $rule8090 : '44';
        if (strpos($rule8090, ',') !== false) {
            $rule8090 = explode(',', $rule8090);
        } else {
            $rule8090 = [$rule8090];
        }

        $basisList = [];

        $BCJL = EMR_BL_BL01::query()->where('JZHM', '=', $ZYH)->whereIn('MBLB', $rule8090)->get()->toArray();
        if (empty($BCJL)) {
            return true;
        }


        foreach ($BCJL as $item) {
            $blmc = $item['BLMC'];
            $blbh = $item['BLBH'];

            $blsy = EMR_BL_BLSY::query()->where('BLBH', '=', $blbh)->where('FG_ACTIVE', 1)->groupBy('SYYS')->get()->toArray();
            if (empty($blsy)) {
                $basis = [];
                $basis[] = '病历标题【' . $blmc . '】，未签名';
                $basisList[] = $basis;
            }
        }

        if (!empty($basisList)) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, JSON_UNESCAPED_UNICODE),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }

        return [];
    }

    public function rule1223($caseRule = [], $ruleId, $ZYH = "")
    {
        //获取brry
        $brry = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->get()->toArray();
        if (empty($brry)) {
            return true;
        }

        $rule8090 = RuleWordMap::query()->where('id', 8090)->value('keyword');
        $rule8090 = !empty($rule8090) ? $rule8090 : '44';
        if (strpos($rule8090, ',') !== false) {
            $rule8090 = explode(',', $rule8090);
        } else {
            $rule8090 = [$rule8090];
        }

        $basisList = [];
        //从MySQL获取病程记录
        $newData = EMR_BL_BL01::query()
            ->where('JZHM', $ZYH)
            ->whereIn('MBLB', $rule8090)
            ->get()->toArray();
        if (empty($newData) || empty($newData[0])) {
            return true;
        }

        $basisList = [];

        $BCJL = $newData;
        if (empty($BCJL)) {
            return true;
        }

        foreach ($BCJL as $item) {
            $blmc = $item['BLMC'];
            $hjnr = $item['HJNR'];
            $zxsj = $item['ZXSJ'];

            if (empty($zxsj)) {
                continue;
            }

            //从hjnr从提取讨论时间，在最前面格式：2025-10-09 12:00，hjnr示例：2025-10-09 12:00，123123
            $ztsj = '';
            if (preg_match('/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2})/', $hjnr, $matches)) {
                $ztsj = $matches[1];
            }

            if (empty($ztsj)) {
                continue;
            }

            //如果ztsj > zxsj
            if (strtotime($ztsj) > strtotime($zxsj)) {
                $basis = [];
                $basis[] = '病历标题【' . $blmc . '】';
                $basis[] = '标题时间【' . $zxsj . '】';
                $basis[] = '讨论时间【' . $ztsj . '】';
                $basis[] = '标题时间【' . $zxsj . '】，标题时间应晚于讨论时间';
                $basisList[] = $basis;
            }
        }

        if (!empty($basisList)) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, JSON_UNESCAPED_UNICODE),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }


        return [];
    }

    public function rule1224($caseRule = [], $ruleId, $ZYH = "")
    {
        //获取brry
        $brry = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->get()->toArray();
        if (empty($brry)) {
            return true;
        }

        $rule8091 = RuleWordMap::query()->where('id', 8091)->value('keyword');
        $rule8091 = !empty($rule8091) ? $rule8091 : '会诊记录';
        if (strpos($rule8091, ',') !== false) {
            $rule8091 = explode(',', $rule8091);
        } else {
            $rule8091 = [$rule8091];
        }

        $basisList = [];

        //从MySQL获取病程记录
        $bl01Query = EMR_BL_BL01::query()
            ->where('JZHM', $ZYH)
            ->where('MBLB', 294)->get()->toArray();
        $newData = [];
        foreach ($bl01Query as $record) {
            foreach ($rule8091 as $v) {
                if (strpos($record['BLMC'], $v) !== false) {
                    $newData[] = $record;
                    break;
                }
            }
        }
        if (empty($newData) || empty($newData[0])) {
            return true;
        }

        $hzyj = YS_ZY_HZYJ::query()->leftJoin('YS_ZY_HZSQ', 'YS_ZY_HZYJ.SXDH', '=', 'YS_ZY_HZSQ.SXDH')->where('YS_ZY_HZSQ.JZHM', '=', $ZYH)->groupBy('YS_ZY_HZYJ.SSYS')->get()->toArray();
        foreach ($newData as $item) {
            $blmc = $item['BLMC'];
            $hjnr = $item['HJNR'];
            $basis = [];
            $basis[] = '病历标题【' . $blmc . '】';
            $isys = false;
            foreach ($hzyj as $hzyjItem) {
                $doctorname = Staff::query()->where('code', '=', $hzyjItem['SSYS'])->get()->toArray();
                if (empty($doctorname)) {
                    continue;
                }
                $doctorname = $doctorname[0]['name'];
                if (strpos($hjnr, $doctorname) === false) {
                    $basis[] = '申请单签名医师【' . $doctorname . '】';
                } else {
                    $isys = true;
                    break;
                }
            }

            if (!$isys) {
                $basisList[] = $basis;
            }
        }

        if (!empty($basisList)) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, JSON_UNESCAPED_UNICODE),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }

        return [];
    }

    /**
     * 规则1225：会诊记录病程中会诊医师姓名、职称与会诊申请单中会诊医师不一致
     * @param array $caseRule
     * @param int $ruleId
     * @param string $ZYH
     * @return array
     */
    public function rule1225($caseRule = [], $ruleId, $ZYH = "")
    {
        //获取brry
        $brry = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->get()->toArray();
        if (empty($brry)) {
            return true;
        }

        // 获取会诊记录关键词配置
        $rule8091 = RuleWordMap::query()->where('id', 8091)->value('keyword');
        $rule8091 = !empty($rule8091) ? $rule8091 : '会诊记录';
        if (strpos($rule8091, ',') !== false) {
            $rule8091 = explode(',', $rule8091);
        } else {
            $rule8091 = [$rule8091];
        }

        $basisList = [];

        // 从MySQL查询会诊记录（MBLB=294的病程记录）
        $bl01Query = EMR_BL_BL01::query()
            ->where('JZHM', $ZYH)
            ->where('MBLB', 294)->get()->toArray();
        $newData = [];
        foreach ($bl01Query as $record) {
            foreach ($rule8091 as $v) {
                if (strpos($record['BLMC'], $v) !== false) {
                    $newData[] = $record;
                    break;
                }
            }
        }
        if (empty($newData) || empty($newData[0])) {
            return true;
        }

        foreach ($newData as $item) {
            $blmc = $item['BLMC'];
            $hjnr = $item['HJNR'];
            $blbh = $item['BLBH'];
            $basis = [];

            // 使用正则表达式提取【经×××医师同意】中的医师姓名
            // 职称可能是：科主任、副主任、主任、主治，后面可能带"医师"也可能不带
            // 示例：经王睿主任医师同意 或 经王睿主任同意，提取"王睿"
            $pattern = '/经(.+?)(科主任|副主任|主任|主治)(?:医师)?\s*同意/u';
            preg_match_all($pattern, $hjnr, $matches);

            if (empty($matches[1])) {
                // 如果没有匹配到医师，不进行检查
                continue;
            }

            // 获取所有匹配到的医师姓名
            $doctorNames = array_unique($matches[1]);

            $basis[] = '病历标题【' . $blmc . '】';
            $hasInconsistency = false;

            foreach ($doctorNames as $doctorName) {
                $doctorName = trim($doctorName);
                if (empty($doctorName)) {
                    continue;
                }

                // 根据医师姓名从staff表获取code
                $staffInfo = Staff::query()->where('name', '=', $doctorName)->first();
                if (empty($staffInfo)) {
                    continue;
                }

                $doctorCode = $staffInfo->code;

                // 查询blsy表，根据blbh获取签名医师列表
                $blsyRecords = EMR_BL_BLSY::query()
                    ->where('BLBH', '=', $blbh)
                    ->pluck('SYYS')
                    ->toArray();

                // 检查该医师code是否在blsy的签名医师列表中
                if (!in_array($doctorCode, $blsyRecords)) {
                    $basis[] = '病程记录中会诊医师【' . $doctorName . '】未签名';
                    $hasInconsistency = true;
                }
            }

            // 如果存在不一致，添加到结果列表
            if ($hasInconsistency) {
                $basisList[] = $basis;
            }
        }

        if (!empty($basisList)) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, JSON_UNESCAPED_UNICODE),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }

        return [];
    }

    /**
     * 规则1226：抢救记录--标题时间早于抢救结束时间
     * @param array $caseRule
     * @param int $ruleId
     * @param string $ZYH
     * @return array
     */
    public function rule1226($caseRule = [], $ruleId, $ZYH = "")
    {
        $allBasisGroups = [];

        try {
            // 1. 配置检索 - 抢救记录的MBLB配置
            $mblbRescueRecordRaw = RuleWordMap::query()->where('id', '=', 8027)->value('keyword');
            $mblbRescueRecordConfig = !empty($mblbRescueRecordRaw) ? $mblbRescueRecordRaw : '27';
            if (strpos($mblbRescueRecordConfig, ',') !== false) {
                $mblbRescueRecordTypes = explode(',', $mblbRescueRecordConfig);
            } else {
                $mblbRescueRecordTypes = [$mblbRescueRecordConfig];
            }

            // 标题时间字段配置
            $zxsjField = RuleWordMap::query()->where('id', '=', 8011)->value('keyword');
            $zxsjField = !empty($zxsjField) ? $zxsjField : 'ZXSJ';

            // 2. 查询EMR_BL_BL01抢救记录
            $bl01Records = EMR_BL_BL01::query()
                ->where('JZHM', $ZYH)
                ->whereIn('MBLB', $mblbRescueRecordTypes)
                ->get()
                ->toArray();

            if (empty($bl01Records)) {
                return true;
            }

            foreach ($bl01Records as $bl01Record) {
                $recordBasis = [];
                $blmc = $bl01Record['BLMC'] ?? '未知病历';
                $blbh = $bl01Record['BLBH'] ?? null;
                $zxsj_datetime_str = $bl01Record[$zxsjField] ?? null;

                // 添加抢救记录信息
                $recordBasis[] = "抢救记录【{$blmc}】";

                // 如果没有标题时间，跳过
                if (empty($zxsj_datetime_str)) {
                    continue;
                }

                // 解析抢救结束时间
                $parsedRescueEndDateTimeStr = null;
                $parsedRescueEndObj = null;

                if ($blbh && $zxsj_datetime_str) {
                    try {
                        $hjnr = EMR_BL_BLXG::query()->where('BLBH', $blbh)->value('HJNR');
                        $match_datetime_str = '';
                        if ($hjnr) {
                            // 统一匹配 YYYY-MM-DD HH:MM 格式，保留多种关键词
                            $keywords = [
                                '抢救成功',
                                '病情',
                                '血压',
                                '测血压',
                                '使用',
                                '氧',
                                '仍无自主呼吸心跳',
                                '心电图示直线',
                                '临床死亡',
                                '宣布',
                                '转入',
                                '死亡',
                                '出现意识不清',
                                '心电图示无心电',
                                '呼吸及血压'
                            ];

                            // 从标题时间中提取年份，用于拼接短格式日期
                            $year = date('Y', strtotime($zxsj_datetime_str));

                            foreach ($keywords as $keyword) {
                                // 匹配格式：于YYYY-MM-DD HH:MM[:SS] + 关键词（秒为可选参数）
                                if (preg_match('/于\s*(\d{4}-\d{1,2}-\d{1,2}\s+\d{1,2}:\d{1,2}(?::\d{1,2})?)\s*' . preg_quote($keyword, '/') . '/', $hjnr, $matches)) {
                                    $match_datetime_str = $matches[1];
                                    break;
                                }
                            }

                            // 匹配格式：于MM-DD HH时MM分 + 关键词（年份从zxsj中提取）
                            if (empty($match_datetime_str)) {
                                foreach ($keywords as $keyword) {
                                    if (preg_match('/于\s*(\d{2}-\d{2})\s+(\d{1,2})时(\d{1,2})分\s*' . preg_quote($keyword, '/') . '/', $hjnr, $matches)) {
                                        $monthDay = $matches[1];
                                        $hour = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
                                        $minute = str_pad($matches[3], 2, '0', STR_PAD_LEFT);
                                        $match_datetime_str = "{$year}-{$monthDay} {$hour}:{$minute}";
                                        break;
                                    }
                                }
                            }

                            // 匹配抢救结束时间字段格式（秒为可选参数）
                            if (empty($match_datetime_str)) {
                                if (preg_match('/抢救结束时间[：:]\s*(\d{4}-\d{1,2}-\d{1,2}\s+\d{1,2}:\d{1,2}(?::\d{1,2})?)/', $hjnr, $matches)) {
                                    $match_datetime_str = $matches[1];
                                }
                            }

                            if ($match_datetime_str) {
                                $parsedRescueEndDateTimeStr = $match_datetime_str;
                            }
                        }
                    } catch (\Exception $e) {
                        Log::error("rule-{$ruleId}-{$ZYH}: Error fetching HJNR for BLBH {$blbh}: " . $e->getMessage());
                    }
                }

                // 如果没有解析到抢救结束时间，跳过
                if (empty($parsedRescueEndDateTimeStr)) {
                    continue;
                }

                $parsedRescueEndObj = strtotime($parsedRescueEndDateTimeStr);

                // 比较标题时间和抢救结束时间
                $zxsj_obj = strtotime($zxsj_datetime_str);

                // 如果标题时间早于抢救结束时间，记录质控问题
                if ($zxsj_obj < $parsedRescueEndObj) {
                    $recordBasis[] = "标题时间【{$zxsj_datetime_str}】";
                    $recordBasis[] = "抢救结束时间【{$parsedRescueEndDateTimeStr}】";
                    $allBasisGroups[] = $recordBasis;
                }
            }

            // 将所有质控结果一次性插入到insertData
            if (!empty($allBasisGroups)) {
                $this->insertData[] = [
                    'JZHM' => $ZYH,
                    'rule_id' => $ruleId,
                    'code' => 'rule_' . $ruleId,
                    'error_field' => $caseRule[$ruleId]['title'],
                    'basis' => json_encode($allBasisGroups, JSON_UNESCAPED_UNICODE)
                ];
            }

            return [];
        } catch (\Exception $e) {
            Log::error("rule-{$ruleId}-{$ZYH}: Exception: " . $e->getMessage());
            return [];
        }
    }

    /**
     * 规则1228：疑难病例讨论结论记录--无主持人签名（病区主任签名）
     * 查询bllb包含8090的，先按照blbh获取所有签名，循环去staff表查询name
     * 然后取出内容中【在。。。。主持下】这句话，看看是否包含这个医师
     * 如果包含就是满足，如果不包含就质控出来
     */
    public function rule1228($caseRule = [], $ruleId, $ZYH = "")
    {
        //获取brry
        $brry = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->get()->toArray();
        if (empty($brry)) {
            return true;
        }

        // 获取疑难病例讨论结论记录的病历类别配置（8090对应的是MBLB，通常是44）
        $rule8090 = RuleWordMap::query()->where('id', 8090)->value('keyword');
        $rule8090 = !empty($rule8090) ? $rule8090 : '44';
        if (strpos($rule8090, ',') !== false) {
            $rule8090 = explode(',', $rule8090);
        } else {
            $rule8090 = [$rule8090];
        }

        $basisList = [];

        // 从MySQL查询疑难病例讨论结论记录
        $newData = EMR_BL_BL01::query()
            ->where('JZHM', $ZYH)
            ->whereIn('MBLB', $rule8090)
            ->get()->toArray();
        if (empty($newData) || empty($newData[0])) {
            return true;
        }

        $BCJL = $newData;
        if (empty($BCJL)) {
            return true;
        }

        foreach ($BCJL as $item) {
            $blmc = $item['BLMC'];
            $blbh = $item['BLBH'];
            $hjnr = $item['HJNR'] ?? '';

            if (empty($hjnr)) {
                continue;
            }

            // 按照blbh获取所有签名
            $blsy = EMR_BL_BLSY::query()
                ->where('BLBH', '=', $blbh)
                ->where('FG_ACTIVE', 1)
                ->get()
                ->toArray();

            if (empty($blsy)) {
                continue;
            }

            // 从病历内容中提取【在...主持下】这句话中的主持人姓名
            // 匹配模式：在XX主持下 或 在XXX主持下
            $pattern = '/在(.{2,10}?)主持下/u';
            preg_match($pattern, $hjnr, $matches);

            if (empty($matches[1])) {
                // 如果没有找到主持人信息，跳过
                continue;
            }

            $hostName = trim($matches[1]); // 主持人姓名

            // 循环查询所有签名医师的姓名
            $signerNames = [];
            foreach ($blsy as $syItem) {
                $syys = $syItem['SYYS']; // 书以医师工号
                if (empty($syys)) {
                    continue;
                }

                // 从staff表查询医师姓名
                $staffInfo = Staff::query()
                    ->where('code', '=', $syys)
                    ->first();

                if (!empty($staffInfo) && !empty($staffInfo->name)) {
                    $signerNames[] = $staffInfo->name;
                }
            }

            // 检查主持人姓名是否在签名列表中
            $isHostSigned = false;
            foreach ($signerNames as $signerName) {
                // 使用stripos进行不区分大小写的包含判断
                if (strpos($hostName, $signerName) !== false || strpos($signerName, $hostName) !== false) {
                    $isHostSigned = true;
                    break;
                }
            }

            // 如果主持人没有签名，则质控出来
            if (!$isHostSigned) {
                $basis = [];
                $basis[] = '病历标题【' . $blmc . '】';
                $basis[] = '主持人【' . $hostName . '】';
                $basis[] = '已签名医师【' . implode('、', $signerNames) . '】';
                $basis[] = '病历内容中的主持人【' . $hostName . '】未在签名列表中';
                $basisList[] = $basis;
            }
        }

        if (!empty($basisList)) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, JSON_UNESCAPED_UNICODE),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }

        return [];
    }

    /**
     * 规则1229：抢救记录--由未参加抢救的医师书写
     * 取mblb 8027的hjnr，取抢救者之后的文本，如果为空就跳过
     * 然后根据blbh查询所有签名，在staff表查name，查看签名医师是否在抢救者名单中
     */
    public function rule1229($caseRule = [], $ruleId, $ZYH = "")
    {
        //获取brry
        $brry = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->get()->toArray();
        if (empty($brry)) {
            return true;
        }

        // 获取抢救记录的病历类别配置（8027对应的是MBLB，通常是27）
        $rule8027 = RuleWordMap::query()->where('id', 8027)->value('keyword');
        $rule8027 = !empty($rule8027) ? $rule8027 : '27';
        if (strpos($rule8027, ',') !== false) {
            $rule8027 = explode(',', $rule8027);
        } else {
            $rule8027 = [$rule8027];
        }

        $basisList = [];

        // 查询抢救记录
        $BCJL = EMR_BL_BL01::query()
            ->where('JZHM', '=', $ZYH)
            ->whereIn('MBLB', $rule8027)
            ->get()
            ->toArray();

        if (empty($BCJL)) {
            return true;
        }

        foreach ($BCJL as $item) {
            $blmc = $item['BLMC'];
            $blbh = $item['BLBH'];

            // 从EMR_BL_BLXG表获取病历内容
            $hjnr = EMR_BL_BLXG::query()->where('BLBH', '=', $blbh)->value('HJNR');

            if (empty($hjnr)) {
                continue;
            }

            // 提取抢救者/参与人员名单
            // 查找【参与主任医师】、【参与】、【抢救者】等标记之后的文本
            $rescuersText = '';
            $rescuersNames = [];

            if (preg_match('/抢救者[：:](.*?)(?:\n|。|$)/us', $hjnr, $matches)) {
                $rescuersText = $matches[1];
            }

            // 如果没有找到抢救者信息，跳过
            if (empty($rescuersText)) {
                continue;
            }

            //去除所有空格和 半角全角空格
            $rescuersText = str_replace([' ', ' '], '', $rescuersText);
            // 从抢救者文本中提取所有人名
            // 第一步：匹配所有"姓名+职称"的组合
            // 注意：使用贪婪匹配{2,4}而不是{2,4}?，确保匹配到完整的姓名而不是职称的一部分
            preg_match_all('/([一-龥]{2,4})(?:副主任医师|主任医师|主治医师|住院医师|医师|护师|主管护师|护士长|护士)/u', $rescuersText, $nameMatches);

            $rescuersNames = [];
            if (!empty($nameMatches[1])) {
                // 过滤掉明显是职称的词语（如"副主任"、"住院"等）
                $titleKeywords = ['副主任', '主任', '主治', '住院', '主管', '护士长', '医师', '护师', '护士'];
                foreach ($nameMatches[1] as $name) {
                    // 如果提取的不是职称关键词，才加入名单
                    if (!empty($name) && !in_array($name, $titleKeywords)) {
                        $rescuersNames[] = $name;
                    }
                }
                $rescuersNames = array_unique($rescuersNames);
            }

            // 第二步：匹配"姓名、姓名职称"或"姓名，姓名职称"格式，提取第一个姓名
            // 匹配模式：2-4个中文字符 + 分隔符（顿号、逗号） + 2-4个中文字符 + 职称
            preg_match_all('/([一-龥]{2,4})[、，,]([一-龥]{2,4})(?:副主任医师|主任医师|主治医师|住院医师|医师|护师|主管护师|护士长|护士)/u', $rescuersText, $commaMatches);

            if (!empty($commaMatches[1])) {
                // 提取分隔符前的姓名（如"王保强、王雪萌主治医师"中的"王保强"）
                foreach ($commaMatches[1] as $name) {
                    if (!empty($name)) {
                        $rescuersNames[] = $name;
                    }
                }
            }

            if (!empty($rescuersNames)) {
                $rescuersNames = array_unique($rescuersNames);
            }

            // 如果没有提取到抢救人员名单，跳过
            if (empty($rescuersNames)) {
                continue;
            }

            // 按照blbh获取所有签名
            $blsy = EMR_BL_BLSY::query()
                ->where('BLBH', '=', $blbh)
                ->where('FG_ACTIVE', 1)
                ->get()
                ->toArray();

            if (empty($blsy)) {
                continue;
            }

            // 循环查询所有签名医师的姓名
            $signerNames = [];

            foreach ($blsy as $syItem) {
                $syys = $syItem['SYYS']; // 书以医师工号
                if (empty($syys)) {
                    continue;
                }

                // 从staff表查询医师姓名
                $staffInfo = Staff::query()
                    ->where('code', '=', $syys)
                    ->first();

                if (!empty($staffInfo) && !empty($staffInfo->name)) {
                    $signerName = $staffInfo->name;
                    $signerNames[] = $signerName;

                    // 检查签名医师是否在抢救者名单中
                    $isInRescuers = false;
                    foreach ($rescuersNames as $rescuerName) {
                        // 使用包含匹配，因为可能存在姓名格式差异
                        if (strpos($rescuerName, $signerName) !== false || strpos($signerName, $rescuerName) !== false) {
                            $isInRescuers = true;
                            break;
                        }
                    }
                    if ($isInRescuers) {
                        break;
                    }
                }
            }

            // 如果存在未参加抢救但签名的医师，则质控出来
            if (!$isInRescuers) {
                $basis = [];
                $basis[] = '病历标题【' . $blmc . '】';
                $basis[] = '抢救参与人员【' . implode('、', $rescuersNames) . '】';
                $basis[] = '已签名医师【' . implode('、', $signerNames) . '】';
                $basisList[] = $basis;
            }
        }

        if (!empty($basisList)) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, JSON_UNESCAPED_UNICODE),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }

        return [];
    }

    /**
     * 规则1230：抢救记录--无主持抢救的医师审核签名（默认职称最高的医师、抢救者中第一位医师）
     * 提取"抢救者："到第一个逗号或者顿号，然后查看是否在签名中的name
     */
    public function rule1230($caseRule = [], $ruleId, $ZYH = "")
    {
        //获取brry
        $brry = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->get()->toArray();
        if (empty($brry)) {
            return true;
        }

        // 获取抢救记录的病历类别配置（8027对应的是MBLB，通常是27）
        $rule8027 = RuleWordMap::query()->where('id', 8027)->value('keyword');
        $rule8027 = !empty($rule8027) ? $rule8027 : '27';
        if (strpos($rule8027, ',') !== false) {
            $rule8027 = explode(',', $rule8027);
        } else {
            $rule8027 = [$rule8027];
        }

        $basisList = [];

        // 查询抢救记录
        $BCJL = EMR_BL_BL01::query()
            ->where('JZHM', '=', $ZYH)
            ->whereIn('MBLB', $rule8027)
            ->get()
            ->toArray();

        if (empty($BCJL)) {
            return true;
        }

        foreach ($BCJL as $item) {
            $blmc = $item['BLMC'];
            $blbh = $item['BLBH'];

            // 从EMR_BL_BLXG表获取病历内容
            $hjnr = EMR_BL_BLXG::query()->where('BLBH', '=', $blbh)->value('HJNR');

            if (empty($hjnr)) {
                continue;
            }

            // 提取"抢救者："后面到第一个逗号或顿号之前的内容（主持抢救的医师）
            $hostDoctorName = '';

            // 匹配"抢救者："或"抢救者:"后面的内容，取到第一个逗号或顿号
            if (preg_match('/抢救者[：:]\s*([^，,、]+)[，,、]/u', $hjnr, $matches)) {
                $hostDoctorName = trim($matches[1]);
            } elseif (preg_match('/抢救者[：:]\s*([^，,、\n。]+)/u', $hjnr, $matches)) {
                // 如果后面没有逗号或顿号，取到换行或句号
                $hostDoctorName = trim($matches[1]);
            }

            // 如果没有找到主持抢救医师，跳过
            if (empty($hostDoctorName)) {
                continue;
            }

            // 清理职称信息，只保留姓名
            // 移除常见职称后缀
            $hostDoctorName = preg_replace('/(副主任医师|主任医师|主治医师|住院医师|医师|护士长|主管护师|护师|护士)$/u', '', $hostDoctorName);
            $hostDoctorName = trim($hostDoctorName);

            // 如果清理后名字为空或太短，跳过
            if (empty($hostDoctorName) || mb_strlen($hostDoctorName, 'UTF-8') < 2) {
                continue;
            }

            // 按照blbh获取所有签名
            $blsy = EMR_BL_BLSY::query()
                ->where('BLBH', '=', $blbh)
                ->where('FG_ACTIVE', 1)
                ->get()
                ->toArray();

            if (empty($blsy)) {
                $basis = [];
                $basis[] = '病历标题【' . $blmc . '】';
                $basis[] = '主持抢救医师【' . $hostDoctorName . '】';
                $basis[] = '该病历未找到任何签名';
                $basisList[] = $basis;
                continue;
            }

            // 循环查询所有签名医师的姓名
            $signerNames = [];
            $isHostDoctorSigned = false;

            foreach ($blsy as $syItem) {
                $syys = $syItem['SYYS']; // 书以医师工号
                if (empty($syys)) {
                    continue;
                }

                // 从staff表查询医师姓名
                $staffInfo = Staff::query()
                    ->where('code', '=', $syys)
                    ->first();

                if (!empty($staffInfo) && !empty($staffInfo->name)) {
                    $signerName = $staffInfo->name;
                    $signerNames[] = $signerName;

                    // 检查主持抢救医师是否在签名列表中
                    // 使用包含匹配，因为可能存在姓名格式差异
                    if (strpos($hostDoctorName, $signerName) !== false || strpos($signerName, $hostDoctorName) !== false) {
                        $isHostDoctorSigned = true;
                    }
                }
            }

            // 如果主持抢救医师没有签名，则质控出来
            if (!$isHostDoctorSigned) {
                $basis = [];
                $basis[] = '病历标题【' . $blmc . '】';
                $basis[] = '主持抢救医师【' . $hostDoctorName . '】';
                $basis[] = '已签名医师【' . implode('、', $signerNames) . '】';
                $basis[] = '主持抢救医师【' . $hostDoctorName . '】未在签名列表中';
                $basisList[] = $basis;
            }
        }

        if (!empty($basisList)) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, JSON_UNESCAPED_UNICODE),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }

        return [];
    }

    /**
     * 规则1231：术前小结--术前讨论时间晚于标题时间或与标题时间一致
     * 查询术前小结，取hjnr中的最开始的时间，标题时间是ZXSJ
     * hjnr中的时间需要早于ZXSJ
     */
    public function rule1231($caseRule = [], $ruleId, $ZYH = "")
    {
        //获取brry
        $brry = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->get()->toArray();
        if (empty($brry)) {
            return true;
        }

        // 获取术前小结及术前讨论结论记录的配置（8016对应的是MBLB，通常是82,304）
        $rule8016 = RuleWordMap::query()->where('id', 8016)->value('keyword');
        $rule8016 = !empty($rule8016) ? $rule8016 : '82,304';
        if (strpos($rule8016, ',') !== false) {
            $rule8016 = explode(',', $rule8016);
        } else {
            $rule8016 = [$rule8016];
        }

        // 获取标题时间字段名（8011配置）
        $zxsjField = RuleWordMap::query()->where('id', 8011)->value('keyword');
        $zxsjField = !empty($zxsjField) ? $zxsjField : 'ZXSJ';

        $basisList = [];

        // 查询术前小结记录
        $BCJL = EMR_BL_BL01::query()
            ->where('JZHM', '=', $ZYH)
            ->whereIn('MBLB', $rule8016)
            ->get()
            ->toArray();

        if (empty($BCJL)) {
            return true;
        }

        foreach ($BCJL as $item) {
            $blmc = $item['BLMC'];
            $blbh = $item['BLBH'];
            $zxsj = $item[$zxsjField] ?? ''; // 标题时间

            // 如果标题时间为空，跳过
            if (empty($zxsj)) {
                continue;
            }

            // 从EMR_BL_BLXG表获取病历内容
            $hjnr = EMR_BL_BLXG::query()->where('BLBH', '=', $blbh)->value('HJNR');
            //blmc只保留汉字
            $blmc1 = preg_replace('/[^\\x{4e00}-\\x{9fa5}]/u', '', $blmc);
            //hjnr提取blmc1之后的内容
            $hjnr = substr($hjnr, strpos($hjnr, $blmc1));
            $hjnr = substr($hjnr, strpos($hjnr, '术前小结及术前讨论结论记录'));

            if (empty($hjnr)) {
                continue;
            }

            // 提取hjnr中最开始的时间（秒是可选参数）
            // 匹配格式：2025-10-26 12:30 或 2025-10-26 12:30:45
            $discussionTime = '';

            // 匹配带秒的时间格式：YYYY-MM-DD HH:MM:SS
            if (preg_match('/^(\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2})/', $hjnr, $matches)) {
                $discussionTime = $matches[1];
            }
            // 匹配不带秒的时间格式：YYYY-MM-DD HH:MM
            elseif (preg_match('/^(\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2})/', $hjnr, $matches)) {
                $discussionTime = $matches[1];
            }

            // 如果没有提取到讨论时间，跳过
            if (empty($discussionTime)) {
                continue;
            }

            // 转换为时间戳进行比较
            $discussionTimestamp = strtotime($discussionTime);
            $zxsjTimestamp = strtotime($zxsj);

            // 如果讨论时间无效，跳过
            if ($discussionTimestamp === false) {
                continue;
            }

            // 如果讨论时间晚于或等于标题时间，则质控出来
            // 正常情况：讨论时间应该早于标题时间
            if ($discussionTimestamp >= $zxsjTimestamp) {
                $basis = [];
                $basis[] = '病历标题【' . $blmc . '】';
                $basis[] = '标题时间【' . $zxsj . '】';
                $basis[] = '术前讨论时间【' . $discussionTime . '】';
                $basis[] = '术前讨论时间应早于标题时间，但讨论时间【' . $discussionTime . '】晚于或等于标题时间【' . $zxsj . '】';
                $basisList[] = $basis;
            }
        }

        if (!empty($basisList)) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, JSON_UNESCAPED_UNICODE),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }

        return [];
    }

    /**
     * 规则1232：术前小结--无术中注意事项
     */
    public function rule1232($caseRule = [], $ruleId, $ZYH = "")
    {
        //获取brry
        $brry = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->get()->toArray();
        if (empty($brry)) {
            return true;
        }

        // 获取术前小结及术前讨论结论记录的配置（8016对应的是MBLB，通常是82,304）
        $rule8016 = RuleWordMap::query()->where('id', 8016)->value('keyword');
        $rule8016 = !empty($rule8016) ? $rule8016 : '82,304';
        if (strpos($rule8016, ',') !== false) {
            $rule8016 = explode(',', $rule8016);
        } else {
            $rule8016 = [$rule8016];
        }

        $basisList = [];

        // 查询术前小结记录
        $BCJL = EMR_BL_BL01::query()
            ->where('JZHM', '=', $ZYH)
            ->whereIn('MBLB', $rule8016)
            ->get()
            ->toArray();

        if (empty($BCJL)) {
            return true;
        }

        foreach ($BCJL as $item) {
            $blmc = $item['BLMC'];
            $blbh = $item['BLBH'];

            // 从EMR_BL_BLXG表获取病历内容
            $hjnr = EMR_BL_BLXG::query()->where('BLBH', '=', $blbh)->value('HJNR');

            if (empty($hjnr)) {
                continue;
            }

            // 提取"注意事项："到"手术者"之间的文本
            $attentionMatters = '';

            // 匹配"注意事项："或"注意事项:"后面到"手术者"之前的内容
            if (preg_match('/注意事项[：:](.*?)手术者/us', $hjnr, $matches)) {
                $attentionMatters = trim($matches[1]);
            }

            if (empty($attentionMatters)) {
                continue;
            }

            // 取第一个"术中"之后的内容，如果为空就质控
            $surgeryContent = '';
            if (preg_match('/术中(.*)/us', $attentionMatters, $matches)) {
                $surgeryContent = trim($matches[1]);
            }

            // 如果术中内容为空，则质控
            if (empty($surgeryContent)) {
                $basis = [];
                $basis[] = '病历名称【' . $blmc . '】';
                $basis[] = '注意事项中术中内容为空';
                $basisList[] = $basis;
            }
        }

        if (!empty($basisList)) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, JSON_UNESCAPED_UNICODE),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }

        return [];
    }

    /**
     * 规则1232：术前小结--无术后注意事项
     */
    public function rule1238($caseRule = [], $ruleId, $ZYH = "")
    {
        //获取brry
        $brry = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->get()->toArray();
        if (empty($brry)) {
            return true;
        }

        // 获取术前小结及术前讨论结论记录的配置（8016对应的是MBLB，通常是82,304）
        $rule8016 = RuleWordMap::query()->where('id', 8016)->value('keyword');
        $rule8016 = !empty($rule8016) ? $rule8016 : '82,304';
        if (strpos($rule8016, ',') !== false) {
            $rule8016 = explode(',', $rule8016);
        } else {
            $rule8016 = [$rule8016];
        }

        $basisList = [];

        // 查询术前小结记录
        $BCJL = EMR_BL_BL01::query()
            ->where('JZHM', '=', $ZYH)
            ->whereIn('MBLB', $rule8016)
            ->get()
            ->toArray();

        if (empty($BCJL)) {
            return true;
        }

        foreach ($BCJL as $item) {
            $blmc = $item['BLMC'];
            $blbh = $item['BLBH'];

            // 从EMR_BL_BLXG表获取病历内容
            $hjnr = EMR_BL_BLXG::query()->where('BLBH', '=', $blbh)->value('HJNR');

            if (empty($hjnr)) {
                continue;
            }

            // 提取"注意事项："到"手术者"之间的文本
            $attentionMatters = '';

            // 匹配"注意事项："或"注意事项:"后面到"手术者"之前的内容
            if (preg_match('/注意事项[：:](.*?)手术者/us', $hjnr, $matches)) {
                $attentionMatters = trim($matches[1]);
            }

            if (empty($attentionMatters)) {
                continue;
            }

            // 取第一个"术后"之后的内容，如果为空就质控
            $surgeryContent = '';
            if (preg_match('/术后(.*)/us', $attentionMatters, $matches)) {
                $surgeryContent = trim($matches[1]);
            }

            // 如果术后内容为空，则质控
            if (empty($surgeryContent)) {
                $basis = [];
                $basis[] = '病历名称【' . $blmc . '】';
                $basis[] = '注意事项中术后内容为空';
                $basisList[] = $basis;
            }
        }

        if (!empty($basisList)) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, JSON_UNESCAPED_UNICODE),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }

        return [];
    }

    /**
     * 规则1233：手术记录--表格中无手术者姓名
     * 使用bllb303模型，通过ZYH关联查询手术记录，如果SSZ（手术者）为空就质控
     */
    public function rule1233($caseRule = [], $ruleId, $ZYH = "")
    {
        //获取brry
        $brry = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->get()->toArray();
        if (empty($brry)) {
            return true;
        }

        // 查询手术记录
        $ssjl = Bllb303::query()->where('ZYH', $ZYH)->get()->toArray();
        if (empty($ssjl)) {
            return true;
        }

        $basisList = [];

        foreach ($ssjl as $item) {
            $ssmc = $item['BLMC'] ?? ''; // 手术名称
            $ssz = $item['SSZ'] ?? ''; // 手术者

            // 如果手术者为空，则质控
            if (empty($ssz)) {
                $basis = [];
                if (!empty($ssmc)) {
                    $basis[] = '手术记录【' . $ssmc . '】';
                }
                $basis[] = '术者为填写';
                $basisList[] = $basis;
            }
        }

        if (!empty($basisList)) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, JSON_UNESCAPED_UNICODE),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }

        return [];
    }

    /**
     * 规则1234：手术记录--表格中无手术名称
     * 使用bllb303模型，通过ZYH关联查询手术记录，如果SSMC（手术名称）为空就质控
     */
    public function rule1234($caseRule = [], $ruleId, $ZYH = "")
    {
        //获取brry
        $brry = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->get()->toArray();
        if (empty($brry)) {
            return true;
        }

        // 查询手术记录
        $ssjl = Bllb303::query()->where('ZYH', $ZYH)->get()->toArray();
        if (empty($ssjl)) {
            return true;
        }

        $basisList = [];

        foreach ($ssjl as $item) {
            $ssmc = $item['SSMC'] ?? ''; // 手术名称
            $ssz = $item['SSZ'] ?? ''; // 手术者（用于辅助说明）

            // 如果手术名称为空，则质控
            if (empty($ssmc)) {
                $basis = [];
                if (!empty($ssz)) {
                    $basis[] = '手术记录【' . $ssmc . '】';
                }
                $basis[] = '手术名称为空';
                $basisList[] = $basis;
            }
        }

        if (!empty($basisList)) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, JSON_UNESCAPED_UNICODE),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }

        return [];
    }

    /**
     * 规则1235：手术记录--表格中无麻醉方法
     * 使用bllb303模型，通过ZYH关联查询手术记录，如果MZFS（麻醉方法）为空就质控
     */
    public function rule1235($caseRule = [], $ruleId, $ZYH = "")
    {
        //获取brry
        $brry = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->get()->toArray();
        if (empty($brry)) {
            return true;
        }

        // 查询手术记录
        $ssjl = Bllb303::query()->where('ZYH', $ZYH)->get()->toArray();
        if (empty($ssjl)) {
            return true;
        }

        $basisList = [];

        foreach ($ssjl as $item) {
            $ssmc = $item['BLMC'] ?? ''; // 手术名称（用于辅助说明）
            $mzfs = $item['MZFS'] ?? ''; // 麻醉方法

            // 如果麻醉方法为空，则质控
            if (empty($mzfs)) {
                $basis = [];
                if (!empty($ssmc)) {
                    $basis[] = '手术记录【' . $ssmc . '】';
                }
                $basis[] = '麻醉方法为空';
                $basisList[] = $basis;
            }
        }

        if (!empty($basisList)) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, JSON_UNESCAPED_UNICODE),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }

        return [];
    }

    /**
     * 规则1236：手术记录--无手术经过
     * 使用bllb303模型，通过ZYH关联查询手术记录，如果SSJG（手术经过）为空就质控
     */
    public function rule1236($caseRule = [], $ruleId, $ZYH = "")
    {
        //获取brry
        $brry = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->get()->toArray();
        if (empty($brry)) {
            return true;
        }

        // 查询手术记录
        $ssjl = Bllb303::query()->where('ZYH', $ZYH)->get()->toArray();
        if (empty($ssjl)) {
            return true;
        }

        $basisList = [];

        foreach ($ssjl as $item) {
            $ssmc = $item['BLMC'] ?? ''; // 手术名称（用于辅助说明）
            $ssjg = $item['SSJG'] ?? ''; // 手术经过

            // 如果手术经过为空，则质控
            if (empty($ssjg)) {
                $basis = [];
                if (!empty($ssmc)) {
                    $basis[] = '手术记录【' . $ssmc . '】';
                }
                $basis[] = '手术经过为空';
                $basisList[] = $basis;
            }
        }

        if (!empty($basisList)) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, JSON_UNESCAPED_UNICODE),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }

        return [];
    }

    /**
     * 规则1237：手术记录--有标本送检时，件数不能为0
     * 使用bllb303模型，通过ZYH关联查询手术记录，如果SSZBBSBL=是时BBJS不能=0或者空
     */
    public function rule1237($caseRule = [], $ruleId, $ZYH = "")
    {
        //获取brry
        $brry = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->get()->toArray();
        if (empty($brry)) {
            return true;
        }

        // 查询手术记录
        $ssjl = Bllb303::query()->where('ZYH', $ZYH)->get()->toArray();
        if (empty($ssjl)) {
            return true;
        }

        $basisList = [];

        foreach ($ssjl as $item) {
            $ssmc = $item['BLMC'] ?? ''; // 手术名称（用于辅助说明）
            $sszbbsbl = $item['SSZBBSBL'] ?? ''; // 手术中标本病送标签
            $bbjs = $item['BBJS'] ?? ''; // 标本件数

            // 如果有标本送检（SSZBBSBL=是），但标本件数为0或为空，则质控
            if ($sszbbsbl === '是' && (empty($bbjs) || $bbjs == '0' || $bbjs == 0 || $bbjs == '')) {
                $basis = [];
                if (!empty($ssmc)) {
                    $basis[] = '手术记录【' . $ssmc . '】';
                }
                $basis[] = '手术记录中有标本送检，但【标本件数】为空或为0';
                $basisList[] = $basis;
            }
        }

        if (!empty($basisList)) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, JSON_UNESCAPED_UNICODE),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }

        return [];
    }

    /**
     * 规则1239：出院记录--无入院情况
     * 使用bllb1模型，通过ZYH关联查询出院记录，如果RYKQ（入院情况）为空就质控
     */
    public function rule1239($caseRule = [], $ruleId, $ZYH = "")
    {
        //获取brry
        $brry = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->get()->toArray();
        if (empty($brry)) {
            return true;
        }

        // 查询出院记录
        $cyjl = Bllb1::query()->where('ZYH', $ZYH)->first();
        if (empty($cyjl)) {
            return true;
        }

        $cyjl = $cyjl->toArray();
        $rykq = $cyjl['RYQK'] ?? ''; // 入院情况

        $basisList = [];

        // 如果入院情况为空，则质控
        if (empty($rykq)) {
            $basis = [];
            $basis[] = '出院记录中【入院情况】为空';
            $basisList[] = $basis;
        }

        if (!empty($basisList)) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, JSON_UNESCAPED_UNICODE),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }

        return [];
    }

    /**
     * 规则1240：出院记录--无诊疗经过
     * 使用bllb1模型，通过ZYH关联查询出院记录，如果ZLJG（诊疗经过）为空就质控
     */
    public function rule1240($caseRule = [], $ruleId, $ZYH = "")
    {
        //获取brry
        $brry = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->get()->toArray();
        if (empty($brry)) {
            return true;
        }

        // 查询出院记录
        $cyjl = Bllb1::query()->where('ZYH', $ZYH)->first();
        if (empty($cyjl)) {
            return true;
        }

        $cyjl = $cyjl->toArray();
        $zljg = $cyjl['ZLJG'] ?? ''; // 诊疗经过

        $basisList = [];

        // 如果诊疗经过为空，则质控
        if (empty($zljg)) {
            $basis = [];
            $basis[] = '出院记录中【诊疗经过】为空';
            $basisList[] = $basis;
        }

        if (!empty($basisList)) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, JSON_UNESCAPED_UNICODE),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }

        return [];
    }

    /**
     * 规则1241：出院记录--无出院情况
     * 使用bllb1模型，通过ZYH关联查询出院记录，如果CYQK（出院情况）为空就质控
     */
    public function rule1241($caseRule = [], $ruleId, $ZYH = "")
    {
        //获取brry
        $brry = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->get()->toArray();
        if (empty($brry)) {
            return true;
        }

        // 查询出院记录
        $cyjl = Bllb1::query()->where('ZYH', $ZYH)->first();
        if (empty($cyjl)) {
            return true;
        }

        $cyjl = $cyjl->toArray();
        $cyqk = $cyjl['CYQK'] ?? ''; // 出院情况

        $basisList = [];

        // 如果出院情况为空，则质控
        if (empty($cyqk)) {
            $basis = [];
            $basis[] = '出院记录中【出院情况】为空';
            $basisList[] = $basis;
        }

        if (!empty($basisList)) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, JSON_UNESCAPED_UNICODE),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }

        return [];
    }

    /**
     * 规则1242：死亡记录--无诊疗经过
     * 使用bllb288模型，通过ZYH关联查询死亡记录，如果ZLJG（诊疗经过）为空就质控
     */
    public function rule1242($caseRule = [], $ruleId, $ZYH = "")
    {
        //获取brry
        $brry = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->get()->toArray();
        if (empty($brry)) {
            return true;
        }

        // 查询死亡记录
        $swjl = Bllb288::query()->where('ZYH', $ZYH)->first();
        if (empty($swjl)) {
            return true;
        }

        $swjl = $swjl->toArray();
        $zljg = $swjl['ZLJG'] ?? ''; // 诊疗经过

        $basisList = [];

        // 如果诊疗经过为空，则质控
        if (empty($zljg)) {
            $basis = [];
            $basis[] = '死亡记录中【诊疗经过】为空';
            $basisList[] = $basis;
        }

        if (!empty($basisList)) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, JSON_UNESCAPED_UNICODE),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }

        return [];
    }

    /**
     * 规则1243：死亡记录--无死亡原因
     * 使用bllb288模型，通过ZYH关联查询死亡记录，如果SWYY（死亡原因）为空就质控
     */
    public function rule1243($caseRule = [], $ruleId, $ZYH = "")
    {
        //获取brry
        $brry = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->get()->toArray();
        if (empty($brry)) {
            return true;
        }

        // 查询死亡记录
        $swjl = Bllb288::query()->where('ZYH', $ZYH)->first();
        if (empty($swjl)) {
            return true;
        }

        $swjl = $swjl->toArray();
        $swyy = $swjl['SWYY'] ?? ''; // 死亡原因

        $basisList = [];

        // 如果死亡原因为空，则质控
        if (empty($swyy)) {
            $basis = [];
            $basis[] = '死亡记录中【死亡原因】为空';
            $basisList[] = $basis;
        }

        if (!empty($basisList)) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, JSON_UNESCAPED_UNICODE),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }

        return [];
    }

    /**
     * 规则1244：死亡病例讨论结论记录中死亡原因与死亡记录中的死亡原因不一致
     * 首先从bllb288查询死亡记录获取死亡原因SWYY，然后从bl01 mblb 8029获取死亡病例讨论记录，检查hjnr中是否包含SWYY
     */
    public function rule1244($caseRule = [], $ruleId, $ZYH = "")
    {
        //获取brry
        $brry = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->get()->toArray();
        if (empty($brry)) {
            return true;
        }

        // 1. 查询死亡记录获取死亡原因SWYY
        $swjl = Bllb288::query()->where('ZYH', $ZYH)->first();
        if (empty($swjl)) {
            return true; // 没有死亡记录，不进行质控
        }

        $swjl = $swjl->toArray();
        $swyy = $swjl['SWYY'] ?? ''; // 死亡原因

        // 如果死亡原因为空，不进行质控（这种情况由规则1243处理）
        if (empty($swyy)) {
            return true;
        }

        // 2. 获取死亡病例讨论结论记录MBLB配置（8029）
        $discussionMBLB = RuleWordMap::query()->where('id', '=', 8029)->value('keyword');
        $discussionMBLB = !empty($discussionMBLB) ? $discussionMBLB : '4302'; // 死亡病例讨论结论记录MBLB默认值

        // 判断是否包含逗号
        if (strpos($discussionMBLB, ',') !== false) {
            $mblbTypes = explode(',', $discussionMBLB);
        } else {
            $mblbTypes = [$discussionMBLB];
        }

        // 3. 查询死亡病例讨论结论记录
        $discussionRecords = EMR_BL_BL01::query()
            ->where('JZHM', $ZYH)
            ->whereIn('MBLB', $mblbTypes)
            ->get()
            ->toArray();

        if (empty($discussionRecords)) {
            return true; // 没有死亡病例讨论记录，不进行质控
        }

        $basisList = [];

        // 4. 遍历每条死亡病例讨论记录，检查hjnr中是否包含死亡原因
        foreach ($discussionRecords as $record) {
            $blbh = $record['BLBH'];
            $blmc = $record['BLMC'] ?? ''; // 病历名称

            // 获取病历内容
            $blxgData = EMR_BL_BLXG::query()->where('BLBH', $blbh)->first();
            if (empty($blxgData)) {
                continue;
            }

            $hjnr = $blxgData['HJNR'] ?? ''; // 病历内容

            //提取“死亡原因”之后的内容
            $deathReason = preg_replace('/死亡原因：.*?/', '', $hjnr);
            //去除所有空格
            $deathReason = str_replace(' ', '', $deathReason);
            //去除数字和符号只保留汉字
            $deathReason = preg_replace('/[0-9\s\p{P}]/u', '', $deathReason);

            $swyy = str_replace(' ', '', $swyy);
            $swyy = preg_replace('/[0-9\s\p{P}]/u', '', $swyy);

            // 检查病历内容中是否包含死亡原因
            if (empty($deathReason) || strpos($deathReason, $swyy) === false) {
                $basis = [];
                if (!empty($blmc)) {
                    $basis[] = '病历名称【' . $blmc . '】';
                }
                $basis[] = '死亡记录死亡原因【' . $swyy . '】';
                $basis[] = '死亡病例讨论结论记录中“死亡原因”与死亡记录中的“死亡原因”中不一致';
                $basisList[] = $basis;
            }
        }

        if (!empty($basisList)) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, JSON_UNESCAPED_UNICODE),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }

        return [];
    }

    /**
     * 规则1245：死亡病例讨论结论记录中"死亡诊断"与死亡记录中的"死亡诊断"不一致
     * 首先从bllb288查询死亡记录获取死亡诊断SWZD，然后从bl01 mblb 8029获取死亡病例讨论记录，检查hjnr中是否包含SWZD
     */
    public function rule1245($caseRule = [], $ruleId, $ZYH = "")
    {
        //获取brry
        $brry = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->get()->toArray();
        if (empty($brry)) {
            return true;
        }

        // 1. 查询死亡记录获取死亡诊断SWZD
        $swjl = Bllb288::query()->where('ZYH', $ZYH)->first();
        if (empty($swjl)) {
            return true; // 没有死亡记录，不进行质控
        }

        $swjl = $swjl->toArray();
        $swzd = $swjl['SWZD'] ?? ''; // 死亡诊断

        // 如果死亡诊断为空，不进行质控
        if (empty($swzd)) {
            return true;
        }

        // 2. 获取死亡病例讨论结论记录MBLB配置（8029）
        $discussionMBLB = RuleWordMap::query()->where('id', '=', 8029)->value('keyword');
        $discussionMBLB = !empty($discussionMBLB) ? $discussionMBLB : '4302'; // 死亡病例讨论结论记录MBLB默认值

        // 判断是否包含逗号
        if (strpos($discussionMBLB, ',') !== false) {
            $mblbTypes = explode(',', $discussionMBLB);
        } else {
            $mblbTypes = [$discussionMBLB];
        }

        // 3. 查询死亡病例讨论结论记录
        $discussionRecords = EMR_BL_BL01::query()
            ->where('JZHM', $ZYH)
            ->whereIn('MBLB', $mblbTypes)
            ->get()
            ->toArray();

        if (empty($discussionRecords)) {
            return true; // 没有死亡病例讨论记录，不进行质控
        }

        $basisList = [];

        // 4. 遍历每条死亡病例讨论记录，检查hjnr中是否包含死亡诊断
        foreach ($discussionRecords as $record) {
            $blbh = $record['BLBH'];
            $blmc = $record['BLMC'] ?? ''; // 病历名称

            // 获取病历内容
            $blxgData = EMR_BL_BLXG::query()->where('BLBH', $blbh)->first();
            if (empty($blxgData)) {
                continue;
            }

            $hjnr = $blxgData['HJNR'] ?? ''; // 病历内容

            //提取“死亡诊断”之后的内容
            $deathDiagnosis = preg_replace('/死亡诊断：.*?/', '', $hjnr);

            //去除所有空格
            $deathDiagnosis = str_replace(' ', '', $deathDiagnosis);
            //去除数字和符号只保留汉字
            $deathDiagnosis = preg_replace('/[0-9\s\p{P}]/u', '', $deathDiagnosis);
            $swzd = str_replace(' ', '', $swzd);
            $swzd = preg_replace('/[0-9\s\p{P}]/u', '', $swzd);

            // 检查病历内容中是否包含死亡诊断
            if (empty($deathDiagnosis) || strpos($deathDiagnosis, $swzd) === false) {
                $basis = [];
                if (!empty($blmc)) {
                    $basis[] = '病历名称【' . $blmc . '】';
                }
                $basis[] = '死亡记录死亡诊断【' . $swzd . '】';
                $basis[] = '死亡病例讨论结论记录中"死亡诊断"与死亡记录中的"死亡诊断"不一致';
                $basisList[] = $basis;
            }
        }

        if (!empty($basisList)) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, JSON_UNESCAPED_UNICODE),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }

        return [];
    }

    /**
     * 规则1246：死亡病例讨论结论记录中死亡诊断未包括死亡原因
     * 取死亡病例讨论记录的hjnr，提取"死亡原因"到"死亡诊断"之间的内容，去除标点符号后如果为空就质控
     */
    public function rule1246($caseRule = [], $ruleId, $ZYH = "")
    {
        //获取brry
        $brry = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->get()->toArray();
        if (empty($brry)) {
            return true;
        }

        // 1. 获取死亡病例讨论结论记录MBLB配置（8029）
        $discussionMBLB = RuleWordMap::query()->where('id', '=', 8029)->value('keyword');
        $discussionMBLB = !empty($discussionMBLB) ? $discussionMBLB : '4302'; // 死亡病例讨论结论记录MBLB默认值

        // 判断是否包含逗号
        if (strpos($discussionMBLB, ',') !== false) {
            $mblbTypes = explode(',', $discussionMBLB);
        } else {
            $mblbTypes = [$discussionMBLB];
        }

        // 2. 查询死亡病例讨论结论记录
        $discussionRecords = EMR_BL_BL01::query()
            ->where('JZHM', $ZYH)
            ->whereIn('MBLB', $mblbTypes)
            ->get()
            ->toArray();

        if (empty($discussionRecords)) {
            return true; // 没有死亡病例讨论记录，不进行质控
        }

        $basisList = [];

        // 3. 遍历每条死亡病例讨论记录
        foreach ($discussionRecords as $record) {
            $blbh = $record['BLBH'];
            $blmc = $record['BLMC'] ?? ''; // 病历名称

            // 获取病历内容
            $blxgData = EMR_BL_BLXG::query()->where('BLBH', $blbh)->first();
            if (empty($blxgData)) {
                continue;
            }

            $hjnr = $blxgData['HJNR'] ?? ''; // 病历内容

            // 4. 提取"死亡原因"的内容，只要有填写就不质控
            $deathCauseContent = '';

            // 使用正则表达式提取"死亡原因："后面的内容（到换行或下一个标签）
            if (preg_match('/死亡原因[：:]\s*([^\n\r]+)/u', $hjnr, $matches)) {
                $deathCauseContent = trim($matches[1]);
                // 去除结尾的标点符号和空白
                $deathCauseContent = preg_replace('/[\s；;。，,：:]+$/u', '', $deathCauseContent);
            }

            // 去除标点符号和空白字符
            $causeCleaned = preg_replace('/[\s\p{P}]/u', '', $deathCauseContent);

            // 如果死亡原因为空或没有内容，则质控
            if (empty($causeCleaned)) {
                $basis = [];
                if (!empty($blmc)) {
                    $basis[] = '病历名称【' . $blmc . '】';
                }
                $basis[] = '死亡病例讨论结论记录中未填写死亡原因或死亡原因为空';
                $basisList[] = $basis;
            }
        }

        if (!empty($basisList)) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, JSON_UNESCAPED_UNICODE),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }

        return [];
    }

    /**
     * 规则1247：死亡病例讨论结论记录--无主持人签名（病区主任签名）
     * 查询bllb包含8090的，先按照blbh获取所有签名，循环去staff表查询name
     * 然后取出内容中【在。。。。主持下】这句话，看看是否包含这个医师
     * 如果包含就是满足，如果不包含就质控出来
     */
    public function rule1247($caseRule = [], $ruleId, $ZYH = "")
    {
        //获取brry
        $brry = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->get()->toArray();
        if (empty($brry)) {
            return true;
        }

        // 获取死亡病例讨论结论记录的病历类别配置（8029对应的是MBLB，通常是4302）
        $discussionMBLB = RuleWordMap::query()->where('id', '=', 8029)->value('keyword');
        $discussionMBLB = !empty($discussionMBLB) ? $discussionMBLB : '4302'; // 死亡病例讨论结论记录MBLB默认值

        // 判断是否包含逗号
        if (strpos($discussionMBLB, ',') !== false) {
            $mblbTypes = explode(',', $discussionMBLB);
        } else {
            $mblbTypes = [$discussionMBLB];
        }

        $basisList = [];

        // 从MySQL查询死亡病例讨论结论记录
        $newData = EMR_BL_BL01::query()
            ->where('JZHM', $ZYH)
            ->whereIn('MBLB', $mblbTypes)
            ->get()->toArray();
        if (empty($newData) || empty($newData[0])) {
            return true;
        }

        $BCJL = $newData;
        if (empty($BCJL)) {
            return true;
        }

        foreach ($BCJL as $item) {
            $blmc = $item['BLMC'];
            $blbh = $item['BLBH'];
            $hjnr = $item['HJNR'] ?? '';

            if (empty($hjnr)) {
                continue;
            }

            // 按照blbh获取所有签名
            $blsy = EMR_BL_BLSY::query()
                ->where('BLBH', '=', $blbh)
                ->where('FG_ACTIVE', 1)
                ->get()
                ->toArray();

            if (empty($blsy)) {
                continue;
            }

            // 从病历内容中提取【在...主持下】这句话中的主持人姓名
            // 匹配模式：在XX主持下 或 在XXX主持下
            $pattern = '/在(.{2,10}?)主持下/u';
            preg_match($pattern, $hjnr, $matches);

            if (empty($matches[1])) {
                // 如果没有找到主持人信息，跳过
                continue;
            }

            $hostName = trim($matches[1]); // 主持人姓名

            // 循环查询所有签名医师的姓名
            $signerNames = [];
            foreach ($blsy as $syItem) {
                $syys = $syItem['SYYS']; // 书以医师工号
                if (empty($syys)) {
                    continue;
                }

                // 从staff表查询医师姓名
                $staffInfo = Staff::query()
                    ->where('code', '=', $syys)
                    ->first();

                if (!empty($staffInfo) && !empty($staffInfo->name)) {
                    $signerNames[] = $staffInfo->name;
                }
            }

            // 检查主持人姓名是否在签名列表中
            $isHostSigned = false;
            foreach ($signerNames as $signerName) {
                // 使用stripos进行不区分大小写的包含判断
                if (strpos($hostName, $signerName) !== false || strpos($signerName, $hostName) !== false) {
                    $isHostSigned = true;
                    break;
                }
            }

            // 如果主持人没有签名，则质控出来
            if (!$isHostSigned) {
                $basis = [];
                $basis[] = '病历标题【' . $blmc . '】';
                $basis[] = '主持人【' . $hostName . '】';
                $basis[] = '已签名医师【' . implode('、', $signerNames) . '】';
                $basis[] = '病历内容中的主持人【' . $hostName . '】未在签名列表中';
                $basisList[] = $basis;
            }
        }

        if (!empty($basisList)) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, JSON_UNESCAPED_UNICODE),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }

        return [];
    }

    /**
     * 规则1248：危急值记录--标题时间早于危急值接收、处置时间
     * 查询危急值记录，标题时间是ZXSJ，接收处置时间从hjnr中提取
     */
    public function rule1248($caseRule = [], $ruleId, $ZYH = "")
    {
        //获取brry
        $brry = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->get()->toArray();
        if (empty($brry)) {
            return true;
        }

        // 1. 获取危急值记录相关配置
        // 获取病程记录类型
        $ruleMap8010 = RuleWordMap::query()->where('id', '=', 8010)->value('keyword');
        $ruleMap8010 = !empty($ruleMap8010) ? $ruleMap8010 : '294'; // 病程记录类型BLLB值

        // 判断是否包含逗号
        if (strpos($ruleMap8010, ',') !== false) {
            $blTypes = explode(',', $ruleMap8010);
        } else {
            $blTypes = [$ruleMap8010];
        }

        // 获取危急值记录的时间字段
        $ruleMap8011 = RuleWordMap::query()->where('id', '=', 8011)->value('keyword');
        $ruleMap8011 = !empty($ruleMap8011) ? $ruleMap8011 : 'ZXSJ'; // 执行时间字段名

        // 获取危急值记录的名称关键词
        $ruleMap8030 = RuleWordMap::query()->where('id', '=', 8030)->value('keyword');
        $ruleMap8030 = !empty($ruleMap8030) ? $ruleMap8030 : '危急值记录'; // 危急值记录关键词

        // 2. 查询危急值记录
        $wjzRecords = EMR_BL_BL01::query()
            ->where('JZHM', $ZYH)
            ->whereIn('BLLB', $blTypes)
            ->where('BLMC', 'like', '%' . $ruleMap8030 . '%')
            ->get()
            ->toArray();

        if (empty($wjzRecords)) {
            return true; // 没有危急值记录，不进行质控
        }

        $basisList = [];

        // 3. 遍历每条危急值记录
        foreach ($wjzRecords as $record) {
            $blbh = $record['BLBH'];
            $blmc = $record['BLMC'] ?? ''; // 病历名称
            $zxsj = $record[$ruleMap8011] ?? ''; // 标题时间（执行时间）

            // 如果标题时间为空或无效，跳过
            if (empty($zxsj) || $zxsj == '1970-01-01 00:00:00' || $zxsj == '0000-00-00 00:00:00') {
                continue;
            }

            // 获取病历内容
            $blxgData = EMR_BL_BLXG::query()->where('BLBH', $blbh)->first();
            if (empty($blxgData)) {
                continue;
            }

            $hjnr = $blxgData['HJNR'] ?? ''; // 病历内容

            if (empty($hjnr)) {
                continue;
            }

            $hjnr = substr($hjnr, strpos($hjnr, '危急值记录'));
            if (empty($hjnr)) {
                continue;
            }

            // 4. 从病历内容中提取接收、处置时间
            // 匹配格式：2025-10-12 10:00 或 2025-10-12 10:00:00
            // 通常在"接收"或"处置"关键词附近
            $timePattern = '/(\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}(?::\d{2})?)/';
            preg_match_all($timePattern, $hjnr, $matches);

            if (empty($matches[1])) {
                continue; // 没有找到时间，跳过
            }

            //精确到分钟
            $zxsj = date("Y-m-d H:i", strtotime($zxsj));

            $extractedTimes = $matches[1];
            $zxsjTimestamp = strtotime($zxsj);

            // 5. 检查标题时间是否早于提取的时间
            $hasEarlierTime = false;
            $earlierTimeStr = '';

            foreach ($extractedTimes as $extractedTime) {
                //精确到分钟
                $extractedTime = date("Y-m-d H:i", strtotime($extractedTime));
                $extractedTimestamp = strtotime($extractedTime);

                // 如果标题时间早于提取的时间，则质控
                if ($zxsjTimestamp < $extractedTimestamp) {
                    $hasEarlierTime = true;
                    $earlierTimeStr = $extractedTime;
                    break;
                }
            }

            if ($hasEarlierTime) {
                $basis = [];
                if (!empty($blmc)) {
                    $basis[] = '病历名称【' . $blmc . '】';
                }
                $basis[] = '标题时间【' . $zxsj . '】';
                $basis[] = '接收/处置时间【' . $earlierTimeStr . '】';
                $basis[] = '标题时间早于危急值接收、处置时间';
                $basisList[] = $basis;
            }
        }

        if (!empty($basisList)) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, JSON_UNESCAPED_UNICODE),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }

        return [];
    }

    /**
     * 规则1249：月经生育史--女性生育史未使用月经表达式记录
     * 参考rule80，检查月经表达式格式：如果没有-或者按照-分隔后[0]或[1]为空就质控
     */
    public function rule1249($caseRule = [], $ruleId, $ZYH = "")
    {
        // 1. 查询入院记录
        $newData = Bllb292::query()->where("ZYH", $ZYH)->first();
        if (empty($newData)) {
            return [];
        }

        $newData = $newData->toArray();
        $xbData = $newData["XB"] ?? '';
        $age = trim($newData["NL"] ?? '');

        // 如果年龄是天为单位，那么转换成年
        if (strpos($age, '天') !== false || strpos($age, '分钟') !== false) {
            $age = 1;
        }

        // 2. 只检查女性且年龄不超过60岁的患者
        if (intval($age) > 60 || $xbData == '男') {
            return [];
        }

        // 3. 获取月经初潮时间（月经生育史）
        $YJCXSJ = $newData["TGJC_YJCXSJ"] ?? '';
        if (empty($YJCXSJ)) {
            return []; // 如果为空，不质控（这种情况由其他规则处理）
        }

        $basisList = [];

        // 4. 检查是否使用了月经表达式（格式应为：X-Y，如3-5）
        if (strpos($YJCXSJ, '-') === false) {
            // 没有"-"符号，未使用月经表达式
            $basis = [];
            $basis[] = '月经生育史【' . $YJCXSJ . '】';
            $basis[] = '未使用月经表达式记录';
            $basisList[] = $basis;
        } else {
            // 有"-"符号，检查分隔后的值是否为空
            $YJCXSJArr = explode('-', $YJCXSJ);

            // 检查[0]或[1]是否为空
            if (empty(trim($YJCXSJArr[0])) || empty(trim($YJCXSJArr[1] ?? ''))) {
                $basis = [];
                $basis[] = '月经生育史【' . $YJCXSJ . '】';
                $basis[] = '月经表达式格式不正确';
                $basisList[] = $basis;
            }
        }

        if (!empty($basisList)) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, JSON_UNESCAPED_UNICODE),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }

        return [];
    }

    /**
     * 规则1250：首次病程记录--病例特点1：出现"否认"疾病史
     * 使用bllb294_295(有model) ZYH关联查询
     * 取BLTD，如果包含'1.'和'2.'就取之间的，如果没有就全部，如果包含'否认'就质控
     */
    public function rule1250($caseRule = [], $ruleId, $ZYH = "")
    {
        //获取brry
        $brry = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->get()->toArray();
        if (empty($brry)) {
            return true;
        }

        // 1. 查询首次病程记录
        $bl01 = Bllb294_295::query()->where("ZYH", $ZYH)->first();
        if (empty($bl01)) {
            return [];
        }

        $bl01 = $bl01->toArray();
        $bltd = $bl01['BLTD'] ?? ''; // 病例特点

        if (empty($bltd)) {
            return [];
        }

        $blmc = $bl01['BLMC'] ?? ''; // 病历名称

        // 2. 提取病例特点1的内容
        $bltd1 = '';

        // 检查是否同时包含'1.'和'2.'
        if (strpos($bltd, '1.') !== false && strpos($bltd, '2.') !== false) {
            // 提取'1.'和'2.'之间的内容
            preg_match('/1\.(.*?)2\./s', $bltd, $matches);
            if (!empty($matches[1])) {
                $bltd1 = trim($matches[1]);
            }
        } else {
            // 如果没有'1.'和'2.'，就取全部内容
            //$bltd1 = $bltd;
            return [];
        }

        $ryjl = Bllb292::query()->where("ZYH", $ZYH)->get(["JWS"])->toArray();
        if (empty($ryjl)) {
            return [];
        }

        $xbs = $ryjl[0]['JWS'];
        if (empty($xbs)) {
            return [];
        }

        // 先获取bltd和xbs的字数（不包含标点符号）
        $bltdWordCount = mb_strlen(preg_replace('/[^\p{L}\p{N}]/u', '', $bltd1));
        $xbsWordCount = mb_strlen(preg_replace('/[^\p{L}\p{N}]/u', '', $xbs));

        // 如果bltd字数/xbs字数小于0.85就直接返回
        if ($xbsWordCount > 0 && $bltdWordCount / $xbsWordCount < 0.60) {
            return [];
        }

        $bltdArray = preg_split('//u', $bltd, 0, PREG_SPLIT_NO_EMPTY);
        $xbsArray = preg_split('//u', $xbs, 0, PREG_SPLIT_NO_EMPTY);
        $res = array_intersect($bltdArray, $xbsArray);
        if (count($bltdArray) > 0 && count($res) / count($bltdArray) >= 0.60) {
            $this->insertData[] = [
                'basis' => json_encode([[""]], 256),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => '',
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }

        return [];
    }

    /**
     * 规则1251：有创操作记录--标题时间早于操作时间
     * 操作记录查询参考1064，标题时间是ZXSJ，操作时间从hjnr中提取
     * 内容示例【今日2025-10-01 12:34:00患者。。。】
     */
    public function rule1251($caseRule = [], $ruleId, $ZYH = "")
    {
        //获取brry
        $brry = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->get()->toArray();
        if (empty($brry)) {
            return true;
        }

        // 1. 获取操作记录配置
        $rule8010 = RuleWordMap::query()->where('id', 8010)->value('keyword');
        if (strpos($rule8010, ",")) {
            $rule8010 = explode(",", $rule8010);
        } else {
            $rule8010 = [$rule8010];
        }

        $rule8081 = RuleWordMap::query()->where('id', 8081)->value('keyword');
        if (strpos($rule8081, ",")) {
            $rule8081 = explode(",", $rule8081);
        } elseif (!empty($rule8081)) {
            $rule8081 = [$rule8081];
        }

        $rule8011 = RuleWordMap::query()->where('id', 8011)->value('keyword');
        $rule8011 = !empty($rule8011) ? $rule8011 : 'ZXSJ';

        // 2. 从MySQL查询符合条件的操作记录：BLLB=294且BLMC含"操作记录"
        $bl01Query = EMR_BL_BL01::query()
            ->where('JZHM', $ZYH)
            ->whereIn('BLLB', $rule8010)->get()->toArray();
        $resData = [];
        foreach ($bl01Query as $record) {
            foreach ($rule8081 as $v) {
                if (strpos($record['BLMC'], $v) !== false) {
                    $resData[] = $record;
                    break;
                }
            }
        }

        if (empty($resData) || empty($resData[0]) || empty($resData[0]['BLBH'])) {
            return true;
        }

        $bl01 = $resData;
        $basisList = [];

        // 3. 遍历每条操作记录
        foreach ($bl01 as $record) {
            $HJNR = $record['HJNR'] ?? '';
            $BLMC = $record['BLMC'] ?? '';
            $ZXSJ = $record[$rule8011] ?? '';

            // 如果标题时间为空或无效，跳过
            if (empty($ZXSJ) || $ZXSJ == '1970-01-01 00:00:00' || $ZXSJ == '0000-00-00 00:00:00') {
                continue;
            }

            //blmc只保留汉字
            $BLMC1 = preg_replace('/[^\\x{4e00}-\\x{9fa5}]/u', '', $BLMC);
            //hjnr提取blmc1之后的内容
            $HJNR = substr($HJNR, strpos($HJNR, $BLMC1));

            if (empty($HJNR)) {
                continue;
            }

            // 4. 从病历内容中提取操作时间
            // 匹配格式：今日13时24分患者，提取成13:24
            $timePattern = '/今日(\d{1,2}时\d{1,2}分)患者/';
            preg_match($timePattern, $HJNR, $matches);

            // 如果没有匹配到"今日"格式，尝试直接匹配日期时间
            if (empty($matches[1])) {
                continue;
            }
            //拼接成日期时间格式 13:24
            $operationTime = str_replace('时', ':', $matches[1]);
            $operationTime = str_replace('分', '', $operationTime);
            // 5. 比较标题时间和操作时间，只比较时分
            $zxsjTime = date('H:i', strtotime($ZXSJ));
            $operationTimeFormatted = $operationTime;

            // 如果标题时间早于操作时间，则质控
            if ($zxsjTime < $operationTimeFormatted) {
                $basis = [];
                $basis[] = '病历名称【' . $BLMC . '】';
                $basis[] = '标题时间【' . $ZXSJ . '】';
                $basis[] = '操作时间【' . $operationTime . '】';
                $basis[] = '标题时间早于操作时间';
                $basisList[] = $basis;
            }
        }

        if (!empty($basisList)) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, JSON_UNESCAPED_UNICODE),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }

        return [];
    }

    /**
     * 规则1252：输血记录--标题时间早于输血结束时间
     * 查询输血记录参考1012，标题时间是ZXSJ，输血结束时间从hjnr中提取
     * 格式：与2025-10-22 22:30至2025-10-22 22:50
     */
    public function rule1252($caseRule = [], $ruleId, $ZYH = "")
    {
        //获取brry
        $brry = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->get()->toArray();
        if (empty($brry)) {
            return true;
        }

        // 1. 获取输血病程记录相关配置
        $ruleMap8031 = RuleWordMap::query()->where('id', '=', 8031)->value('keyword');
        $ruleMap8031 = !empty($ruleMap8031) ? $ruleMap8031 : '45'; // 输血病程记录MBLB值

        // 判断是否包含逗号
        if (strpos($ruleMap8031, ',') !== false) {
            $blTypes = explode(',', $ruleMap8031);
        } else {
            $blTypes = [$ruleMap8031];
        }

        // 获取执行时间字段名
        $ruleMap8011 = RuleWordMap::query()->where('id', '=', 8011)->value('keyword');
        $ruleMap8011 = !empty($ruleMap8011) ? $ruleMap8011 : 'ZXSJ'; // 执行时间字段名

        // 2. 查询输血记录
        $bl01Data = EMR_BL_BL01::query()
            ->where('JZHM', $ZYH)
            ->whereIn('MBLB', $blTypes)
            ->get()
            ->toArray();

        if (empty($bl01Data)) {
            return true; // 没有输血记录，不进行质控
        }

        $basisList = [];

        // 3. 遍历每条输血记录
        foreach ($bl01Data as $record) {
            $blbh = $record['BLBH'];
            $blmc = $record['BLMC'] ?? '';
            $zxsj = $record[$ruleMap8011] ?? ''; // 标题时间（执行时间）

            // 如果标题时间为空或无效，跳过
            if (empty($zxsj) || $zxsj == '1970-01-01 00:00:00' || $zxsj == '0000-00-00 00:00:00') {
                continue;
            }

            // 获取病历内容
            $blxgData = EMR_BL_BLXG::query()->where('BLBH', $blbh)->first();
            if (empty($blxgData)) {
                continue;
            }

            $hjnr = $blxgData['HJNR'] ?? ''; // 病历内容

            if (empty($hjnr)) {
                continue;
            }

            // 4. 从病历内容中提取输血结束时间
            // 匹配格式：至2025-10-22 22:50 或 至2025-10-22 22:50:00
            $timePattern = '/至(\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}(?::\d{2})?)/';
            preg_match($timePattern, $hjnr, $matches);

            // 如果没有提取到输血结束时间，跳过
            if (empty($matches[1])) {
                continue;
            }

            $endTime = $matches[1]; // 输血结束时间

            // 5. 比较标题时间和输血结束时间
            $zxsjTimestamp = strtotime($zxsj);
            $endTimeTimestamp = strtotime($endTime);

            // 如果标题时间早于输血结束时间，则质控
            if ($zxsjTimestamp < $endTimeTimestamp) {
                $basis = [];
                if (!empty($blmc)) {
                    $basis[] = '病历名称【' . $blmc . '】';
                }
                $basis[] = '标题时间【' . $zxsj . '】';
                $basis[] = '输血结束时间【' . $endTime . '】';
                $basis[] = '标题时间早于输血结束时间';
                $basisList[] = $basis;
            }
        }

        if (!empty($basisList)) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, JSON_UNESCAPED_UNICODE),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }

        return [];
    }

    /**
     * 规则1253：术前小结--术者未参加术前讨论
     * @param $caseRule
     * @param $ruleId
     * @param $ZYH
     * @return bool
     * @author lzh
     * @datetime 2025-10-30
     */
    public function rule1253($caseRule, $ruleId, $ZYH)
    {
        // 1. 查询手术信息，从bllb303表获取手术开始时间和术者
        try {
            $surgeryData = Bllb303::query()
                ->where('ZYH', '=', $ZYH)
                ->orderBy('SSKSSJ', 'asc')
                ->get()
                ->toArray();

            if (empty($surgeryData)) {
                return true; // 没有手术记录，不进行质控
            }
        } catch (\Exception $e) {
            Log::error("rule-$ruleId-$ZYH-error: " . $e->getMessage());
            return true; // 查询出错，跳过质控
        }

        // 2. 获取术前小结及术前讨论结论记录的配置
        $ruleMap8016 = RuleWordMap::query()->where('id', '=', 8016)->value('keyword');
        $ruleMap8016 = !empty($ruleMap8016) ? $ruleMap8016 : '82,304'; // 术前小结及术前讨论结论记录MBLB值

        // 判断是否包含逗号
        if (strpos($ruleMap8016, ',') !== false) {
            $recordTypes = explode(',', $ruleMap8016);
        } else {
            $recordTypes = [$ruleMap8016];
        }

        // 3. 获取查询字段
        // 获取执行时间字段名
        $ruleMap8011 = RuleWordMap::query()->where('id', '=', 8011)->value('keyword');
        $ruleMap8011 = !empty($ruleMap8011) ? $ruleMap8011 : 'ZXSJ'; // 执行时间字段名

        //获取8047,8048配置
        $excludeKeywords8047 = RuleWordMap::getArrayById(8047);

        // 收集所有质控结果
        $allBasisGroups = [];
        //是否是多台手术
        $isMultiSurgery = false;
        $surgerySTime = '';

        // 4. 处理每个手术记录
        foreach ($surgeryData as $surgery) {
            // 获取手术开始时间、结束时间和术者
            $surgeryStartTime = $surgery['SSKSSJ']; // 手术开始时间
            $surgeryEndTime = $surgery['SSJSSJ']; // 手术结束时间
            $surgeon = $surgery['SSZ']; // 术者
            $surgeryName = $surgery['SSMC'] ?? ''; // 手术名称

            // 检查必要字段是否有效
            if (
                empty($surgeryStartTime) || empty($surgeon) || empty($surgeryName) ||
                empty($surgeryEndTime) || strpos($surgeryEndTime, '1970-01-01') !== false ||
                $surgeryName == 'NULL'
            ) {
                continue;
            }
            //根据手术名称查询SSCZ的SSLB
            $ssCZ = SSCZ::query()->where('SSMC', $surgeryName)->value('SSLB');
            if (empty($ssCZ)) {
                continue;
            }
            //检查是否是手术+介入
            if (!in_array($ssCZ, $excludeKeywords8047)) {
                continue;
            }

            // 检查手术开始时间是否有效
            if ($surgeryStartTime == '1970-01-01 00:00:00' || $surgeryStartTime == '0000-00-00 00:00:00') {
                continue; // 跳过无效的手术时间
            }

            // 5. 查询术者的职称信息
            try {
                $staffInfo = Staff::query()
                    ->where('name', '=', $surgeon)
                    ->first();

                if (empty($staffInfo) || empty($staffInfo['ygjb_text'])) {
                    continue; // 没有找到术者信息或职称信息，跳过
                }

                $surgeonTitle = $staffInfo['ygjb_text']; // 职称
                $surgeonWithTitle = $surgeon . $surgeonTitle; // 术者+职称

            } catch (\Exception $e) {
                Log::error("rule-$ruleId-$ZYH-查询术者职称error: " . $e->getMessage());
                continue;
            }

            // 6. 查询术前小结及术前讨论结论记录
            try {
                $records = EMR_BL_BL01::query()
                    ->where('JZHM', $ZYH)
                    ->whereIn('MBLB', $recordTypes);
                if ($isMultiSurgery && !empty($surgerySTime)) {
                    $records = $records->where($ruleMap8011, '>=', $surgerySTime)->where($ruleMap8011, '<=', $surgeryStartTime);
                } else {
                    $records = $records->where($ruleMap8011, '<=', $surgeryStartTime);
                }
                $records = $records->get()->toArray();
            } catch (\Exception $e) {
                Log::error("rule-$ruleId-$ZYH-查询术前小结error: " . $e->getMessage());
                continue; // 查询出错，跳过当前手术记录
            }

            // 如果没有找到记录
            if (empty($records)) {
                continue;
            }

            $basis = [];
            $basis[] = "手术名称【" . $surgeryName . "】";
            $basis[] = "术者【" . $surgeon . "】";
            $basis[] = "职称【" . $surgeonTitle . "】";
            $isbh = false;
            // 7. 检查每条记录的会记内容中是否包含术者+职称
            foreach ($records as $record) {
                $hjnr = isset($record['HJNR']) ? $record['HJNR'] : ''; // 会记内容

                // 检查会记内容中是否包含术者+职称
                if (!empty($hjnr) && strpos($hjnr, $surgeonWithTitle) === false) {
                    // 会记内容中未包含术者+职称
                    $basis[] = "【" . $record['BLMC'] . "】中未包含术者及职称【" . $surgeonWithTitle . "】";
                    $basis["BLBH"] = $record['BLBH'];
                } else {
                    $isbh = true;
                    break;
                }
            }

            //如果所有记录中未包含术者+职称，则质控
            if (!$isbh) {
                $allBasisGroups[] = $basis;
            }

            $isMultiSurgery = true;
            $surgerySTime = $surgeryEndTime;
        }

        // 8. 将所有质控结果一次性插入到insertData
        if (!empty($allBasisGroups)) {
            $this->insertData[] = [
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title'],
                'basis' => json_encode($allBasisGroups, JSON_UNESCAPED_UNICODE)
            ];
        }

        return true;
    }

    /**
     * 规则1254：术前小结--无术者审核签字
     * @param $caseRule
     * @param $ruleId
     * @param $ZYH
     * @return bool
     * @author lzh
     * @datetime 2025-10-30
     */
    public function rule1254($caseRule, $ruleId, $ZYH)
    {
        // 1. 查询手术信息，从bllb303表获取手术开始时间和术者
        try {
            $surgeryData = Bllb303::query()
                ->where('ZYH', '=', $ZYH)
                ->orderBy('SSKSSJ', 'asc')
                ->get()
                ->toArray();

            if (empty($surgeryData)) {
                return true; // 没有手术记录，不进行质控
            }
        } catch (\Exception $e) {
            Log::error("rule-$ruleId-$ZYH-error: " . $e->getMessage());
            return true; // 查询出错，跳过质控
        }

        // 2. 获取术前小结及术前讨论结论记录的配置
        $ruleMap8016 = RuleWordMap::query()->where('id', '=', 8016)->value('keyword');
        $ruleMap8016 = !empty($ruleMap8016) ? $ruleMap8016 : '82,304'; // 术前小结及术前讨论结论记录MBLB值

        // 判断是否包含逗号
        if (strpos($ruleMap8016, ',') !== false) {
            $recordTypes = explode(',', $ruleMap8016);
        } else {
            $recordTypes = [$ruleMap8016];
        }

        // 3. 获取查询字段
        // 获取执行时间字段名
        $ruleMap8011 = RuleWordMap::query()->where('id', '=', 8011)->value('keyword');
        $ruleMap8011 = !empty($ruleMap8011) ? $ruleMap8011 : 'ZXSJ'; // 执行时间字段名

        //获取8047,8048配置
        $excludeKeywords8047 = RuleWordMap::getArrayById(8047);

        // 收集所有质控结果
        $allBasisGroups = [];
        //是否是多台手术
        $isMultiSurgery = false;
        $surgerySTime = '';

        // 4. 处理每个手术记录
        foreach ($surgeryData as $surgery) {
            // 获取手术开始时间、结束时间和术者
            $surgeryStartTime = $surgery['SSKSSJ']; // 手术开始时间
            $surgeryEndTime = $surgery['SSJSSJ']; // 手术结束时间
            $surgeon = $surgery['SSZ']; // 术者
            $surgeryName = $surgery['SSMC'] ?? ''; // 手术名称

            // 检查必要字段是否有效
            if (
                empty($surgeryStartTime) || empty($surgeon) || empty($surgeryName) ||
                empty($surgeryEndTime) || strpos($surgeryEndTime, '1970-01-01') !== false ||
                $surgeryName == 'NULL'
            ) {
                continue;
            }
            //根据手术名称查询SSCZ的SSLB
            $ssCZ = SSCZ::query()->where('SSMC', $surgeryName)->value('SSLB');
            if (empty($ssCZ)) {
                continue;
            }
            //检查是否是手术+介入
            if (!in_array($ssCZ, $excludeKeywords8047)) {
                continue;
            }

            // 检查手术开始时间是否有效
            if ($surgeryStartTime == '1970-01-01 00:00:00' || $surgeryStartTime == '0000-00-00 00:00:00') {
                continue; // 跳过无效的手术时间
            }

            // 6. 查询术前小结及术前讨论结论记录
            try {
                $records = EMR_BL_BL01::query()
                    ->where('JZHM', $ZYH)
                    ->whereIn('MBLB', $recordTypes);
                if ($isMultiSurgery && !empty($surgerySTime)) {
                    $records = $records->where($ruleMap8011, '>=', $surgerySTime)->where($ruleMap8011, '<=', $surgeryStartTime);
                } else {
                    $records = $records->where($ruleMap8011, '<=', $surgeryStartTime);
                }
                $records = $records->get()->toArray();
            } catch (\Exception $e) {
                Log::error("rule-$ruleId-$ZYH-查询术前小结error: " . $e->getMessage());
                continue; // 查询出错，跳过当前手术记录
            }

            // 如果没有找到记录
            if (empty($records)) {
                continue;
            }

            $basis = [];
            $basis[] = "手术名称【" . $surgeryName . "】";
            $basis[] = "手术开始时间【" . $surgeryStartTime . "】";
            $basis[] = "手术结束时间【" . $surgeryEndTime . "】";
            $basis[] = "术者【" . $surgeon . "】";
            $isbh = false;

            // 7. 检查每条记录的签名中是否包含术者code
            foreach ($records as $record) {
                $blbh = $record['BLBH'] ?? ''; // 病历编号

                if (empty($blbh)) {
                    continue;
                }

                // 查询该病历的所有签名
                try {
                    $blsy = EMR_BL_BLSY::query()
                        ->where('BLBH', $blbh)
                        ->where('FG_ACTIVE', 1)
                        ->pluck('SYYS')
                        ->toArray();

                    if (!empty($blsy)) {
                        $blsyNames = Staff::query()->whereIn('code', $blsy)->pluck('name')->toArray();
                        foreach ($blsyNames as $blsyName) {
                            if (strpos($blsyName, $surgeon) !== false || strpos($surgeon, $blsyName) !== false) {
                                $isbh = true;
                                break;
                            }
                        }
                        if (!$isbh) {
                            $basis[] = "【" . $record['BLMC'] . "】中无术者审核签字";
                            $basis["BLBH"] = $record['BLBH'];
                        }
                    } else {
                        $basis[] = "【" . $record['BLMC'] . "】中无术者审核签字";
                        $basis["BLBH"] = $record['BLBH'];
                    }
                } catch (\Exception $e) {
                    Log::error("rule-$ruleId-$ZYH-查询签名error: " . $e->getMessage());
                    continue;
                }
            }

            if (!$isbh) {
                $allBasisGroups[] = $basis;
            }

            $isMultiSurgery = true;
            $surgerySTime = $surgeryEndTime;
        }

        // 8. 将所有质控结果一次性插入到insertData
        if (!empty($allBasisGroups)) {
            $this->insertData[] = [
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title'],
                'basis' => json_encode($allBasisGroups, JSON_UNESCAPED_UNICODE)
            ];
        }

        return true;
    }

    /**
     * 规则1255：缺术后首程
     * @param $caseRule
     * @param $ruleId
     * @param $ZYH
     * @return bool
     * @author lzh
     * @datetime 2025-10-30
     */
    public function rule1255($caseRule, $ruleId, $ZYH)
    {
        // 1. 查询手术信息，获取手术结束时间
        try {
            //获取出入院时间
            $enterTime = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->value('AAB01');
            $exitTime = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->value('AAC01');

            // 如果没有出院时间或出院时间为默认值，使用当前时间
            if (empty($exitTime) || $exitTime == '1970-01-01 00:00:00' || $exitTime == '0000-00-00 00:00:00') {
                $exitTime = date('Y-m-d H:i:s');
            }

            // 3. 计算出院时间-入院时间
            $enterDate = date('Y-m-d', strtotime($enterTime));
            $exitDate = date('Y-m-d', strtotime($exitTime));
            $diffDays = (strtotime($exitDate) - strtotime($enterDate)) / (24 * 3600);
            if ($diffDays <= 1) {
                return true;
            }


            $ssapData = SM_SSAP::query()->where('ZYH', '=', $ZYH)->get()->toArray();
            if (empty($ssapData)) {
                return true; // 没有手术记录，不进行质控
            }
        } catch (\Exception $e) {
            Log::error("rule-$ruleId-$ZYH-error: " . $e->getMessage());
            return true; // 查询出错，跳过质控
        }

        // 2. 获取术后首次病程记录的配置
        $ruleMap8017 = RuleWordMap::query()->where('id', '=', 8017)->value('keyword');
        $ruleMap8017 = !empty($ruleMap8017) ? $ruleMap8017 : '42'; // 术后首次病程记录MBLB值
        //逗号分割
        if (strpos($ruleMap8017, ',') !== false) {
            $recordTypes = explode(',', $ruleMap8017);
        } else {
            $recordTypes = [$ruleMap8017];
        }

        $ruleMap8010 = RuleWordMap::query()->where('id', '=', 8010)->value('keyword');
        $ruleMap8010 = !empty($ruleMap8010) ? $ruleMap8010 : '294'; // 病程记录BLLB值

        $ruleMap8018 = RuleWordMap::query()->where('id', '=', 8018)->value('keyword');
        $ruleMap8018 = !empty($ruleMap8018) ? $ruleMap8018 : '术后首次病程记录'; // 病程记录标题包含的关键词

        // 3. 获取查询字段
        // 获取执行时间字段名
        $ruleMap8011 = RuleWordMap::query()->where('id', '=', 8011)->value('keyword');
        $ruleMap8011 = !empty($ruleMap8011) ? $ruleMap8011 : 'ZXSJ'; // 执行时间字段名

        // 获取首次签名时间字段名
        $firstBlsyTimeField = RuleWordMap::query()->where('id', '=', 8002)->value('keyword');
        $firstBlsyTimeField = !empty($firstBlsyTimeField) ? $firstBlsyTimeField : 'first_blsy_time';

        //获取8047,8048配置
        $excludeKeywords8047 = RuleWordMap::getArrayById(8047);

        // 收集所有质控结果
        $allBasisGroups = [];

        // 4. 处理每个手术记录
        foreach ($ssapData as $surgery) {
            // 获取手术结束时间
            $surgeryEndTime = $surgery['JSRQ'];
            //获取当前时间
            $currentDate = Carbon::now()->toDateTimeString();
            $sixHoursAfterSurgery = date('Y-m-d H:i:s', strtotime($surgeryEndTime) + 6 * 3600);
            //如果当前时间小于手术结束时间+6小时就跳过
            if (strtotime($currentDate) < strtotime($sixHoursAfterSurgery)) {
                continue;
            }
            //手术名称
            $surgeryName = $surgery['ICD9_SSCZMC'];
            if (empty($surgery['SSRQ']) || empty($surgery['ICD9_SSCZMC']) || empty($surgery['JSRQ']) || strpos($surgery['JSRQ'], '1970-01-01') !== false || $surgery['ICD9_SSCZMC'] == 'NULL') {
                continue;
            }
            //根据手术名称查询SSCZ的SSLB
            $ssCZ = SSCZ::query()->where('SSMC', $surgeryName)->value('SSLB');
            if (empty($ssCZ)) {
                continue;
            }
            //检查是否是手术+介入
            if (!in_array($ssCZ, $excludeKeywords8047)) {
                continue;
            }
            // 检查手术结束时间是否有效
            if (empty($surgeryEndTime) || $surgeryEndTime == '1970-01-01 00:00:00' || $surgeryEndTime == '0000-00-00 00:00:00') {
                continue; // 跳过无效的手术时间
            }

            // 计算手术结束后6小时的时间点
            $sixHoursAfterSurgery = date('Y-m-d H:i:s', strtotime($surgeryEndTime) + 6 * 3600);


            //使用sql查询
            $records = null;

            $recordsmblb = EMR_BL_BL01::query()
                ->where('JZHM', $ZYH)
                ->whereIn('MBLB', $recordTypes)
                ->where($ruleMap8011, '>=', $surgeryEndTime)
                ->where($ruleMap8011, '<=', $sixHoursAfterSurgery)
                ->get()->toArray();


            if (!empty($recordsmblb)) {
                $records = $recordsmblb;
            } else {
                $recordsbllb = EMR_BL_BL01::query()
                    ->where('JZHM', $ZYH)
                    ->where('BLLB', $ruleMap8010)
                    ->where('BLMC', 'like', '%' . $ruleMap8018 . '%')
                    ->where($ruleMap8011, '>=', $surgeryEndTime)
                    ->where($ruleMap8011, '<=', $sixHoursAfterSurgery)
                    ->get()->toArray();
                $records = $recordsbllb;
            }


            // 如果没有找到记录
            if (empty($records)) {
                // 记录质控结果 - 未找到记录
                $basis = [];
                $basis[] = "(手麻)手术名称【" . $surgeryName . "】";
                $basis[] = "(手麻)手术结束时间【" . $surgeryEndTime . "】";
                $basis[] = "【术后首次病程记录】【未书写】";

                $allBasisGroups[] = $basis;
            }
        }

        // 7. 将所有质控结果一次性插入到insertData
        if (!empty($allBasisGroups)) {
            $this->insertData[] = [
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title'],
                'basis' => json_encode($allBasisGroups, JSON_UNESCAPED_UNICODE)
            ];
        }

        return true;
    }

    /**
     * 规则1256：术后首程--非参加手术医师书写
     * @param $caseRule
     * @param $ruleId
     * @param $ZYH
     * @return bool
     * @author lzh
     * @datetime 2025-10-30
     */
    public function rule1256($caseRule, $ruleId, $ZYH)
    {
        // 1. 查询手术信息，从bllb303表获取手术开始时间和术者
        try {
            //获取出入院时间
            $enterTime = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->value('AAB01');
            $exitTime = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->value('AAC01');

            if (empty($exitTime)) {
                $exitTime = date('Y-m-d H:i:s');
            }

            // 如果没有出院时间或出院时间为默认值，使用当前时间
            if (empty($exitTime) || $exitTime == '1970-01-01 00:00:00' || $exitTime == '0000-00-00 00:00:00') {
                $exitTime = date('Y-m-d H:i:s');
            }

            // 计算出院时间-入院时间
            $enterDate = date('Y-m-d', strtotime($enterTime));
            $exitDate = date('Y-m-d', strtotime($exitTime));
            $diffDays = (strtotime($exitDate) - strtotime($enterDate)) / (24 * 3600);
            if ($diffDays <= 1) {
                return true;
            }

            // 从bllb303表查询手术信息
            $surgeryData = Bllb303::query()
                ->where('ZYH', '=', $ZYH)
                ->get()
                ->toArray();

            if (empty($surgeryData)) {
                return true; // 没有手术记录，不进行质控
            }
        } catch (\Exception $e) {
            Log::error("rule-$ruleId-$ZYH-error: " . $e->getMessage());
            return true; // 查询出错，跳过质控
        }

        // 2. 获取术后首次病程记录的配置
        $ruleMap8017 = RuleWordMap::query()->where('id', '=', 8017)->value('keyword');
        $ruleMap8017 = !empty($ruleMap8017) ? $ruleMap8017 : '42'; // 术后首次病程记录MBLB值
        //逗号分割
        if (strpos($ruleMap8017, ',') !== false) {
            $recordTypes = explode(',', $ruleMap8017);
        } else {
            $recordTypes = [$ruleMap8017];
        }

        $ruleMap8010 = RuleWordMap::query()->where('id', '=', 8010)->value('keyword');
        $ruleMap8010 = !empty($ruleMap8010) ? $ruleMap8010 : '294'; // 病程记录BLLB值

        $ruleMap8018 = RuleWordMap::query()->where('id', '=', 8018)->value('keyword');
        $ruleMap8018 = !empty($ruleMap8018) ? $ruleMap8018 : '术后首次病程记录'; // 病程记录标题包含的关键词

        // 3. 获取查询字段
        // 获取执行时间字段名
        $ruleMap8011 = RuleWordMap::query()->where('id', '=', 8011)->value('keyword');
        $ruleMap8011 = !empty($ruleMap8011) ? $ruleMap8011 : 'ZXSJ'; // 执行时间字段名

        //获取8047,8048
        $excludeKeywords8047 = RuleWordMap::getArrayById(8047);

        // 收集所有质控结果
        $allBasisGroups = [];

        // 4. 处理每个手术记录
        foreach ($surgeryData as $surgery) {
            // 获取手术开始时间、结束时间和术者
            $surgeryStartTime = $surgery['SSKSSJ']; // 手术开始时间
            $surgeryEndTime = $surgery['SSJSSJ']; // 手术结束时间
            $surgeon = $surgery['SSZ']; // 术者
            //一助
            $yz = $surgery['YIZHU'] ?? '';
            //二助
            $ez = $surgery['ERZHU'] ?? '';
            $surgeryName = $surgery['SSMC'] ?? ''; // 手术名称

            // 检查必要字段是否有效
            if (
                empty($surgeryStartTime) || empty($surgeon) || empty($surgeryName) ||
                empty($surgeryEndTime) || strpos($surgeryEndTime, '1970-01-01') !== false ||
                $surgeryName == 'NULL'
            ) {
                continue;
            }

            //根据手术名称查询SSCZ的SSLB
            $ssCZ = SSCZ::query()->where('SSMC', $surgeryName)->value('SSLB');
            if (empty($ssCZ)) {
                continue;
            }
            //检查是否是手术+介入
            if (!in_array($ssCZ, $excludeKeywords8047)) {
                continue;
            }

            // 检查手术结束时间是否有效
            if ($surgeryEndTime == '1970-01-01 00:00:00' || $surgeryEndTime == '0000-00-00 00:00:00') {
                continue; // 跳过无效的手术时间
            }


            // 计算手术结束后6小时的时间点
            $sixHoursAfterSurgery = date('Y-m-d H:i:s', strtotime($surgeryEndTime) + 6 * 3600);

            // 6. 查询术后首次病程记录
            try {
                $records = null;

                $recordsmblb = EMR_BL_BL01::query()
                    ->where('JZHM', $ZYH)
                    ->whereIn('MBLB', $recordTypes)
                    ->where($ruleMap8011, '>=', $surgeryEndTime)
                    ->where($ruleMap8011, '<=', $sixHoursAfterSurgery)
                    ->get()->toArray();

                if (!empty($recordsmblb)) {
                    $records = $recordsmblb;
                } else {
                    $recordsbllb = EMR_BL_BL01::query()
                        ->where('JZHM', $ZYH)
                        ->where('BLLB', $ruleMap8010)
                        ->where('BLMC', 'like', '%' . $ruleMap8018 . '%')
                        ->where($ruleMap8011, '>=', $surgeryEndTime)
                        ->where($ruleMap8011, '<=', $sixHoursAfterSurgery)
                        ->get()->toArray();
                    $records = $recordsbllb;
                }
            } catch (\Exception $e) {
                Log::error("rule-$ruleId-$ZYH-查询术后首程error: " . $e->getMessage());
                continue; // 查询出错，跳过当前手术记录
            }

            // 如果没有找到记录
            if (empty($records)) {
                continue;
            }

            $basis = [];
            $basis[] = "手术名称【" . $surgeryName . "】";
            $basis[] = "术者【" . $surgeon . "】";
            if ($yz) {
                $basis[] = "一助【" . $yz . "】";
            }
            if ($ez) {
                $basis[] = "二助【" . $ez . "】";
            }
            $isbh = false;

            // 7. 检查每条记录的签名中是否包含术者code
            foreach ($records as $record) {
                $blbh = $record['BLBH'] ?? ''; // 病历编号

                if (empty($blbh)) {
                    continue;
                }

                // 查询该病历的所有签名
                try {
                    $blsy = EMR_BL_BLSY::query()
                        ->where('BLBH', $blbh)
                        ->where('FG_ACTIVE', 1)
                        ->pluck('SYYS')
                        ->toArray();

                    // 检查术者code是否在签名列表中
                    if (!empty($blsy)) {
                        $blsyNames = Staff::query()->whereIn('code', $blsy)->pluck('name')->toArray();
                        foreach ($blsyNames as $blsyName) {
                            if (strpos($blsyName, $surgeon) !== false || strpos($surgeon, $blsyName) !== false) {
                                $isbh = true;
                                break;
                            }
                            if ($yz) {
                                if (strpos($blsyName, $yz) !== false || strpos($yz, $blsyName) !== false) {
                                    $isbh = true;
                                    break;
                                }
                            }
                            if ($ez) {
                                if (strpos($blsyName, $ez) !== false || strpos($ez, $blsyName) !== false) {
                                    $isbh = true;
                                    break;
                                }
                            }
                        }
                        if (!$isbh) {
                            $basis[] = "【" . $record['BLMC'] . "】非参加手术医师书写";
                            $basis["BLBH"] = $record['BLBH'];
                        }
                    } else {
                        $basis[] = "【" . $record['BLMC'] . "】非参加手术医师书写";
                        $basis["BLBH"] = $record['BLBH'];
                    }
                } catch (\Exception $e) {
                    Log::error("rule-$ruleId-$ZYH-查询签名error: " . $e->getMessage());
                    continue;
                }
            }

            if (!$isbh) {
                $allBasisGroups[] = $basis;
            }
        }

        // 8. 将所有质控结果一次性插入到insertData
        if (!empty($allBasisGroups)) {
            $this->insertData[] = [
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title'],
                'basis' => json_encode($allBasisGroups, JSON_UNESCAPED_UNICODE)
            ];
        }

        return true;
    }

    /**
     * 规则1257：抢救记录--有抢救记录无抢救医嘱
     * @param $caseRule
     * @param $ruleId
     * @param $ZYH
     * @return bool
     * @author lzh
     * @datetime 2025-10-30
     */
    public function rule1257($caseRule, $ruleId, $ZYH)
    {
        // 1. 获取抢救记录MBLB值配置
        $ruleMap8027 = RuleWordMap::query()->where('id', '=', 8027)->value('keyword');
        $ruleMap8027 = !empty($ruleMap8027) ? $ruleMap8027 : '27'; // 抢救记录MBLB值

        // 判断是否包含逗号
        if (strpos($ruleMap8027, ',') !== false) {
            $recordTypes = explode(',', $ruleMap8027);
        } else {
            $recordTypes = [$ruleMap8027];
        }

        // 2. 获取查询字段
        // 获取执行时间字段名
        $ruleMap8011 = RuleWordMap::query()->where('id', '=', 8011)->value('keyword');
        $ruleMap8011 = !empty($ruleMap8011) ? $ruleMap8011 : 'ZXSJ'; // 执行时间字段名

        // 3. 查询所有抢救记录，按ZXSJ正序排序
        try {
            $rescueRecords = EMR_BL_BL01::query()
                ->where('JZHM', $ZYH)
                ->whereIn('MBLB', $recordTypes)
                ->orderBy($ruleMap8011, 'asc')
                ->get()
                ->toArray();

            if (empty($rescueRecords)) {
                return true; // 没有抢救记录，不进行质控
            }
        } catch (\Exception $e) {
            Log::error("rule-$ruleId-$ZYH-error: " . $e->getMessage());
            return true; // 查询出错，跳过质控
        }

        // 4. 获取抢救关键词配置
        $ruleMap8026 = RuleWordMap::query()->where('id', '=', 8026)->value('keyword');
        $ruleMap8026 = !empty($ruleMap8026) ? $ruleMap8026 : '抢救'; // 抢救关键词

        // 5. 获取入院时间（用于第一条抢救记录）
        try {
            $enterTime = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->value('AAB01');
            if (empty($enterTime)) {
                $enterTime = '1970-01-01 00:00:00'; // 默认值
            }
        } catch (\Exception $e) {
            Log::error("rule-$ruleId-$ZYH-获取入院时间error: " . $e->getMessage());
            $enterTime = '1970-01-01 00:00:00';
        }

        // 收集所有质控结果
        $allBasisGroups = [];

        // 6. 处理每条抢救记录
        $previousRecordTime = null;
        foreach ($rescueRecords as $index => $record) {
            $recordTime = $record[$ruleMap8011] ?? ''; // 抢救记录标题时间
            $recordName = $record['BLMC'] ?? '抢救记录';
            $blbh = $record['BLBH'] ?? '';

            // 检查记录时间是否有效
            if (empty($recordTime) || $recordTime == '1970-01-01 00:00:00' || $recordTime == '0000-00-00 00:00:00') {
                continue;
            }

            // 7. 确定医嘱查询的时间范围
            // 第一条记录：从入院时间到记录时间
            // 后续记录：从上一条记录时间到当前记录时间
            if ($index == 0) {
                $startTime = $enterTime;
            } else {
                $startTime = $previousRecordTime;
            }
            $endTime = $recordTime;

            // 8. 从MySQL查询该时间段内医嘱名称含"抢救"的医嘱
            // 构建查询条件：医嘱名称含"抢救"且住院号为$ZYH，且在时间范围内
            $yzbQuery = Yzb::query()
                ->where('ZYH', $ZYH)
                ->where('KZSJ', '>=', $startTime)
                ->where('KZSJ', '<=', $endTime)
                ->get()->toArray();
            $yzbData = [];
            foreach ($yzbQuery as $record) {
                if (strpos($record['YZMC'], $ruleMap8026) !== false) {
                    $yzbData[] = $record;
                    break;
                }
            }

            // 9. 检查是否有抢救医嘱
            if (empty($yzbData)) {
                // 没有找到抢救医嘱，记录质控结果
                $basis = [];
                $basis[] = "抢救记录【" . $recordName . "】";
                $basis[] = "记录时间【" . $recordTime . "】";
                if ($index == 0) {
                    $basis[] = "入院时间【" . $enterTime . "】至记录时间无抢救医嘱";
                } else {
                    $basis[] = "上条记录时间【" . $previousRecordTime . "】至本次记录时间无抢救医嘱";
                }
                if (!empty($blbh)) {
                    $basis["BLBH"] = $blbh;
                }
                $allBasisGroups[] = $basis;
            }

            // 更新上一条记录的时间
            $previousRecordTime = $recordTime;
        }

        // 10. 将所有质控结果一次性插入到insertData
        if (!empty($allBasisGroups)) {
            $this->insertData[] = [
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title'],
                'basis' => json_encode($allBasisGroups, JSON_UNESCAPED_UNICODE)
            ];
        }

        return true;
    }

    /**
     * 规则1258：术前小结--无术者审核签字
     * 查询所有术前小结，提取hjnr，然后提取手术者之后的内容，
     * 根据blbh关联blsy获取SYYS，关联staff表的code获取name，
     * 看看上面的文本是否包含，有一个包含就算有
     * @param $caseRule
     * @param $ruleId
     * @param $ZYH
     * @return bool
     * @author lzh
     * @datetime 2025-10-31
     */
    public function rule1258($caseRule, $ruleId, $ZYH)
    {
        // 1. 获取术前小结及术前讨论结论记录的配置
        $ruleMap8016 = RuleWordMap::query()->where('id', '=', 8016)->value('keyword');
        $ruleMap8016 = !empty($ruleMap8016) ? $ruleMap8016 : '82,304'; // 术前小结及术前讨论结论记录MBLB值

        // 判断是否包含逗号
        if (strpos($ruleMap8016, ',') !== false) {
            $recordTypes = explode(',', $ruleMap8016);
        } else {
            $recordTypes = [$ruleMap8016];
        }

        // 2. 查询所有术前小结记录
        try {
            $records = EMR_BL_BL01::query()
                ->where('JZHM', $ZYH)
                ->whereIn('MBLB', $recordTypes)
                ->get()
                ->toArray();

            if (empty($records)) {
                return true; // 没有术前小结记录，不进行质控
            }
        } catch (\Exception $e) {
            Log::error("rule-$ruleId-$ZYH-查询术前小结error: " . $e->getMessage());
            return true; // 查询出错，跳过质控
        }

        // 收集所有质控结果
        $allBasisGroups = [];

        // 3. 处理每条术前小结记录
        foreach ($records as $record) {
            $blbh = $record['BLBH'] ?? ''; // 病历编号
            $hjnr = $record['HJNR'] ?? ''; // 会诊内容
            $blmc = $record['BLMC'] ?? ''; // 病历名称

            if (empty($blbh) || empty($hjnr)) {
                continue;
            }

            // 提取"手术者"之后的内容
            $surgeonPos = mb_strpos($hjnr, '手术者');
            if ($surgeonPos === false) {
                // 如果没有找到"手术者"关键字，尝试其他可能的关键字
                $surgeonPos = mb_strpos($hjnr, '术者');
            }

            if ($surgeonPos === false) {
                // 如果没有找到术者相关关键字，跳过此记录
                continue;
            }

            // 提取术者之后的内容
            $contentAfterSurgeon = mb_substr($hjnr, $surgeonPos);

            // 4. 查询该病历的所有签名
            try {
                $blsy = EMR_BL_BLSY::query()
                    ->where('BLBH', $blbh)
                    ->where('FG_ACTIVE', 1)
                    ->pluck('SYYS')
                    ->toArray();

                if (empty($blsy)) {
                    // 没有签名记录
                    continue;
                }

                // 5. 根据SYYS（code）查询staff表获取name，检查是否在术者之后的内容中
                $hasMatch = false;
                foreach ($blsy as $syys) {
                    $staffInfo = Staff::query()
                        ->where('code', '=', $syys)
                        ->first();

                    if (!empty($staffInfo) && !empty($staffInfo['name'])) {
                        $staffName = $staffInfo['name'];
                        // 检查术者之后的内容是否包含该医生姓名
                        if (mb_strpos($contentAfterSurgeon, $staffName) !== false) {
                            $hasMatch = true;
                            break;
                        }
                    }
                }

                // 6. 如果没有找到匹配的签名医生姓名，记录质控结果
                if (!$hasMatch) {
                    $basis = [];
                    $basis[] = "病历名称【" . $blmc . "】";
                    $basis[] = "该病历中无术者审核签字";
                    $basis["BLBH"] = $blbh;
                    $allBasisGroups[] = $basis;
                }
            } catch (\Exception $e) {
                Log::error("rule-$ruleId-$ZYH-查询签名error: " . $e->getMessage());
                continue;
            }
        }

        // 7. 将所有质控结果一次性插入到insertData
        if (!empty($allBasisGroups)) {
            $this->insertData[] = [
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title'],
                'basis' => json_encode($allBasisGroups, JSON_UNESCAPED_UNICODE)
            ];
        }

        return true;
    }

    /**
     * 规则1259：手术同意书签署时间晚于术前小结及术前讨论结论记录时间
     *
     * 逻辑：
     * 1. 从术前小结中提取术前讨论时间（有术前小结，只要有一个提取不到就不质控该病历）
     * 2. 提取手术医嘱开嘱时间：医嘱名称包含【**拟***术**】
     * 3. bl01的blmc中含【手术同意书,手术知情同意书】，提取最后一次签名时间（标题时间（zxsj）在讨论时间到开瞩时间之间的）
     *
     * 质控判断：手术同意书最后一次签署时间应该在术前讨论时间到手术开嘱时间之间
     */
    public function rule1259($caseRule = [], $ruleId, $ZYH = "")
    {
        // 1. 获取术前小结及术前讨论结论记录的配置（8016对应的是MBLB，通常是82,304）
        $rule8016 = RuleWordMap::query()->where('id', 8016)->value('keyword');
        $rule8016 = !empty($rule8016) ? $rule8016 : '82,304';
        if (strpos($rule8016, ',') !== false) {
            $rule8016 = explode(',', $rule8016);
        } else {
            $rule8016 = [$rule8016];
        }

        // 获取标题时间字段名（8011配置）
        $zxsjField = RuleWordMap::query()->where('id', 8011)->value('keyword');
        $zxsjField = !empty($zxsjField) ? $zxsjField : 'ZXSJ';

        // 2. 查询术前小结记录
        $sqxjList = EMR_BL_BL01::query()
            ->where('JZHM', '=', $ZYH)
            ->whereIn('MBLB', $rule8016)
            ->get()
            ->toArray();

        if (empty($sqxjList)) {
            return true; // 没有术前小结，不质控
        }

        // 3. 从术前小结中提取术前讨论时间
        $discussionTimeList = []; // 存储每个术前小结的讨论时间
        foreach ($sqxjList as $item) {
            $blmc = $item['BLMC'];
            $blbh = $item['BLBH'];
            $zxsj = $item[$zxsjField] ?? ''; // 标题时间

            if (empty($zxsj)) {
                continue;
            }

            // 从EMR_BL_BLXG表获取病历内容
            $hjnr = EMR_BL_BLXG::query()->where('BLBH', '=', $blbh)->value('HJNR');

            if (empty($hjnr)) {
                // 有术前小结但没有内容，不质控该病历
                return true;
            }

            // blmc只保留汉字
            $blmc1 = preg_replace('/[^\\x{4e00}-\\x{9fa5}]/u', '', $blmc);
            // hjnr提取blmc1之后的内容
            $hjnr = substr($hjnr, strpos($hjnr, $blmc1));
            $hjnr = substr($hjnr, strpos($hjnr, '术前小结及术前讨论结论记录'));

            if (empty($hjnr)) {
                // 提取不到内容，不质控该病历
                return true;
            }

            // 提取hjnr中最开始的时间（秒是可选参数）
            $discussionTime = '';

            // 匹配带秒的时间格式：YYYY-MM-DD HH:MM:SS
            if (preg_match('/^(\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2})/', $hjnr, $matches)) {
                $discussionTime = $matches[1];
            }
            // 匹配不带秒的时间格式：YYYY-MM-DD HH:MM
            elseif (preg_match('/^(\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2})/', $hjnr, $matches)) {
                $discussionTime = $matches[1];
            }

            // 如果没有提取到讨论时间，不质控该病历
            if (empty($discussionTime)) {
                return true;
            }

            $discussionTimeList[] = [
                'discussion_time' => $discussionTime,
                'blmc' => $blmc,
                'blbh' => $blbh,
                'zxsj' => $zxsj
            ];
        }

        if (empty($discussionTimeList)) {
            return true; // 没有提取到任何讨论时间，不质控
        }

        // 4. 提取手术医嘱开嘱时间：医嘱名称包含【**拟***术**】
        $surgeryOrders = Yzb::query()
            ->where('ZYH', '=', $ZYH)
            ->where('YZMC', 'like', '%拟%术%')
            ->whereIn('YZZT', $this->yzzt)
            ->get(['YZMC', 'KZSJ'])
            ->toArray();

        if (empty($surgeryOrders)) {
            return true; // 没有手术医嘱，不质控
        }

        // 5. 如果数量和术前讨论数量对不上就不质控
        if (count($surgeryOrders) != count($discussionTimeList)) {
            return true;
        }

        // 6. 一对一二对二这种对比
        $basisList = [];

        for ($i = 0; $i < count($discussionTimeList); $i++) {
            $discussion = $discussionTimeList[$i];
            $order = $surgeryOrders[$i];

            $discussionTime = $discussion['discussion_time'];
            $kzsj = $order['KZSJ'];
            $yzmc = $order['YZMC'];

            $discussionTimestamp = strtotime($discussionTime);
            $kzsjTimestamp = strtotime($kzsj);

            // 7. 查询手术同意书：blmc中含【手术同意书】或【手术知情同意书】
            // 标题时间（zxsj）在讨论时间到开嘱时间之间
            $consentForms = EMR_BL_BL01::query()
                ->where('JZHM', '=', $ZYH)
                ->where(function ($query) {
                    $query->where('BLMC', 'like', '%手术同意书%')
                        ->orWhere('BLMC', 'like', '%手术知情同意书%');
                })
                ->where($zxsjField, '>=', $discussionTime)
                ->where($zxsjField, '<=', $kzsj)
                ->get(['BLBH', 'BLMC', $zxsjField])
                ->toArray();

            if (empty($consentForms)) {
                // 没有找到符合条件的手术同意书
                $basis = [];
                $basis[] = '术前讨论时间【' . $discussionTime . '】';
                $basis[] = '手术开嘱时间【' . $kzsj . '】';
                $basis[] = '医嘱名称【' . $yzmc . '】';
                $basis[] = '在讨论时间到开嘱时间之间未找到手术同意书';
                $basisList[] = $basis;
                continue;
            }

            // 8. 关联blsy取最晚的jlsj，看是否在讨论时间到开瞩时间之间
            $lastSignTime = null;
            $consentBlmc = '';

            foreach ($consentForms as $form) {
                $blbh = $form['BLBH'];
                $blmc = $form['BLMC'];

                // 查询该病历编号的所有签名记录
                $signatures = EMR_BL_BLSY::query()
                    ->where('BLBH', '=', $blbh)
                    ->where('FG_ACTIVE', '=', 1)
                    ->orderBy('JLSJ', 'desc')
                    ->first();

                if ($signatures && !empty($signatures['JLSJ'])) {
                    $jlsj = $signatures['JLSJ'];
                    $jlsjTimestamp = strtotime($jlsj);

                    // 取最晚的签名时间
                    if ($lastSignTime === null || $jlsjTimestamp > strtotime($lastSignTime)) {
                        $lastSignTime = $jlsj;
                        $consentBlmc = $blmc;
                    }
                }
            }

            // 9. 判断手术同意书最后一次签署时间是否在讨论时间到开嘱时间之间
            if ($lastSignTime === null) {
                // 没有签名记录
                $basis = [];
                $basis[] = '术前讨论时间【' . $discussionTime . '】';
                $basis[] = '手术开嘱时间【' . $kzsj . '】';
                $basis[] = '医嘱名称【' . $yzmc . '】';
                $basis[] = '手术同意书未找到签名记录';
                $basisList[] = $basis;
            } else {
                $lastSignTimestamp = strtotime($lastSignTime);

                // 判断签名时间是否在讨论时间到开嘱时间之间
                // 违反逻辑：先术前讨论，后签署知情同意书，再开具手术医嘱
                if ($lastSignTimestamp < $discussionTimestamp || $lastSignTimestamp > $kzsjTimestamp) {
                    $basis = [];
                    $basis[] = '术前讨论时间【' . $discussionTime . '】';
                    $basis[] = '手术开嘱时间【' . $kzsj . '】';
                    $basis[] = '医嘱名称【' . $yzmc . '】';
                    $basis[] = '手术同意书【' . $consentBlmc . '】';
                    $basis[] = '手术同意书最后一次签署时间【' . $lastSignTime . '】';

                    if ($lastSignTimestamp < $discussionTimestamp) {
                        $basis[] = '违反先术前讨论，后签署知情同意书，再开具手术医嘱的逻辑：手术同意书签署时间早于术前讨论时间';
                    } else {
                        $basis[] = '违反先术前讨论，后签署知情同意书，再开具手术医嘱的逻辑：手术同意书签署时间晚于手术开嘱时间';
                    }

                    $basisList[] = $basis;
                }
            }
        }

        // 10. 如果有质控结果，插入数据
        if (!empty($basisList)) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, JSON_UNESCAPED_UNICODE),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title']
            ];
        }

        return [];
    }

    /**
     * 规则1260：入院记录/现病史描述过于简单，应不少于150字
     *
     * 逻辑：
     * 1. 从bllb292表获取该住院号的现病史（XBS）字段。
     * 2. 若现病史为空则不质控。
     * 3. 若现病史长度<=150字符，记录质控结果。
     * 数据ID为1260
     * 质控依据：无
     */
    public function rule1261($caseRule = [], $ruleId, $ZYH = "")
    {
        // 查询当前住院号的入院记录（bllb292表，XBS为现病史）
        $record = Bllb292::query()->where('JZHM', $ZYH)->first();
        if (!$record) {
            // 无入院记录直接返回
            return true;
        }
        $xbs = $record->XBS ?? '';
        // 现病史字段为空时不质控
        if (empty($xbs)) {
            return true;
        }
        // 判断字符数（不是字数），mb_strlen可防止中文乱码
        if (mb_strlen($xbs, 'utf-8') < 150) {
            $basis = [];
            $basis[] = "现病史描述不足150个字符";
            $this->insertData[] = [
                'basis' => json_encode([$basis], JSON_UNESCAPED_UNICODE),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title'] ?? '',
            ];
        }
        return true;
    }

    /**
     * 规则: 病程记录描述过于简单，应不少于150字
     *
     * 逻辑：
     * 1. 从bllb294表获取该住院号的所有病程记录（假定字段 BLMC 为名称，BLNR 为内容）。
     * 2. 遍历每条记录，若BLNR实际字符数<=150，则记录质控结果。
     * 3. 数据ID可根据实际传入ruleId。
     * 4. 质控依据：{BLMC}描述过于简单
     *
     * @param array $caseRule
     * @param int $ruleId
     * @param string $ZYH
     * @return true
     */
    public function rule1262($caseRule = [], $ruleId, $ZYH = "")
    {
        $records = EMR_BL_BL01::query()->where('JZHM', $ZYH)->where('BLLB', 294)->get();

        if ($records->isEmpty()) {
            // 没有病程记录，无需质控
            return true;
        }

        foreach ($records as $record) {
            $blmc = $record->BLMC ?? '';
            // 获取病历内容（从EMR_BL_BLXG表）
            $blxg = EMR_BL_BLXG::query()->where('BLBH', $record->BLBH)->first();
            $blnr = $blxg->HJNR ?? '';

            // 病程内容为空不质控
            if (empty($blnr)) {
                continue;
            }

            // 低于150字符认为描述简单
            if (mb_strlen($blnr, 'utf-8') < 150) {
                $basis = [];
                $basis[] = "{$blmc}描述过于简单";
                $this->insertData[] = [
                    'basis' => json_encode([$basis], JSON_UNESCAPED_UNICODE),
                    'JZHM' => $ZYH,
                    'rule_id' => $ruleId,
                    'code' => 'rule_' . $ruleId,
                    'error_field' => $blmc,
                ];
            }
        }
        return true;
    }

    /**
     * 规则: 手术记录描述过于简单，应不少于150字
     *
     * 逻辑：
     * 1. 从bllb292表获取该住院号的所有手术记录（假定字段 BLMC 为名称，HJNR 为内容）。
     * 2. 遍历每条记录，若 HJNR 实际字符数 <= 150，则记录质控结果。
     * 3. 数据ID可根据实际传入 ruleId。
     * 4. 质控依据：{BLMC}描述过于简单
     *
     * @param array $caseRule
     * @param int $ruleId
     * @param string $ZYH
     * @return true
     */
    public function rule1263($caseRule = [], $ruleId, $ZYH = "")
    {
        // 获取该住院号所有手术记录
        $records = EMR_BL_BL01::query()->where('JZHM', $ZYH)->where('BLLB', 303)->get();

        if ($records->isEmpty()) {
            // 没有手术记录，无需质控
            return true;
        }

        foreach ($records as $record) {
            $blmc = $record->BLMC ?? '';
            // 获取病历内容（从EMR_BL_BLXG表）
            $blxg = EMR_BL_BLXG::query()->where('BLBH', $record->BLBH)->first();
            $blnr = $blxg->HJNR ?? '';

            // 内容为空不质控
            if (empty($hjnr)) {
                continue;
            }

            // 低于150字符认为描述简单
            if (mb_strlen($hjnr, 'utf-8') < 150) {
                $basis = [];
                $basis[] = "{$blmc}描述过于简单";
                $this->insertData[] = [
                    'basis' => json_encode([$basis], JSON_UNESCAPED_UNICODE),
                    'JZHM' => $ZYH,
                    'rule_id' => $ruleId,
                    'code' => 'rule_' . $ruleId,
                    'error_field' => $blmc,
                ];
            }
        }
        return true;
    }

    /**
     * 规则1268：首次病程记录诊断依据年龄错误
     *
     * 前置条件：
     * 1. patient_info_v2 表的 AAA04 不为空
     * 2. 首次病程记录诊断依据（bllb294_295，ZYH 关联，取 ZDYJ）中存在“岁”
     *
     * 逻辑：
     * 1. 从 patient_info_v2 表中根据 ZYH 获取 AAA04（首页年龄）
     * 2. 从 bllb294_295 表中根据 ZYH 获取 ZDYJ（首次病程记录诊断依据）
     * 3. 若 ZDYJ 中包含“岁”，则提取“岁”前面的数字作为病程记录中的年龄
     * 4. 将提取的年龄与 AAA04 对比，若不一致则触发质控
     *
     * @param array $caseRule
     * @param int $ruleId
     * @param string $ZYH
     * @return array
     */
    public function rule1268($caseRule = [], $ruleId, $ZYH = "")
    {
        $frontPageAge = null;

        // 1. 获取 v2 表的 AAA04，如果为空就去查 bllb292 的 NL
        $AAA04 = PatientInfoV2::query()->where('ZYH', $ZYH)->value('AAA04');
        $ageStr = '';
        if ($AAA04 !== '' && $AAA04 !== null) {
            $ageStr = $AAA04;
        } else {
            $NL = Bllb292::query()->where('ZYH', $ZYH)->value('NL');
            if ($NL !== '' && $NL !== null) {
                $ageStr = $NL;
            }
        }

        // 提取数字，首页的年龄使用这个
        if ($ageStr !== '') {
            if (preg_match('/(\d+)/', $ageStr, $matches)) {
                $frontPageAge = (int)$matches[1];
            }
        }

        // 如果都是空的就 return
        if ($frontPageAge === null) {
            return [];
        }

        //如果年龄大于70岁，则不质控
        if ($frontPageAge > 70) {
            return [];
        }

        // 2. 获取 rulewordmap id = 8107
        $rule8107 = RuleWordMap::query()->where('id', 8107)->value('keyword');
        $recordTypes = [];
        if (!empty($rule8107)) {
            if (strpos($rule8107, ',') !== false) {
                $recordTypes = explode(',', $rule8107);
            } else {
                $recordTypes = [$rule8107];
            }
        }

        // 3. 查询 EMR_BL_BL01，mblb = id8107 或者 blmc 包含“首次查房”
        $records = EMR_BL_BL01::query()
            ->where('JZHM', $ZYH)
            ->where(function ($query) use ($recordTypes) {
                if (!empty($recordTypes)) {
                    $query->whereIn('MBLB', $recordTypes)
                        ->orWhere('BLMC', 'like', '%首次查房%');
                } else {
                    $query->where('BLMC', 'like', '%首次查房%');
                }
            })
            ->get();

        if ($records->isEmpty()) {
            return [];
        }

        $allBasisGroups = [];

        // 4. 循环，根据 blbh 获取 hjnr
        foreach ($records as $record) {
            $blbh = $record->BLBH ?? '';
            $blmc = $record->BLMC ?? '';

            if (empty($blbh)) {
                continue;
            }

            $hjnr = EMR_BL_BLXG::query()->where('BLBH', $blbh)->value('HJNR');


            // 删除“鉴别诊断”到“诊疗计划”之间的内容
            $replaceCount = 0;
            $pattern = '/鉴别诊断(.*?)诊疗计划/su';
            $hjnr = preg_replace($pattern, '', $hjnr, 1, $replaceCount);

            // 5. 查询是否包含“岁”，如果不包含就跳过
            if (empty($hjnr) || mb_strpos($hjnr, '岁') === false) {
                continue;
            }

            // 6. 如果包含提取年龄比较
            $recordAge = null;
            if (preg_match('/(\d+)\s*岁/u', $hjnr, $matches)) {
                $recordAge = (int)$matches[1];
            }

            if ($recordAge === null) {
                continue;
            }

            // 7. 如果不符添加质控依据（仅记录病历中的错误提示）
            if ($recordAge !== $frontPageAge) {
                $allBasisGroups[] = '【' . $blmc . '】中年龄为【' . $recordAge . '】，不一致';
            }
        }

        // 8. 汇总插入质控数据
        if (!empty($allBasisGroups)) {
            $finalBasis = [];
            // 首页年龄提示仅保留一行，放在最前面
            $finalBasis[] = '病案首页/入院记录中年龄为【' . $frontPageAge . '】';
            // 合并所有发现的病程年龄错误
            foreach ($allBasisGroups as $errorMsg) {
                $finalBasis[] = $errorMsg;
            }

            $this->insertData[] = [
                'basis' => json_encode([$finalBasis], JSON_UNESCAPED_UNICODE),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title'] ?? '',
            ];
        }

        return [];
    }

    /**
     * 规则: 未经上级医师同意，不得为患者办理出院手续
     *
     * 逻辑：
     * 1. 出院当日或前一日需有主治医师(及以上)查房/签名，或“请示上级医师,经上级医师同意”记载
     * 2. brry表获取出院时间，bllb294为病程（BLMC为文书名称，RQ为日期，BLNR为内容，QMR为签名医师，QMRZC为签名医师职称）
     *
     * @param array $caseRule
     * @param int $ruleId
     * @param string $ZYH
     * @return true
     */
    public function rule1264($caseRule = [], $ruleId, $ZYH = "")
    {
        // 获取出院时间
        $brry = ZY_BRRY::query()->where('ZYH', $ZYH)->first();
        if (!$brry || empty($brry->AAC01)) {
            // 无出院记录，不质控
            return true;
        }
        $cyDate = substr($brry->AAC01, 0, 10);

        // 构造当日和前一日
        $prevDate = date('Y-m-d 00:00:00', strtotime($cyDate . ' -1 day'));

        // 查询EMR_BL_BL01表中bllb为294的出院当日或前一日的病程记录
        $records = EMR_BL_BL01::query()
            ->where('JZHM', $ZYH)
            ->where('BLLB', 294)
            ->where('ZXSJ', '>=', $prevDate)
            ->get();

        $isQualified = false;

        foreach ($records as $record) {
            // 获取病历内容（从EMR_BL_BLXG表）
            $blxg = EMR_BL_BLXG::query()->where('BLBH', $record->BLBH)->first();
            $blnr = $blxg->HJNR ?? '';

            // 获取签名医师信息（从EMR_BL_BLSY表）
            $blsy = EMR_BL_BLSY::query()->where('BLBH', $record->BLBH)->where('FG_ACTIVE', 1)->first();
            $qmzc = '';
            if ($blsy && !empty($blsy->SYYS)) {
                $staff = Staff::query()->where('code', $blsy->SYYS)->first();
                if ($staff && !empty($staff->ygjb_text)) {
                    $qmzc = $staff->ygjb_text;
                }
            }

            // 1. 病历内容含请示及同意
            if (
                mb_strpos($blnr, '请示上级医师') !== false &&
                mb_strpos($blnr, '经上级医师同意') !== false
            ) {
                $isQualified = true;
                break;
            }

            // 2. 签名医师职称包含主治/主任
            if (
                !empty($qmzc) &&
                (mb_strpos($qmzc, '主任') !== false || mb_strpos($qmzc, '主治') !== false)
            ) {
                $isQualified = true;
                break;
            }
        }

        if (!$isQualified) {
            $basis = [];
            $basis[] = "出院当日或前日无经上级医师同意出院的病程记录";
            $this->insertData[] = [
                'basis' => json_encode([$basis], JSON_UNESCAPED_UNICODE),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => '出院病程',
            ];
        }
        return true;
    }

    /**
     * 规则1269：出院时间在入院时间之前
     *
     * 逻辑：
     * 1. 从 bllb1 表根据 ZYH 获取出院日期 CYRQ 和入院日期 RYRQ
     * 2. 若 CYRQ、RYRQ 任一为空，则不质控
     * 3. 若 CYRQ < RYRQ，则认为出院时间早于入院时间，触发质控
     *
     * @param array $caseRule
     * @param int $ruleId
     * @param string $ZYH
     * @return array
     */
    public function rule1269($caseRule = [], $ruleId, $ZYH = "")
    {
        $basisList = [];

        // 1. 从 bllb1 获取出院日期和入院日期
        $bl01 = Bllb1::query()->where("ZYH", $ZYH)->first();
        if (empty($bl01)) {
            return [];
        }

        $bl01 = $bl01->toArray();
        $CYRQ = $bl01['CYRQ'] ?? '';
        $RYRQ = $bl01['RYRQ'] ?? '';

        // 2. 任一为空则不质控
        if (empty($CYRQ) || empty($RYRQ)) {
            return [];
        }

        // 3. 时间比较：CYRQ 早于 RYRQ 时质控
        $cyTimestamp = strtotime($CYRQ);
        $ryTimestamp = strtotime($RYRQ);

        // 若任一转换失败，则不质控
        if ($cyTimestamp === false || $ryTimestamp === false) {
            return [];
        }

        if ($cyTimestamp < $ryTimestamp) {
            $basis = [];
            $basis[] = '入院时间【' . $RYRQ . '】';
            $basis[] = '出院时间【' . $CYRQ . '】';
            $basis[] = '出院时间早于入院时间';
            $basisList[] = $basis;
        }

        if (!empty($basisList)) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, JSON_UNESCAPED_UNICODE),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title'] ?? '',
            ];
        }

        return [];
    }

    public function rule1286($caseRule = [], $ruleId, $ZYH = "")
    {
        $rule20021 = RuleWordMap::getArrayById(20021);
        $rule20021 = $rule20021 ?: ['病危', '病重'];

        $rule20022 = RuleWordMap::getArrayById(20022);
        $rule20022 = $rule20022 ?: ['病危', '病重告知书'];

        $yzList = Yzb::query()->where('ZYH', $ZYH)->where(function ($query) use ($rule20021) {
            foreach ($rule20021 as $k => $v) {
                $query->orWhere('YZMC', 'like', "%{$v}%");
            }
        })->get()->toArray();

        if (!$yzList) return true;

        $blList = EMR_BL_BL01::query()
            ->where('JZHM', $ZYH)->whereIn('BLLB', ['2000185', '329'])
            ->select(["BLBH", "JZHM"])
            ->where(function ($query) use ($rule20022) {
                foreach ($rule20022 as $k => $v) {
                    $query->orWhere('BLMC', 'like', "%{$v}%");
                }
            })->first();

        if (!$blList) {
            $this->insertData[] = [
                'basis' => json_encode([['医嘱【' . $yzList[0]['YZMC'] . '】', '医嘱时间【' . $yzList[0]['KZSJ'] . '】', '病危患者无病危告知书']], JSON_UNESCAPED_UNICODE),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title'] ?? '',
            ];
        }
        return true;
    }

    public function rule1287($caseRule = [], $ruleId, $ZYH = "")
    {
        $rule20022 = RuleWordMap::getFirstById(20022);
        $rule20022 = $rule20022 ?: ['病危', '病重告知书'];

        $blList = EMR_BL_BL01::query()->where('JZHM', $ZYH)->whereIn('BLLB', ['2000185', '329'])->select(["BLBH", "JZHM", 'BLMC'])->where(function ($query) use ($rule20022) {
            if (!isset($rule20022['keyword'])) {
                foreach ($rule20022 as $k => $v) {
                    $query->orWhere('BLMC', 'like', "%{$v}%");
                }
            } else {
                $query->where('BLMC', 'like', "%{$rule20022['keyword']}%");
            }
        })->get()->toArray();

        if (empty($blList)) return true;

        $is_exist = true;
        $blmc = '';
        foreach ($blList as $val) {
            $blsy_res = EMR_BL_BLSY::query()->select()
                ->where('BLBH', $val['BLBH'])
                ->where('QMLX', 2)->first();

            $blmc = "【{$val['BLMC']}】";
            if (!$blsy_res) {
                $is_exist = false;
                break;
            }
        }

        if (!$is_exist) {
            $this->insertData[] = [
                'basis' => json_encode([["{$blmc}无患方签名"]], JSON_UNESCAPED_UNICODE),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title'] ?? '',
            ];
        }

        return true;
    }

    public function rule1288($caseRule = [], $ruleId, $ZYH = "")
    {

        $rule20020 = RuleWordMap::getFirstById(20020);
        $rule20020 = $rule20020 ?: ['实习', '见习', '规培', '研究生', '博士生'];

        $blList = EMR_BL_BL01::query()->select(["BLBH", "JZHM"])
            ->where('JZHM', $ZYH)->where('BLLB', '294')->get()->toArray();
        if (empty($blList)) return true;

        $staff = Staff::query()->pluck('name', 'code')->toArray();
        $basisList = [];
        foreach ($blList as $k => $v) {
            $is_exist = true;
            $blsy_res = EMR_BL_BLSY::query()->where('BLBH', $v['BLBH'])->get(['SYYS'])->toArray();
            if (!$blsy_res) {
                continue;
            }
            foreach ($blsy_res as $item) {
                // SYYS存在，并且SYYS不包含rule20020中的任何关键字
                if (!empty($staff[$item['SYYS']])) {
                    foreach ($rule20020 as $keyword) {
                        if (strpos($staff[$item['SYYS']], $keyword) === false) {
                            $is_exist = false;
                            break;
                        }
                    }
                }
                if (!$is_exist) {
                    break;
                }
            }
            if ($is_exist) {
                $basisList[] = [["【{$v['BLMC']}】为非执业医师签名"]];
                break;
            }
        }

        if (!empty($basisList)) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, JSON_UNESCAPED_UNICODE),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title'] ?? '',
            ];
        }

        return true;
    }

    public function rule1297($caseRule = [], $ruleId, $ZYH = "")
    {

        $rule20037 = RuleWordMap::getArrayById(20037);
        $rule20037 = $rule20037 ?: ['实习', '见习', '规培', '研究生', '博士生'];

        $yzList = Yzb::query()->where('ZYH', $ZYH)->whereNotNull('KZYS')->where('KZYS', '!=', '')->get()->toArray();

        $basisList = [];
        $staff = Staff::query()->get()->toArray();
        $staff = array_column($staff, NULL, 'code');
        foreach ($yzList as $k => $v) {
            $basis = [];
            if (empty($staff[$v['KZYS']])) {
                continue;
            }
            // 判断$v['staff']['ygjb_text']是否包含$rule20037中的任何一个关键字
            $hasKeyword = false;
            if (!empty($staff[$v['KZYS']]['ygjb_text']) && !empty($rule20037)) {
                foreach ($rule20037 as $keyword) {
                    if (strpos($staff[$v['KZYS']]['ygjb_text'], $keyword) !== false) {
                        $hasKeyword = true;
                        break;
                    }
                }
            }
            if ($hasKeyword) {
                $basis[] = '医嘱名称【' . $v['YZMC'] . '】';
                $basis[] = "开嘱医师【{$staff[$v['KZYS']]['name']}({$staff[$v['KZYS']]['base_code']})({$staff[$v['KZYS']]['ygjb_text']})】非执业医师";
            }
            if (!empty($basis)) {
                $basisList[] = $basis;
            }
        }

        if (count($basisList) > 0) {
            $this->insertData[] = [
                'basis' => json_encode($basisList, JSON_UNESCAPED_UNICODE),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title'] ?? '',
            ];
        }

        return true;
    }

    /**
     * 规则1302：患者入院48小时内，无主任/主治医师查房记录
     * 适用对象：全院住院患者
     * 触发条件：当前时间 - 入院时间（BRRY.AAB01）> 48小时
     * 判定标准（合格）：
     *   1. bl01中存在 bllb=294 的病程记录
     *   2. 病历标题时间（zxsj）≤ 入院时间 + 72小时
     *   3. 病历名称（blmc）包含 rulewordmap id=8117 的关键词（三级,主任,主治,二级）
     *
     * @param array $caseRule
     * @param int $ruleId
     * @param string $ZYH
     * @return bool
     */
    public function rule1302($caseRule = [], $ruleId, $ZYH = "")
    {
        // 第一步：查询患者入院时间（BRRY表的AAB01字段）
        $brry = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->get(['AAB01', 'AAC01'])->toArray();
        $enterTime = $brry[0]['AAB01'] ?? '';
        $outTime = $brry[0]['AAC01'] ?? '';
        // 入院时间为空则跳过，不做质控判定
        if (empty($enterTime)) {
            return true;
        }

        if ($outTime && strtotime($outTime) - strtotime($enterTime) <= 48 * 60 * 60) {
            return true;
        }


        // 第二步：计算当前时间与入院时间的差值（小时）
        $enterCarbon = Carbon::parse($enterTime);
        $nowCarbon = Carbon::now();
        $diffHours = $enterCarbon->diffInHours($nowCarbon, false);
        // 触发条件：入院超过48小时才触发此规则，未满48小时不检查
        if ($diffHours <= 48) {
            return true;
        }

        // 第三步：计算病程记录的截止时间（入院时间 + 72小时）
        $deadline72h = $enterCarbon->copy()->addHours(72)->toDateTimeString();

        // 第四步：获取字典配置 rulewordmap id=8117 的关键词（三级,主任,主治,二级）
        $ruleMap8117 = RuleWordMap::getArrayById(8117);
        // 第五步：查询病历表 bl01 中符合条件的病程记录
        // 条件：住院号匹配、病历类别=294（病程记录）、标题时间≤入院后72小时
        $bl01List = EMR_BL_BL01::query()
            ->where('JZHM', $ZYH)
            ->where('BLLB', '294')
            ->where('ZXSJ', '<=', $deadline72h)
            ->get()
            ->toArray();

        // 情况一：入院72小时内完全没有病程记录，上报缺陷
        if (empty($bl01List)) {
            $this->insertData[] = [
                'basis' => json_encode([["入院时间【{$enterTime}】，48小时内无主任/主治医师查房记录"]], JSON_UNESCAPED_UNICODE),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title'] ?? '',
            ];
            return true;
        }

        // 第六步：遍历病程记录，检查病历名称（BLMC）是否包含主任/主治关键词
        $staff = Staff::query()->get()->toArray();
        $staff = array_column($staff, NULL, 'code');
        $hasValidRecord = false;
        foreach ($bl01List as $record) {
            $blmc = $record['BLMC'] ?? '';
            // 逐一匹配关键词，任一关键词命中即视为存在有效查房记录
            foreach ($ruleMap8117 as $keyword) {
                if (strpos($blmc, $keyword) !== false) {
                    $hasValidRecord = true;
                    break 2; // 找到即跳出双层循环
                }
            }

            $blsy = EMR_BL_BLSY::query()->where('BLBH', $record['BLBH'])->get()->toArray();
            if ($blsy) {
                foreach ($blsy as $bl) {
                    if (empty($staff[$bl['SYYS']])) {
                        continue;
                    }
                    foreach ($ruleMap8117 as $kw) {
                        if (strpos($staff[$bl['SYYS']]['ygjb_text'], $kw) !== false) {
                            $hasValidRecord = true;
                            break 3;
                        }
                    }
                }
            }
        }

        // 情况二：有病程记录但名称中不包含主任/主治关键词，上报缺陷
        if (!$hasValidRecord) {
            $this->insertData[] = [
                'basis' => json_encode([["入院时间【{$enterTime}】，48小时内无主任/主治医师查房记录"]], JSON_UNESCAPED_UNICODE),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title'] ?? '',
            ];
        }

        return true;
    }

    /**
     * 规则1303：患者入院72小时内，无主任（副主任）医师查房记录
     * 适用对象：全院住院患者
     * 触发条件：当前时间 - 入院时间（BRRY.AAB01）> 72小时
     * 判定标准（合格）：
     *   1. bl01中存在 bllb=294 的病程记录
     *   2. 病历标题时间（zxsj）≤ 入院时间 + 72小时
     *   3. 病历名称（blmc）包含 rulewordmap id=8118 的关键词（三级,主任）
     *
     * @param array $caseRule 规则配置数组
     * @param int $ruleId 规则ID
     * @param string $ZYH 住院号
     * @return bool
     */
    public function rule1303($caseRule = [], $ruleId, $ZYH = "")
    {
        // 第一步：查询患者入院时间（BRRY表的AAB01字段）
        $brry = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->get(['AAB01', 'AAC01'])->toArray();
        $enterTime = $brry[0]['AAB01'] ?? '';
        $outTime = $brry[0]['AAC01'] ?? '';
        // 入院时间为空则跳过，不做质控判定
        if (empty($enterTime)) {
            return true;
        }

        if ($outTime && strtotime($outTime) - strtotime($enterTime) <= 72 * 60 * 60) {
            return true;
        }

        // 第二步：计算当前时间与入院时间的差值（小时）
        $enterCarbon = Carbon::parse($enterTime);
        $nowCarbon = Carbon::now();
        $diffHours = $enterCarbon->diffInHours($nowCarbon, false);
        // 触发条件：入院超过72小时才触发此规则，未满72小时不检查
        if ($diffHours <= 72) {
            return true;
        }

        // 第三步：计算病程记录的截止时间（入院时间 + 72小时）
        $deadline72h = $enterCarbon->copy()->addHours(72)->toDateTimeString();

        // 第四步：获取字典配置 rulewordmap id=8118 的关键词（三级,主任）
        $ruleMap8118 = RuleWordMap::getArrayById(8118);
        // 第五步：查询病历表 bl01 中符合条件的病程记录
        // 条件：住院号匹配、病历类别=294（病程记录）、标题时间≤入院后72小时
        $bl01List = EMR_BL_BL01::query()
            ->where('JZHM', $ZYH)
            ->where('BLLB', '294')
            ->where('ZXSJ', '<=', $deadline72h)
            ->get()
            ->toArray();

        // 情况一：入院72小时内完全没有病程记录，上报缺陷
        if (empty($bl01List)) {
            $this->insertData[] = [
                'basis' => json_encode([["入院时间【{$enterTime}】，72小时内无主任（副主任）医师查房记录"]], JSON_UNESCAPED_UNICODE),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title'] ?? '',
            ];
            return true;
        }

        // 第六步：遍历病程记录，检查病历名称（BLMC）是否包含主任级别关键词
        $staff = Staff::query()->get()->toArray();
        $staff = array_column($staff, NULL, 'code');
        $hasValidRecord = false;
        foreach ($bl01List as $record) {
            $blmc = $record['BLMC'] ?? '';
            // 逐一匹配关键词，任一关键词命中即视为存在有效查房记录
            foreach ($ruleMap8118 as $keyword) {
                if (strpos($blmc, $keyword) !== false) {
                    $hasValidRecord = true;
                    break 2; // 找到即跳出双层循环
                }
            }

            $blsy = EMR_BL_BLSY::query()->where('BLBH', $record['BLBH'])->get()->toArray();
            if ($blsy) {
                foreach ($blsy as $bl) {
                    if (empty($staff[$bl['SYYS']])) {
                        continue;
                    }
                    foreach ($ruleMap8118 as $kw) {
                        if (strpos($staff[$bl['SYYS']]['ygjb_text'], $kw) !== false) {
                            $hasValidRecord = true;
                            break 3;
                        }
                    }
                }
            }
        }

        // 情况二：有病程记录但名称中不包含主任（副主任）关键词，上报缺陷
        if (!$hasValidRecord) {
            $this->insertData[] = [
                'basis' => json_encode([["入院时间【{$enterTime}】，72小时内无主任（副主任）医师查房记录"]], JSON_UNESCAPED_UNICODE),
                'JZHM' => $ZYH,
                'rule_id' => $ruleId,
                'code' => 'rule_' . $ruleId,
                'error_field' => $caseRule[$ruleId]['title'] ?? '',
            ];
        }

        return true;
    }
}
