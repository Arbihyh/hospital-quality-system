<?php

namespace App\Services;

use App\Model\CaseQuality;
use App\Model\CaseRule;
use App\Model\PacsWash;
use App\Model\PatientInfo;
use App\Model\Setting;
use App\Services\ElasticsearchService;

/**
 * 病例分析
 */
class UltrasonicService
{
    public $caseRule = [];

    public function __construct()
    {

        //所有添加的质控规则
        $caseRule = CaseRule::query()->get()->toArray();
        $this->caseRule = array_column($caseRule, null, 'id');

    }

    /**
     *
     * update patient_info set is_defect=0;
     * update setting set content=0 where name="quality_last_zyh";
     * truncate `case_quality`;
     *
     */
    public function qualityContrl()
    {
        // 获取规则
        $caseRule = $this->caseRule;

        // 获取最后一次质控的住院号
        $setName = 'quality_chaosheng_last_id';
        $lastId = Setting::query()->where('name', '=', $setName)->pluck('content')->first();
        $lastId = $lastId ?: 0;

        // 获取新想需要质控的病例信息
        $piesService = new ElasticsearchService('patient_info');
        while (true) {
            $params = $piesService->clearMust()
                ->queryByMust(['range' => ['AAC01' => ['gt' => "2022-01-01 00:00:00"]]])
                ->queryByMust(['range' => ['MED_REC_ID' => ['gt' => $lastId]]])
                ->paginate(1, 1000)
                ->orderBy('MED_REC_ID', 'asc')
                ->source(['MED_REC_ID', 'AAC04', 'AAB01', 'AAC01', 'AAA28'])
                ->getParams();
            $res = app('es')->search($params);
            $resData = $piesService->getDataByEs($res);
            if (empty($resData[1])) {
                break;
            }

            // 数据处理
            foreach ($resData[0] as $info) {
                $pacs = PacsWash::query()->where('JZLSH', '=', $info['AAA28'])->get()->toArray();
                if (empty($pacs)) {
                    continue;
                }
                // 获取规则
                $this->rule135($pacs, $info, $caseRule);
                $this->rule162($pacs, $info, $caseRule);
                $this->rule163($pacs, $info, $caseRule);
                $this->rule164($pacs, $info, $caseRule);
                $this->rule165($pacs, $info, $caseRule);
                $this->rule166($pacs, $info, $caseRule);
                $this->rule167($pacs, $info, $caseRule);
                $this->rule168($pacs, $info, $caseRule);
                $this->rule169($pacs, $info, $caseRule);
                $this->rule170($pacs, $info, $caseRule);
                $this->rule171($pacs, $info, $caseRule);
                $this->rule172($pacs, $info, $caseRule);
                $this->rule173($pacs, $info, $caseRule);
                $this->rule174($pacs, $info, $caseRule);
                $this->rule175($pacs, $info, $caseRule);
                $this->rule176($pacs, $info, $caseRule);
                $this->rule177($pacs, $info, $caseRule);
                $this->rule178($pacs, $info, $caseRule);
            }

            // 更新
            $lastInfo = array_pop($resData[0]);
            $lastId = $lastInfo['MED_REC_ID'];
        }
        Setting::query()->where('name', '=', $setName)->update(['content' => $lastId]);
    }

    public function rule178($pacs, $info, $caseRule)
    {
        $ruleid = 178;
        if (!$caseRule[$ruleid]['status']) {
            return false;
        }
        foreach ($pacs as $item) {
            if ($item['keyword'] != '脐带绕颈') {
                continue;
            }
            $basis = [];
            //【 U】 或 【W】 或 【锯齿】    + 【脐带】
            if (
                strpos($item['content'], 'U') === false &&
                strpos($item['content'], 'W') === false &&
                strpos($item['content'], '锯齿') === false &&
                strpos($item['content'], '脐带') === false
            ) {
                $basis[] = '检查诊断【脐带绕颈】';
                $basis[] = '影像表现：无【宫腔+蜂窝】';
            }
            if (!empty($basis)) {
                $errorNotice = [
                    'basis' => json_encode([$basis], 256),
                    'JZHM' => $info['MED_REC_ID'],
                    'rule_id' => $ruleid,
                    'code' => 'chaosheng',
                    'error_field' => $caseRule[$ruleid]['title']
                ];
                // 添加数据
                PatientInfo::query()->where('MED_REC_ID', '=', $info['MED_REC_ID'])->update(['is_defect' => 1]);
                CaseQuality::addData($errorNotice);
            }

        }
    }

    public function rule177($pacs, $info, $caseRule)
    {
        $ruleid = 177;
        if (!$caseRule[$ruleid]['status']) {
            return false;
        }
        foreach ($pacs as $item) {
            if ($item['keyword'] != '葡萄胎') {
                continue;
            }
            $basis = [];
            //宫腔+蜂窝
            if (strpos($item['content'], '宫腔') === false || strpos($item['content'], '蜂窝') === false) {
                $basis[] = '检查诊断【葡萄胎】';
                $basis[] = '影像表现：无【宫腔+蜂窝】';
            }
            if (!empty($basis)) {
                $errorNotice = [
                    'basis' => json_encode([$basis], 256),
                    'JZHM' => $info['MED_REC_ID'],
                    'rule_id' => $ruleid,
                    'code' => 'chaosheng',
                    'error_field' => $caseRule[$ruleid]['title']
                ];
                // 添加数据
                PatientInfo::query()->where('MED_REC_ID', '=', $info['MED_REC_ID'])->update(['is_defect' => 1]);
                CaseQuality::addData($errorNotice);
            }

        }
    }

    public function rule176($pacs, $info, $caseRule)
    {
        $ruleid = 176;
        if (!$caseRule[$ruleid]['status']) {
            return false;
        }
        foreach ($pacs as $item) {
            if ($item['keyword'] != '输卵管积水') {
                continue;
            }
            $basis = [];
            //【囊性+椭圆形】 或 【腊肠】或【纺锤】或【曲颈瓶】
            if (
                (strpos($item['content'], '囊样') !== false && strpos($item['content'], '椭圆形') !== false) ||
                strpos($item['content'], '腊肠') !== false ||
                strpos($item['content'], '纺锤') !== false ||
                strpos($item['content'], '曲颈瓶') !== false
            ) {
                continue;
            } else {
                $basis[] = '检查诊断【输卵管积水】';
                $basis[] = '影像表现：无【囊性+椭圆形】 或 【腊肠】或【纺锤】或【曲颈瓶】';
            }
            if (!empty($basis)) {
                $errorNotice = [
                    'basis' => json_encode([$basis], 256),
                    'JZHM' => $info['MED_REC_ID'],
                    'rule_id' => $ruleid,
                    'code' => 'chaosheng',
                    'error_field' => $caseRule[$ruleid]['title']
                ];
                // 添加数据
                PatientInfo::query()->where('MED_REC_ID', '=', $info['MED_REC_ID'])->update(['is_defect' => 1]);
                CaseQuality::addData($errorNotice);
            }

        }
    }

    public function rule175($pacs, $info, $caseRule)
    {
        $ruleid = 175;
        if (!$caseRule[$ruleid]['status']) {
            return false;
        }
        foreach ($pacs as $item) {
            if ($item['keyword'] != '卵巢冠囊肿') {
                continue;
            }
            $basis = [];
            //囊样或囊性
            if (strpos($item['content'], '囊样') === false && strpos($item['content'], '囊性') === false) {
                $basis[] = '检查诊断【卵巢冠囊肿】';
                $basis[] = '影像表现：无【囊样或囊性】';
            }
            if (!empty($basis)) {
                $errorNotice = [
                    'basis' => json_encode([$basis], 256),
                    'JZHM' => $info['MED_REC_ID'],
                    'rule_id' => $ruleid,
                    'code' => 'chaosheng',
                    'error_field' => $caseRule[$ruleid]['title']
                ];
                // 添加数据
                PatientInfo::query()->where('MED_REC_ID', '=', $info['MED_REC_ID'])->update(['is_defect' => 1]);
                CaseQuality::addData($errorNotice);
            }

        }
    }

    public function rule174($pacs, $info, $caseRule)
    {
        $ruleid = 174;
        if (!$caseRule[$ruleid]['status']) {
            return false;
        }
        foreach ($pacs as $item) {
            if ($item['keyword'] != '巧克力囊肿') {
                continue;
            }
            $basis = [];
            // 囊样回声+透声差 或细密点状回声
            if (
                (strpos($item['content'], '囊样回声') !== false && strpos($item['content'], '细密点状回声') !== false) ||
                (strpos($item['content'], '囊样回声') !== false && strpos($item['content'], '透声差') !== false)
            ) {
                continue;
            } else {
                $basis[] = '检查诊断【巧克力囊肿】';
                $basis[] = '影像表现：无【囊样回声+透声差 或细密点状回声】';
            }
            if (!empty($basis)) {
                $errorNotice = [
                    'basis' => json_encode([$basis], 256),
                    'JZHM' => $info['MED_REC_ID'],
                    'rule_id' => $ruleid,
                    'code' => 'chaosheng',
                    'error_field' => $caseRule[$ruleid]['title']
                ];
                // 添加数据
                PatientInfo::query()->where('MED_REC_ID', '=', $info['MED_REC_ID'])->update(['is_defect' => 1]);
                CaseQuality::addData($errorNotice);
            }

        }
    }

    // 畸胎瘤或畸胎类肿瘤或皮样囊肿
    public function rule173($pacs, $info, $caseRule)
    {
        $ruleid = 173;
        if (!$caseRule[$ruleid]['status']) {
            return false;
        }
        foreach ($pacs as $item) {
            if (
                strpos($item['keyword'], '畸胎瘤') === false &&
                strpos($item['keyword'], '皮样囊肿') === false &&
                strpos($item['keyword'], '畸胎类肿瘤') === false
            ) {
                continue;
            }
            $basis = [];
            // (囊性或囊样或无回声  + 细密点状回声 )
            // 或
            //高回声 或 强回声 或 混合回声 或 稍强回声  或 实性回声 或 囊实性 或 囊性部分 或 面团征 或【脂液分层征】或【瀑布征】或【垂柳征】或【星花状】或【壁立结节征】或
            //【多囊征】或【杂乱结构征】或【线条征】
            if (
                (strpos($item['content'], '囊性') !== false && strpos($item['content'], '细密点状回声') !== false) ||
                (strpos($item['content'], '囊样') !== false && strpos($item['content'], '细密点状回声') !== false) ||
                (strpos($item['content'], '无回声') !== false && strpos($item['content'], '细密点状回声') !== false) ||
                strpos($item['content'], '高回声') !== false ||
                strpos($item['content'], '强回声') !== false ||
                strpos($item['content'], '混合回声') !== false ||
                strpos($item['content'], '稍强回声') !== false ||
                strpos($item['content'], '实性回声') !== false ||
                strpos($item['content'], '囊实性') !== false ||
                strpos($item['content'], '囊性部分') !== false ||
                strpos($item['content'], '面团征') !== false ||
                strpos($item['content'], '脂液分层征') !== false ||
                strpos($item['content'], '瀑布征') !== false ||
                strpos($item['content'], '垂柳征') !== false ||
                strpos($item['content'], '星花状') !== false ||
                strpos($item['content'], '壁立结节征') !== false ||
                strpos($item['content'], '多囊征') !== false ||
                strpos($item['content'], '杂乱结构征') !== false ||
                strpos($item['content'], '线条征') !== false ||
                strpos($item['content'], '头臀径') !== false
            ) {
                continue;
            } else {
                $basis[] = '检查诊断【畸胎瘤或畸胎类肿瘤或皮样囊肿】';
                $basis[] = '影像表现：无【卵巢 或附件 + (囊性或囊样或无回声  + 细密点状回声 ) 或 高回声 或 强回声 或 混合回声 或 稍强回声  或 实性回声 或囊实性 或 囊性部分 或 面团征 或【脂液分层征】或【瀑布征】或【垂柳征】或【星花状】或【壁立结节征】或【多囊征】或【杂乱结构征】或【线条征】';
            }
            if (!empty($basis)) {
                $errorNotice = [
                    'basis' => json_encode([$basis], 256),
                    'JZHM' => $info['MED_REC_ID'],
                    'rule_id' => $ruleid,
                    'code' => 'chaosheng',
                    'error_field' => $caseRule[$ruleid]['title']
                ];
                // 添加数据
                PatientInfo::query()->where('MED_REC_ID', '=', $info['MED_REC_ID'])->update(['is_defect' => 1]);
                CaseQuality::addData($errorNotice);
            }

        }
    }

    public function rule172($pacs, $info, $caseRule)
    {
        $ruleid = 172;
        if (!$caseRule[$ruleid]['status']) {
            return false;
        }
        foreach ($pacs as $item) {
            if (strpos($item['keyword'], '早孕') === false && strpos($item['keyword'], '活胎') === false) {
                continue;
            }
            $basis = [];
            // 子宫 +  头臀径
            if (strpos($item['content'], '子宫') === false || strpos($item['content'], '头臀径') === false) {
                $basis[] = '检查诊断【早孕活胎】';
                $basis[] = '影像表现：无【子宫+头臀径】';
            }
            if (!empty($basis)) {
                $errorNotice = [
                    'basis' => json_encode([$basis], 256),
                    'JZHM' => $info['MED_REC_ID'],
                    'rule_id' => $ruleid,
                    'code' => 'chaosheng',
                    'error_field' => $caseRule[$ruleid]['title']
                ];
                // 添加数据
                PatientInfo::query()->where('MED_REC_ID', '=', $info['MED_REC_ID'])->update(['is_defect' => 1]);
                CaseQuality::addData($errorNotice);
            }

        }
    }

    public function rule170($pacs, $info, $caseRule)
    {
        $ruleid = 170;
        if (!$caseRule[$ruleid]['status']) {
            return false;
        }
        foreach ($pacs as $item) {
            if ($item['keyword'] != '早孕') {
                continue;
            }
            $basis = [];
            // 子宫+孕囊
            if (strpos($item['content'], '子宫') === false || strpos($item['content'], '孕囊') === false) {
                $basis[] = '检查诊断【早孕】';
                $basis[] = '影像表现：无【子宫+孕囊】';
            }
            if (!empty($basis)) {
                $errorNotice = [
                    'basis' => json_encode([$basis], 256),
                    'JZHM' => $info['MED_REC_ID'],
                    'rule_id' => $ruleid,
                    'code' => 'chaosheng',
                    'error_field' => $caseRule[$ruleid]['title']
                ];
                // 添加数据
                PatientInfo::query()->where('MED_REC_ID', '=', $info['MED_REC_ID'])->update(['is_defect' => 1]);
                CaseQuality::addData($errorNotice);
            }

        }
    }

    public function rule171($pacs, $info, $caseRule)
    {
        $ruleid = 171;
        if (!$caseRule[$ruleid]['status']) {
            return false;
        }
        foreach ($pacs as $item) {
            if ($item['keyword'] != '肌瘤') {
                continue;
            }
            $basis = [];
            // 子宫+低回声   或   子宫+中低回声   或   子宫+中等回声
            if (
                (
                    strpos($item['content'], '子宫') !== false &&
                    strpos($item['content'], '低回声') !== false
                ) ||
                (
                    strpos($item['content'], '子宫') !== false &&
                    strpos($item['content'], '中低回声') !== false
                ) ||
                (
                    strpos($item['content'], '子宫') !== false &&
                    strpos($item['content'], '中等回声') !== false
                )
            ) {
                continue;
            } else {
                $basis[] = '检查诊断【肌瘤】';
                $basis[] = '影像表现：无【子宫+低回声 或 子宫+中低回声 或 子宫+中等回声】';
            }
            if (!empty($basis)) {
                $errorNotice = [
                    'basis' => json_encode([$basis], 256),
                    'JZHM' => $info['MED_REC_ID'],
                    'rule_id' => $ruleid,
                    'code' => 'chaosheng',
                    'error_field' => $caseRule[$ruleid]['title']
                ];
                // 添加数据
                PatientInfo::query()->where('MED_REC_ID', '=', $info['MED_REC_ID'])->update(['is_defect' => 1]);
                CaseQuality::addData($errorNotice);
            }

        }
    }

    public function rule169($pacs, $info, $caseRule)
    {
        $ruleid = 169;
        if (!$caseRule[$ruleid]['status']) {
            return false;
        }
        foreach ($pacs as $item) {
            if ($item['keyword'] != '膀胱炎') {
                continue;
            }
            $basis = [];
            // 膀胱+厚+毛糙
            if (strpos($item['content'], '膀胱') === false || strpos($item['content'], '厚') === false || strpos($item['content'], '毛糙') === false) {
                $basis[] = '检查诊断【膀胱炎】';
                $basis[] = '影像表现：无【膀胱+厚+毛糙】';
            }
            if (!empty($basis)) {
                $errorNotice = [
                    'basis' => json_encode([$basis], 256),
                    'JZHM' => $info['MED_REC_ID'],
                    'rule_id' => $ruleid,
                    'code' => 'chaosheng',
                    'error_field' => $caseRule[$ruleid]['title']
                ];
                // 添加数据
                PatientInfo::query()->where('MED_REC_ID', '=', $info['MED_REC_ID'])->update(['is_defect' => 1]);
                CaseQuality::addData($errorNotice);
            }

        }
    }

    public function rule168($pacs, $info, $caseRule)
    {
        $ruleid = 168;
        if (!$caseRule[$ruleid]['status']) {
            return false;
        }
        foreach ($pacs as $item) {
            if ($item['keyword'] != '结石') {
                continue;
            }
            $basis = [];
            // 肾+强回声   或 肾+中强回声   或 肾+高回声   或  或 肾+中高回声
            if (
                (
                    strpos($item['content'], '肾') !== false &&
                    strpos($item['content'], '强回声') !== false
                ) ||
                (
                    strpos($item['content'], '肾') !== false &&
                    strpos($item['content'], '中强回声') !== false
                ) ||
                (
                    strpos($item['content'], '肾') !== false &&
                    strpos($item['content'], '高回声') !== false
                ) ||
                (
                    strpos($item['content'], '肾') !== false &&
                    strpos($item['content'], '中高回声') !== false
                )
            ) {
                continue;
            } else {
                $basis[] = '检查诊断【结石】';
                $basis[] = '影像表现：无【肾+强回声 或 肾+中强回声 或 肾+高回声 或 肾+中高回声】';
            }
            if (!empty($basis)) {
                $errorNotice = [
                    'basis' => json_encode([$basis], 256),
                    'JZHM' => $info['MED_REC_ID'],
                    'rule_id' => $ruleid,
                    'code' => 'chaosheng',
                    'error_field' => $caseRule[$ruleid]['title']
                ];
                // 添加数据
                PatientInfo::query()->where('MED_REC_ID', '=', $info['MED_REC_ID'])->update(['is_defect' => 1]);
                CaseQuality::addData($errorNotice);
            }

        }
    }

    public function rule167($pacs, $info, $caseRule)
    {
        $ruleid = 167;
        if (!$caseRule[$ruleid]['status']) {
            return false;
        }
        foreach ($pacs as $item) {
            if ($item['keyword'] != '肾积水') {
                continue;
            }
            $basis = [];
            // 肾窦区分离
            if (strpos($item['content'], '肾窦区分离') === false) {
                $basis[] = '检查诊断【肾积水】';
                $basis[] = '影像表现：无【肾窦区分离】';
            }
            if (!empty($basis)) {
                $errorNotice = [
                    'basis' => json_encode([$basis], 256),
                    'JZHM' => $info['MED_REC_ID'],
                    'rule_id' => $ruleid,
                    'code' => 'chaosheng',
                    'error_field' => $caseRule[$ruleid]['title']
                ];
                // 添加数据
                PatientInfo::query()->where('MED_REC_ID', '=', $info['MED_REC_ID'])->update(['is_defect' => 1]);
                CaseQuality::addData($errorNotice);
            }

        }
    }

    public function rule166($pacs, $info, $caseRule)
    {
        $ruleid = 166;
        if (!$caseRule[$ruleid]['status']) {
            return false;
        }
        foreach ($pacs as $item) {
            if ($item['keyword'] != '膀胱结石') {
                continue;
            }
            $basis = [];
            // 膀胱+强回声+活动    或   膀胱+强回声+声影
            if (
                (
                    strpos($item['content'], '膀胱') !== false &&
                    strpos($item['content'], '活动') !== false &&
                    strpos($item['content'], '强回声') !== false
                ) ||
                (
                    strpos($item['content'], '膀胱') !== false &&
                    strpos($item['content'], '强回声') !== false &&
                    strpos($item['content'], '声影') !== false
                )
            ) {
                continue;
            } else {
                $basis[] = '检查诊断【胆囊结石】';
                $basis[] = '影像表现：无【胆囊+强回声+活动 或 胆囊+活动+伴声影】';
            }
            if (!empty($basis)) {
                $errorNotice = [
                    'basis' => json_encode([$basis], 256),
                    'JZHM' => $info['MED_REC_ID'],
                    'rule_id' => $ruleid,
                    'code' => 'chaosheng',
                    'error_field' => $caseRule[$ruleid]['title']
                ];
                // 添加数据
                PatientInfo::query()->where('MED_REC_ID', '=', $info['MED_REC_ID'])->update(['is_defect' => 1]);
                CaseQuality::addData($errorNotice);
            }

        }
    }

    public function rule165($pacs, $info, $caseRule)
    {
        $ruleid = 165;
        if (!$caseRule[$ruleid]['status']) {
            return false;
        }
        foreach ($pacs as $item) {
            if ($item['keyword'] != '胆囊息肉') {
                continue;
            }
            $basis = [];
            // 胆囊+凸起+不活动   或      胆囊+凸起+无声影   或  胆囊+回声+不活动  或 胆囊+回声+无声影
            if (
                (
                    strpos($item['content'], '胆囊') !== false &&
                    strpos($item['content'], '凸起') !== false &&
                    strpos($item['content'], '不活动') !== false
                ) ||
                (
                    strpos($item['content'], '胆囊') !== false &&
                    strpos($item['content'], '凸起') !== false &&
                    strpos($item['content'], '无声影') !== false
                ) ||
                (
                    strpos($item['content'], '胆囊') !== false &&
                    strpos($item['content'], '回声') !== false &&
                    strpos($item['content'], '不活动') !== false
                ) ||
                (
                    strpos($item['content'], '胆囊') !== false &&
                    strpos($item['content'], '回声') !== false &&
                    strpos($item['content'], '无声影') !== false
                )
            ) {
                continue;
            } else {
                $basis[] = '检查诊断【胆囊息肉】';
                $basis[] = '影像表现：无【胆囊+凸起+不活动 或 胆囊+凸起+无声影 或 胆囊+回声+不活动 或 胆囊+回声+无声影】';
            }
            if (!empty($basis)) {
                $errorNotice = [
                    'basis' => json_encode([$basis], 256),
                    'JZHM' => $info['MED_REC_ID'],
                    'rule_id' => $ruleid,
                    'code' => 'chaosheng',
                    'error_field' => $caseRule[$ruleid]['title']
                ];
                // 添加数据
                PatientInfo::query()->where('MED_REC_ID', '=', $info['MED_REC_ID'])->update(['is_defect' => 1]);
                CaseQuality::addData($errorNotice);
            }

        }
    }

    public function rule164($pacs, $info, $caseRule)
    {
        $ruleid = 164;
        if (!$caseRule[$ruleid]['status']) {
            return false;
        }
        foreach ($pacs as $item) {
            if ($item['keyword'] != '胆囊结石') {
                continue;
            }
            $basis = [];
            // 胆囊+强回声+活动  或    胆囊+活动+伴声影
            if (
                (
                    strpos($item['content'], '胆囊') !== false &&
                    strpos($item['content'], '活动') !== false &&
                    strpos($item['content'], '强回声') !== false
                ) ||
                (
                    strpos($item['content'], '胆囊') !== false &&
                    strpos($item['content'], '活动') !== false &&
                    strpos($item['content'], '伴声影') !== false
                )
            ) {
                continue;
            } else {
                $basis[] = '检查诊断【胆囊结石】';
                $basis[] = '影像表现：无【胆囊+强回声+活动 或 胆囊+活动+伴声影】';
            }
            if (!empty($basis)) {
                $errorNotice = [
                    'basis' => json_encode([$basis], 256),
                    'JZHM' => $info['MED_REC_ID'],
                    'rule_id' => $ruleid,
                    'code' => 'chaosheng',
                    'error_field' => $caseRule[$ruleid]['title']
                ];
                // 添加数据
                PatientInfo::query()->where('MED_REC_ID', '=', $info['MED_REC_ID'])->update(['is_defect' => 1]);
                CaseQuality::addData($errorNotice);
            }

        }
    }

    public function rule163($pacs, $info, $caseRule)
    {
        $ruleid = 163;
        if (!$caseRule[$ruleid]['status']) {
            return false;
        }
        foreach ($pacs as $item) {
            if ($item['keyword'] != '钙化灶') {
                continue;
            }
            $basis = [];
            // 肝脏+强回声
            if (strpos($item['content'], '肝脏') === false || strpos($item['content'], '强回声') === false) {

                $basis[] = '检查诊断【钙化灶】';
                $basis[] = '影像表现：无【肝脏+强回声】';
            }
            if (!empty($basis)) {
                $errorNotice = [
                    'basis' => json_encode([$basis], 256),
                    'JZHM' => $info['MED_REC_ID'],
                    'rule_id' => $ruleid,
                    'code' => 'chaosheng',
                    'error_field' => $caseRule[$ruleid]['title']
                ];
                // 添加数据
                PatientInfo::query()->where('MED_REC_ID', '=', $info['MED_REC_ID'])->update(['is_defect' => 1]);
                CaseQuality::addData($errorNotice);
            }

        }
    }

    public function rule162($pacs, $info, $caseRule)
    {
        $ruleid = 162;
        if (!$caseRule[$ruleid]['status']) {
            return false;
        }
        foreach ($pacs as $item) {
            if ($item['keyword'] != '血管瘤' && $item['keyword'] != '错构瘤') {
                continue;
            }
            $basis = [];
            // 肝脏+高回声  或   肝脏+中高回声   或  肝脏+中等回声
            if (
                (
                    strpos($item['content'], '肝脏') !== false &&
                    strpos($item['content'], '高回声') !== false
                ) ||
                (
                    strpos($item['content'], '肝脏') !== false &&
                    strpos($item['content'], '中高回声') !== false
                ) ||
                (
                    strpos($item['content'], '肝脏') !== false &&
                    strpos($item['content'], '中等回声') !== false
                )
            ) {
                continue;
            } else {

                $basis[] = '检查诊断【血管瘤   或   错构瘤】';
                $basis[] = '影像表现：无【肝脏+高回声  或   肝脏+中高回声   或  肝脏+中等回声  】';
            }
            if (!empty($basis)) {
                $errorNotice = [
                    'basis' => json_encode([$basis], 256),
                    'JZHM' => $info['MED_REC_ID'],
                    'rule_id' => $ruleid,
                    'code' => 'chaosheng',
                    'error_field' => $caseRule[$ruleid]['title']
                ];
                // 添加数据
                PatientInfo::query()->where('MED_REC_ID', '=', $info['MED_REC_ID'])->update(['is_defect' => 1]);
                CaseQuality::addData($errorNotice);
            }

        }
    }


    public function rule135($pacs, $info, $caseRule)
    {
        $ruleid = 135;
        if (!$caseRule[$ruleid]['status']) {
            return false;
        }
        foreach ($pacs as $item) {
            if ($item['keyword'] != '脂肪肝') {
                continue;
            }
            $basis = [];
            if (
                strpos($item['content'], '实质回声细密增强') !== false ||
                (
                    strpos($item['content'], '肝内光点较密集') !== false &&
                    strpos($item['content'], '回声') !== false &&
                    strpos($item['content'], '强') !== false
                ) ||
                (
                    strpos($item['content'], '后方回声') !== false &&
                    strpos($item['content'], '衰减') !== false
                )
            ) {
                continue;
            } else {

                $basis[] = '检查诊断【脂肪肝】';
                $basis[] = '影像表现：无【实质回声细密增强 或 肝内光点较密集＋回声＋强 或 后方回声+衰减】';
            }
            if (!empty($basis)) {
                $errorNotice = [
                    'basis' => json_encode([$basis], 256),
                    'JZHM' => $info['MED_REC_ID'],
                    'rule_id' => $ruleid,
                    'code' => 'chaosheng',
                    'error_field' => $caseRule[$ruleid]['title']
                ];
                // 添加数据
                PatientInfo::query()->where('MED_REC_ID', '=', $info['MED_REC_ID'])->update(['is_defect' => 1]);
                CaseQuality::addData($errorNotice);
            }

        }
    }

    function cleanPacs($zyh=0)
    {
        // 获取规则
        $caseRule = $this->caseRule;
        // 获取最后一次质控的住院号
        $setName = 'qx_last_pacs_id';
        $lastId = Setting::query()->where('name', '=', $setName)->pluck('content')->first();
        $lastId = $lastId ?: 0;
        while (true) {
            $query = \App\Model\PACS::query()
                ->where('ExamType', '=', '06')
                ->where('id', '>', $lastId)
                ->limit(1000);
            if ($zyh) {
                $query = $query->whereIn('ZYH', $zyh);
            }
            $data = $query->get(['JZLSH', 'KDSJ', 'YXZD', 'YXBX', 'id'])->toArray();
            if (empty($data)) {
                break;
            }

            foreach ($data as $item) {
                $lastId = $item['id'];
                var_dump($lastId);
                $keyword = '';
                $content = '';
                if (strpos($item['YXZD'], '脂肪肝') !== false) {
                    preg_match_all("/肝脏[^\f\n\r\t\v]*/", $item['YXBX'], $res);
                    if (!empty($res[0])) {
                        $keyword = '脂肪肝';
                        $content = $res[0][0];
                    }
                } elseif (strpos($item['YXZD'], '血管瘤') !== false || strpos($item['YXZD'], '错构瘤') !== false) {
                    preg_match_all("/肝脏([\s\S]*)胆囊/", $item['YXBX'], $res);
                    if (!empty($res[0])) {
                        $keyword = '血管瘤,错构瘤';
                        $content = $res[0][0];
                    }
                } elseif (strpos($item['YXZD'], '钙化灶') !== false) {
                    preg_match_all("/肝脏([\s\S]*)胆囊/", $item['YXBX'], $res);
                    if (!empty($res[0])) {
                        $keyword = '钙化灶';
                        $content = $res[0][0];
                    }
                } elseif (strpos($item['YXZD'], '胆囊结石') !== false) {
                    preg_match_all("/肝脏([\s\S]*)胰腺/", $item['YXBX'], $res);
                    if (!empty($res[0])) {
                        $keyword = '胆囊结石';
                        $content = $res[0][0];
                    }
                } elseif (strpos($item['YXZD'], '胆囊息肉') !== false) {
                    $keyword = '胆囊息肉';
                    $content = $item['YXBX'];
                } elseif (strpos($item['YXZD'], '膀胱结石') !== false) {
                    $keyword = '膀胱结石';
                    preg_match_all("/膀胱([\s\S]*)输尿管/", $item['YXBX'], $res);
                    if (!empty($res[0])) {
                        $content = $res[0][0];
                    } else {
                        preg_match_all("/膀胱([\s\S]*)前内腺/", $item['YXBX'], $res1);
                        if (!empty($res1[0])) {
                            $content = $res1[0][0];
                        }
                    }
                } elseif (strpos($item['YXZD'], '肾积水') !== false) {
                    preg_match_all("/双肾([\s\S]*)输尿管/", $item['YXBX'], $res);
                    if (!empty($res[0])) {
                        $keyword = '肾积水';
                        $content = $res[0][0];
                    }
                } elseif (strpos($item['YXZD'], '结石') !== false) {
                    preg_match_all("/双肾[^\f\n\r\t\v]*/", $item['YXBX'], $res);
                    if (!empty($res[0])) {
                        $keyword = '结石';
                        $content = $res[0][0];
                    }
                } elseif (strpos($item['YXZD'], '膀胱炎') !== false) {
                    preg_match_all("/膀胱[^\f\n\r\t\v]*/", $item['YXBX'], $res);
                    if (!empty($res[0])) {
                        $keyword = '膀胱炎';
                        $content = $res[0][0];
                    }
                } elseif (strpos($item['YXZD'], '早孕') !== false) {
                    preg_match_all("/子宫[^\f\n\r\t\v]*/", $item['YXBX'], $res);
                    if (!empty($res[0])) {
                        $keyword = '早孕';
                        $content = $res[0][0];
                    }
                } elseif (strpos($item['YXZD'], '肌瘤') !== false) {
                    preg_match_all("/子宫[^\f\n\r\t\v]*/", $item['YXBX'], $res);
                    if (!empty($res[0])) {
                        $keyword = '肌瘤';
                        $content = $res[0][0];
                    }
                } elseif (strpos($item['YXZD'], '早孕') !== false || strpos($item['YXZD'], '活胎') !== false) {
                    preg_match_all("/子宫[^\f\n\r\t\v]*/", $item['YXBX'], $res);
                    if (!empty($res[0])) {
                        $keyword = '早孕,活胎';
                        $content = $res[0][0];
                    }
                } elseif (
                    strpos($item['YXZD'], '畸胎瘤') !== false ||
                    strpos($item['YXZD'], '畸胎类肿瘤') !== false ||
                    strpos($item['YXZD'], '皮样囊肿') !== false
                ) {
                    $keyword = '畸胎瘤或畸胎类肿瘤或皮样囊肿';
                    $content = '';
                    preg_match_all("/卵巢[^\f\n\r\t\v]*/", $item['YXBX'], $res);
                    if (!empty($res[0])) {
                        $content .= $res[0][0];
                    }
                    preg_match_all("/附件[^\f\n\r\t\v]*/", $item['YXBX'], $res1);
                    if (!empty($res1[0])) {
                        $content .= $res1[0][0];
                    }

                } elseif (strpos($item['YXZD'], '巧克力囊肿') !== false) {
                    $keyword = '巧克力囊肿';
                    $content = '';
                    preg_match_all("/卵巢[^\f\n\r\t\v]*/", $item['YXBX'], $res);
                    if (!empty($res[0])) {
                        $content .= $res[0][0];
                    }
                    preg_match_all("/附件[^\f\n\r\t\v]*/", $item['YXBX'], $res1);
                    if (!empty($res1[0])) {
                        $content .= $res1[0][0];
                    }
                } elseif (strpos($item['YXZD'], '卵巢冠囊肿') !== false) {
                    $keyword = '卵巢冠囊肿';
                    $content = '';
                    preg_match_all("/卵巢[^\f\n\r\t\v]*/", $item['YXBX'], $res);
                    if (!empty($res[0])) {
                        $content .= $res[0][0];
                    }
                    preg_match_all("/附件[^\f\n\r\t\v]*/", $item['YXBX'], $res1);
                    if (!empty($res1[0])) {
                        $content .= $res1[0][0];
                    }
                } elseif (strpos($item['YXZD'], '输卵管积水') !== false) {
                    $keyword = '输卵管积水';
                    $content = '';
                    preg_match_all("/卵巢[^\f\n\r\t\v]*/", $item['YXBX'], $res);
                    if (!empty($res[0])) {
                        $content .= $res[0][0];
                    }
                    preg_match_all("/附件[^\f\n\r\t\v]*/", $item['YXBX'], $res1);
                    if (!empty($res1[0])) {
                        $content .= $res1[0][0];
                    }
                } elseif (strpos($item['YXZD'], '葡萄胎') !== false) {
                    $keyword = '葡萄胎';
                    $content = $item['YXBX'];
                } elseif (strpos($item['YXZD'], '脐带绕颈') !== false) {
                    $keyword = '脐带绕颈';
                    $content = $item['YXBX'];
                }
                if (empty($keyword) || empty($content)) {
                    continue;
                }
                // 添加数据
                PacsWash::query()->updateOrInsert(
                    ['KDSJ' => $item['KDSJ'], 'JZLSH' => $item['JZLSH']],
                    ['pacs_id' => $item['pacs_id'], 'keyword' => $keyword, 'content' => $content]
                );
            }

        }
        Setting::query()->where('name', '=', $setName)->update(['content' => $lastId]);
    }
}

