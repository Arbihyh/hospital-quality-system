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
        $lastId = Setting::query()->where('name', '=', 'quality_chaosheng_last_id')->pluck('content')->first();
        $lastId = $lastId ?: 0;

        // 获取新想需要质控的病例信息
        $piesService = new ElasticsearchService('patient_info');
        while (true) {
            $params = $piesService->clearMust()
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
                $this->quality($caseRule, $info);
            }

            // 更新
            $lastInfo = array_pop($resData[0]);
            $lastId = $lastInfo['MED_REC_ID'];
        }
        Setting::query()->where('name', '=', 'quality_chaosheng_last_id')->update(['content' => $lastZyh]);
    }

    public function quality($caseRule = [], $info = [])
    {
        $caseRule = $this->caseRule;

        $pacs = PacsWash::query()->where('JZLSH', '=', $info['AAA28'])->get()->toArray();
        if(empty($pacs)){
            return false;
        }
        // 获取规则
        foreach ($pacs as $item) {
            $basis = [];
            if (
                $item['keyword'] == '脂肪肝' &&
                (
                    strpos($item['content'], '实质回声细密增强') === false &&
                    (
                        strpos($item['content'], '肝内光点较密集') === false &&
                        strpos($item['content'], '回声') === false &&
                        strpos($item['content'], '强') === false
                    ) &&
                    (
                        strpos($item['content'], '后方回声') === false &&
                        strpos($item['content'], '衰减') === false
                    )
                )
            ) {

                $basis[] = '检查诊断【脂肪肝】';
                $basis[] = '影像表现：无【实质回声细密增强    或   肝内光点较密集 ＋  回声＋ 强   或 后方回声+衰减】';
            }
//            elseif (strpos($item['YXZD'], '血管瘤') !== false || strpos($item['YXZD'], '错构瘤') !== false) {
//                preg_match_all("/肝脏([\s\S]*)胆囊/", $item['YXBX'], $res);
//                if (!empty($res[0])) {
//                    $keyword = '血管瘤,错构瘤';
//                    $content = $res[0][0];
//                }
//            } elseif (strpos($item['YXZD'], '钙化灶') !== false) {
//                preg_match_all("/肝脏([\s\S]*)胆囊/", $item['YXBX'], $res);
//                if (!empty($res[0])) {
//                    $keyword = '钙化灶';
//                    $content = $res[0][0];
//                }
//            } elseif (strpos($item['YXZD'], '胆囊结石') !== false) {
//                preg_match_all("/肝脏([\s\S]*)胰腺/", $item['YXBX'], $res);
//                if (!empty($res[0])) {
//                    $keyword = '胆囊结石';
//                    $content = $res[0][0];
//                }
//            } elseif (strpos($item['YXZD'], '胆囊息肉') !== false) {
//                $keyword = '胆囊息肉';
//                $content = $item['YXBX'];
//            } elseif (strpos($item['YXZD'], '膀胱结石') !== false) {
//                preg_match_all("/膀胱([\s\S]*)输尿管/", $item['YXBX'], $res);
//                if (!empty($res[0])) {
//                    $keyword = '膀胱结石';
//                    $content = $res[0][0];
//                } else {
//                    preg_match_all("/膀胱([\s\S]*)前内腺/", $item['YXBX'], $res1);
//                    if (!empty($res1[0])) {
//                        $keyword = '膀胱结石';
//                        $content = $res1[0][0];
//                    }
//                }
//            } elseif (strpos($item['YXZD'], '肾积水') !== false) {
//                preg_match_all("/双肾([\s\S]*)输尿管/", $item['YXBX'], $res);
//                if (!empty($res[0])) {
//                    $keyword = '肾积水';
//                    $content = $res[0][0];
//                }
//            } elseif (strpos($item['YXZD'], '结石') !== false) {
//                preg_match_all("/双肾[^\f\n\r\t\v]*/", $item['YXBX'], $res);
//                if (!empty($res[0])) {
//                    $keyword = '结石';
//                    $content = $res[0][0];
//                }
//            } elseif (strpos($item['YXZD'], '膀胱炎') !== false) {
//                preg_match_all("/膀胱[^\f\n\r\t\v]*/", $item['YXBX'], $res);
//                if (!empty($res[0])) {
//                    $keyword = '膀胱炎';
//                    $content = $res[0][0];
//                }
//            } elseif (strpos($item['YXZD'], '早孕') !== false) {
//                preg_match_all("/子宫[^\f\n\r\t\v]*/", $item['YXBX'], $res);
//                if (!empty($res[0])) {
//                    $keyword = '早孕';
//                    $content = $res[0][0];
//                }
//            } elseif (strpos($item['YXZD'], '肌瘤') !== false) {
//                preg_match_all("/子宫[^\f\n\r\t\v]*/", $item['YXBX'], $res);
//                if (!empty($res[0])) {
//                    $keyword = '肌瘤';
//                    $content = $res[0][0];
//                }
//            } elseif (strpos($item['YXZD'], '早孕') !== false || strpos($item['YXZD'], '活胎') !== false) {
//                preg_match_all("/子宫[^\f\n\r\t\v]*/", $item['YXBX'], $res);
//                if (!empty($res[0])) {
//                    $keyword = '早孕,活胎';
//                    $content = $res[0][0];
//                }
//            } elseif (strpos($item['YXZD'], '畸胎瘤') !== false) {
//                preg_match_all("/卵巢[^\f\n\r\t\v]*/", $item['YXBX'], $res);
//                if (!empty($res[0])) {
//                    $keyword = '畸胎瘤';
//                    $content = $res[0][0];
//                } else {
//                    preg_match_all("/附件[^\f\n\r\t\v]*/", $item['YXBX'], $res1);
//                    if (!empty($res1[0])) {
//                        $keyword = '畸胎瘤';
//                        $content = $res1[0][0];
//                    }
//                }
//            } elseif (strpos($item['YXZD'], '巧克力囊肿') !== false) {
//                preg_match_all("/卵巢[^\f\n\r\t\v]*/", $item['YXBX'], $res);
//                if (!empty($res[0])) {
//                    $keyword = '巧克力囊肿';
//                    $content = $res[0][0];
//                } else {
//                    preg_match_all("/附件[^\f\n\r\t\v]*/", $item['YXBX'], $res1);
//                    if (!empty($res1[0])) {
//                        $keyword = '巧克力囊肿';
//                        $content = $res1[0][0];
//                    }
//                }
//            } elseif (strpos($item['YXZD'], '卵巢冠囊肿') !== false) {
//                preg_match_all("/卵巢[^\f\n\r\t\v]*/", $item['YXBX'], $res);
//                if (!empty($res[0])) {
//                    $keyword = '卵巢冠囊肿';
//                    $content = $res[0][0];
//                } else {
//                    preg_match_all("/附件[^\f\n\r\t\v]*/", $item['YXBX'], $res1);
//                    if (!empty($res1[0])) {
//                        $keyword = '卵巢冠囊肿';
//                        $content = $res1[0][0];
//                    }
//                }
//            }
            var_dump($basis);
            if (!empty($basis)) {
                $errorNotice = [
                    'basis' => json_encode([$basis], 256),
                    'JZHM' => $info['MED_REC_ID'],
                    'rule_id' => 135,
                    'code' => 'chaosheng',
                    'error_field' => $caseRule[135]['title']
                ];
                // 添加数据
                PatientInfo::query()->where('MED_REC_ID', '=', $info['MED_REC_ID'])->update(['is_defect' => 1]);
                CaseQuality::addData($errorNotice);
            }

        }
        return false;
    }


    function cleanPacs()
    {
        // 获取规则
        $caseRule = $this->caseRule;
        // 获取最后一次质控的住院号
        $lastId = Setting::query()->where('name', '=', 'clean_last_pacs_id')->pluck('content')->first();
        $lastId = $lastId ?: 0;
        $paceEsService = new ElasticsearchService('pacs');
        while (true) {
            $params = $paceEsService->clearMust()
                ->queryByMust(['term' => ['ExamType' => '06']])
                ->queryByMust(['range' => ['pacs_id' => ['gt' => $lastId]]])
                ->paginate(1, 10)
                ->source(['JZLSH', 'KDSJ', 'YXZD', 'YXBX', 'pacs_id'])
                ->getParams();
            $res = app('es')->search($params);
            $resdata = $paceEsService->getDataByEs($res);
            if (empty($resdata[1])) {
                break;
            }

            $data = $resdata[0];
            foreach ($data as $item) {
                $lastId = $item['pacs_id'];
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
                    preg_match_all("/膀胱([\s\S]*)输尿管/", $item['YXBX'], $res);
                    if (!empty($res[0])) {
                        $keyword = '膀胱结石';
                        $content = $res[0][0];
                    } else {
                        preg_match_all("/膀胱([\s\S]*)前内腺/", $item['YXBX'], $res1);
                        if (!empty($res1[0])) {
                            $keyword = '膀胱结石';
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
                } elseif (strpos($item['YXZD'], '畸胎瘤') !== false) {
                    preg_match_all("/卵巢[^\f\n\r\t\v]*/", $item['YXBX'], $res);
                    if (!empty($res[0])) {
                        $keyword = '畸胎瘤';
                        $content = $res[0][0];
                    } else {
                        preg_match_all("/附件[^\f\n\r\t\v]*/", $item['YXBX'], $res1);
                        if (!empty($res1[0])) {
                            $keyword = '畸胎瘤';
                            $content = $res1[0][0];
                        }
                    }
                } elseif (strpos($item['YXZD'], '巧克力囊肿') !== false) {
                    preg_match_all("/卵巢[^\f\n\r\t\v]*/", $item['YXBX'], $res);
                    if (!empty($res[0])) {
                        $keyword = '巧克力囊肿';
                        $content = $res[0][0];
                    } else {
                        preg_match_all("/附件[^\f\n\r\t\v]*/", $item['YXBX'], $res1);
                        if (!empty($res1[0])) {
                            $keyword = '巧克力囊肿';
                            $content = $res1[0][0];
                        }
                    }
                } elseif (strpos($item['YXZD'], '卵巢冠囊肿') !== false) {
                    preg_match_all("/卵巢[^\f\n\r\t\v]*/", $item['YXBX'], $res);
                    if (!empty($res[0])) {
                        $keyword = '卵巢冠囊肿';
                        $content = $res[0][0];
                    } else {
                        preg_match_all("/附件[^\f\n\r\t\v]*/", $item['YXBX'], $res1);
                        if (!empty($res1[0])) {
                            $keyword = '卵巢冠囊肿';
                            $content = $res1[0][0];
                        }
                    }
                }
                if (empty($keyword)) {
                    continue;
                }
                // 添加数据
                PacsWash::query()->updateOrInsert(
                    ['KDSJ' => $item['KDSJ'], 'JZLSH' => $item['JZLSH']],
                    ['pacs_id' => $item['pacs_id'], 'keyword' => $keyword, 'content' => $content]
                );
            }

        }
        Setting::query()->where('name', '=', 'clean_last_pacs_id')->update(['content' => $lastId]);
    }
}

