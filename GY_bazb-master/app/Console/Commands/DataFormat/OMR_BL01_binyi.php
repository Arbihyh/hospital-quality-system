<?php

namespace App\Console\Commands\DataFormat;

use App\Model\MS_BRDA;
use App\Model\PatientInfo;
use App\Model\RuleWordMap;
use App\Model\Setting;
use App\Services\ElasticsearchService;
use Illuminate\Console\Command;

class OMR_BL01_binyi extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'omr_bl01_binyi {time?} {blbh?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'omr_bl01_binyi数据格式化';

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
        $this->info('omr_bl01_binyi数据格式化 - 数据开始处理');

        $blbh = $this->argument('blbh') ?: '';
        $time = $this->argument('time') ?: date('Y-m-d 00:00:00', strtotime('-10 day'));
        $this->omrBl01Format($blbh, $time);

        $this->info('omr_bl01_binyi数据格式化 - 数据处理完毕');
    }

    /**
     * @param $page
     * @return true
     */
    public function omrBl01Format($blbh = '', $time = '')
    {
        $timeInt = strtotime($time);

        while (true) {
            if ($timeInt >= time()) {
                break;
            }
            var_dump("处理时间: " . date('Y-m-d 00:00:00', $timeInt));
            // 查询指定ID范围内的所有记录
            $data = \App\Model\OMR_BL01::query()
                ->orderBy('id')
                ->when($blbh, function ($query) use ($blbh) {
                    $query->where('BLBH', '=', $blbh);
                })
                ->when($time, function ($query) use ($timeInt) {
                    $query->where('JLSJ', '>=', date('Y-m-d 00:00:00', $timeInt))->where('JLSJ', '<=', date('Y-m-d 23:59:59', $timeInt));
                })
                ->get()->toArray();

            $total = count($data);
            var_dump("总条数: " . $total);

            // 每次循环只加1天
            $timeInt += 86400;

            foreach ($data as $key => $value) {
                $recordId = $value['id'];

                $saveData = [];

                $BLNR_TXT = $value['BLNR_TXT'];
                if (env("APP_NAME") == "binyi") {
                    $BLNR_TXT = preg_replace('/辅助检查结果:/', '辅助检查:', $BLNR_TXT, 1);
                }

                $saveData1 = $this->cleanDataFilter($BLNR_TXT, 0);
                if (!empty($saveData1['xm'])) {
                    $saveData1['xm'] = desensitize($saveData1['xm'], 1, 1, '*');
                }
                if (!empty($saveData1['jzsj'])) {
                    // 兼容中文日期格式，使用正则匹配
                    // 格式1: 2026年01月30日10时00分 -> 2026-01-30 10:00:00
                    // 格式2: 20260130100000 -> 2026-01-30 10:00:00
                    $jzsj = $saveData1['jzsj'];

                    // 尝试匹配中文日期格式
                    if (preg_match('/(\d{4})年(\d{1,2})月(\d{1,2})日(\d{1,2})时(\d{1,2})分?/', $jzsj, $matches)) {
                        $saveData1['jzsj'] = sprintf(
                            '%04d-%02d-%02d %02d:%02d:00',
                            $matches[1],
                            $matches[2],
                            $matches[3],
                            $matches[4],
                            $matches[5]
                        );
                    }
                    // 尝试匹配纯数字格式（带秒）
                    elseif (preg_match('/^(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})$/', $jzsj, $matches)) {
                        $saveData1['jzsj'] = sprintf(
                            '%04d-%02d-%02d %02d:%02d:%02d',
                            $matches[1],
                            $matches[2],
                            $matches[3],
                            $matches[4],
                            $matches[5],
                            $matches[6]
                        );
                    }
                    // 尝试匹配简单数字格式（没有秒）
                    elseif (preg_match('/^(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})$/', $jzsj, $matches)) {
                        $saveData1['jzsj'] = sprintf(
                            '%04d-%02d-%02d %02d:%02d:00',
                            $matches[1],
                            $matches[2],
                            $matches[3],
                            $matches[4],
                            $matches[5]
                        );
                    }
                    // 如果都不匹配，使用原来的简单方法
                    elseif (strlen($jzsj) > 10) {
                        $saveData1['jzsj'] = substr($jzsj, 0, 10) . ' ' . substr($jzsj, 10);
                        // 如果没有秒，补充:00
                        if (strlen($saveData1['jzsj']) == 16) {
                            $saveData1['jzsj'] .= ':00';
                        }
                    }
                }
                $saveData = array_merge($saveData, $saveData1);

                // 年龄处理 - 使用更宽松的正则表达式
                if (!empty($saveData['nl'])) {
                    $nl = trim($saveData['nl']);
                    $saveData['nl1'] = mb_substr($nl, 0, 20);

                    if (stripos($nl, '岁') !== false) {
                        $saveData['nl'] = preg_replace('/[^0-9]/', '', $nl);
                    } else if (stripos($nl, '月') !== false) {
                        $saveData['nl'] = '0';
                        $saveData['nl1'] = mb_substr($nl, 0, 20);
                    } else if (stripos($nl, '天') !== false) {
                        $saveData['nl'] = '0';
                        $saveData['nl_day'] = preg_replace('/[^0-9]/', '', $nl);
                    } else if (stripos($nl, '小时') !== false) {
                        $saveData['nl'] = '0';
                        $saveData['nl_hour'] = preg_replace('/[^0-9]/', '', $nl);
                    } else if (stripos($nl, '分钟') !== false) {
                        $saveData['nl'] = '0';
                        $saveData['nl_minute'] = preg_replace('/[^0-9]/', '', $nl);
                    } else {
                        $saveData['nl'] = '0';
                    }
                }

                if (!empty($saveData['qjjl'])) {
                    $saveData['zlyj'] = $saveData['zlyj'] . ' 抢救记录：' . $saveData['qjjl'];
                }

                // 提醒
                if (preg_match('/※提醒：(.*?)※/s', $BLNR_TXT, $matches)) {
                    $saveData['tx'] = trim($matches[1]);
                }

                // 初诊、复诊、急诊
                $saveData['bl_type'] = '门诊';
                if (stripos($BLNR_TXT, '门(急)诊病历') !== false) {
                    $saveData['bl_type'] = '门诊';
                } elseif (stripos($BLNR_TXT, '初诊') !== false) {
                    $saveData['bl_type'] = '初诊';
                } elseif (stripos($BLNR_TXT, '复诊') !== false) {
                    $saveData['bl_type'] = '复诊';
                } elseif (stripos($BLNR_TXT, '急诊') !== false) {
                    $saveData['bl_type'] = '急诊';
                }

                // 身份证号获取
                $msBrdaData = MS_BRDA::query()->where('BRID', '=', $value['BRID'])->first();
                if (!empty($msBrdaData)) {
                    $saveData['SFZH'] = desensitize($msBrdaData->SFZH, 6, 8, '*');
                }
                // 打印要更新的数据

                if ($saveData) {
                    $result = \App\Model\OMR_BL01::query()->where('id', '=', $recordId)->update($saveData);
                    if ($result) {
                        $this->info("处理记录ID: " . $recordId . " (总第 " . ($key + 1) . " 条)");
                        // 临时跳过ES写入，避免磁盘空间不足错误
                        // $this->esSave(array_merge($saveData, $value));
                    }
                }
            }
        }
        return true;
    }

    public function esSave($item = [])
    {

        $index = 'omr_bl01_2023';
        $es_params['body'][] = ['update' => ['_index' => $index, '_id' => $item['BLBH']]];
        $es_params['body'][] = ['doc' => [
            "BLBH" => $item['BLBH'],
            "BLLB" => $item['BLLB'],
            "BLLX" => $item['BLLX'],
            "BLMC" => $item['BLMC'],
            "BLNR_TXT" => $item['BLNR_TXT'],
            "BLZT" => $item['BLZT'],
            "BRID" => $item['BRID'],
            "BRKS" => $item['BRKS'],
            "CJSJ" => $item['CJSJ'],
            "DLJ" => $item['DLJ'],
            "DLLB" => $item['DLLB'],
            "JLSJ" => $item['JLSJ'],
            "JZXH" => $item['JZXH'],
            "SFZH" => $item['SFZH'],
            "SXKS" => $item['SXKS'],
            "SXYS" => $item['SXYS'],
            "WCSJ" => $item['WCSJ'],
            "cbzd" => $item['cbzd'],
            "data_id" => $item['id'],
            "fzjc" => $item['fzjc'],
            "id" => $item['id'],
            "is_defect" => $item['is_defect'],
            "jws" => $item['jws'],
            "jzsj" => platformTime($item['jzsj']),
            "ks" => $item['ks'],
            "lxbxs" => $item['lxbxs'],
            "mzh" => $item['mzh'],
            "nl" => $item['nl'],
            "nl1" => $item['nl1'],
            "tgjc" => $item['tgjc'],
            "tx" => $item['tx'],
            "xb" => $item['xb'],
            "xbs" => $item['xbs'],
            "xm" => $item['xm'],
            "xy" => $item['xy'],
            "xy_json" => $item['xy_json'],
            "zlyj" => $item['zlyj'],
            "zs" => $item['zs'],
            "score" => $item['score'] ?? '',
            "score_lv" => $item['score_lv'] ?? '',
            "qjjl" => $item['qjjl'] ?? '',
            "sz" => $item['sz'] ?? '',
            "yz" => $item['yz'] ?? '',
            "wangz" => $item['wangz'] ?? '',
            "wenz" => $item['wenz'] ?? '',
            "wz" => $item['wz'] ?? '',
            "qiez" => $item['qiez'] ?? '',
            "zyzd" => $item['zyzd'] ?? '',
            "xyzd" => $item['xyzd'] ?? '',
        ], 'doc_as_upsert' => true];

        $res = app('es')->bulk($es_params);
        if ($res['errors'] == true) {
            throw new \Exception($res['items'][0]['update']['error']['reason']);
        }
    }


    function cleanDataFilter($hjnr = "", $filter = 1)
    {

        // 去除所有空格并统一冒号格式
        $hjnr = preg_replace("/\s+/", "", $hjnr);
        $hjnr = str_replace("：", ":", $hjnr);

        // 获取后台设置的配置信息
        $keyword = RuleWordMap::query()->where("name", 'clean_omr_bl01')->value("keyword");
        $keyword = explode(",", $keyword);

        // 先进行字段替换，确保关键字段名称统一
        //如果不包含处理意见，且包含诊疗意见，则替换为处理意见：
        if (stripos($hjnr, '处理意见:') === false && stripos($hjnr, '诊疗意见') !== false) {
            $hjnr = str_replace("诊疗意见", "处理意见", $hjnr);
        }
        $hjnr = str_replace("诊疗意见", "处理意见", $hjnr);

        //如果不包含现病史：，且包含病史：，则替换为现病史：
        if (stripos($hjnr, '现病史:') === false && stripos($hjnr, '病史:') !== false) {
            $hjnr = str_replace("病史:", "现病史:", $hjnr);
        }

        //如果不包含辅助检查：，且包含复制检查结果：，则替换为辅助检查：
        if (stripos($hjnr, '辅助检查:') === false && stripos($hjnr, '辅助检查结果:') !== false) {
            $hjnr = preg_replace('/辅助检查结果:/', '辅助检查:', $hjnr, 1);
        }

        // 处理药品字段：如果不包含"药品:"，但包含"方药:"或"西药:"，则替换为"药品:"
        if (stripos($hjnr, '药品:') === false) {
            if (stripos($hjnr, '方药:') !== false) {
                $hjnr = str_replace("方药:", "药品:", $hjnr);
            } elseif (stripos($hjnr, '西药:') !== false) {
                $hjnr = str_replace("西药:", "药品:", $hjnr);
            }
        }

        // 处理诊断字段 - 中医病历特殊处理
        // 如果包含"中医诊断:"或"西医诊断:"，则将"诊断:"替换为"初步诊断:"（仅替换第一个）
        if (stripos($hjnr, '中医诊断:') !== false || stripos($hjnr, '西医诊断:') !== false) {
            // 找到"诊断:"的位置，确保它在"中医诊断:"或"西医诊断:"之前
            $pos_zd = mb_strpos($hjnr, '诊断:');
            $pos_zyzd = mb_strpos($hjnr, '中医诊断:');
            $pos_xyzd = mb_strpos($hjnr, '西医诊断:');

            if ($pos_zd !== false) {
                // 如果"诊断:"在"中医诊断:"或"西医诊断:"之前，则替换
                if (($pos_zyzd !== false && $pos_zd < $pos_zyzd) || ($pos_xyzd !== false && $pos_zd < $pos_xyzd)) {
                    $hjnr = preg_replace('/诊断:/', '初步诊断:', $hjnr, 1);
                }
            }
        } else {
            // 如果不包含"中医诊断:"和"西医诊断:"，则正常替换
            if (stripos($hjnr, '初步诊断:') === false && stripos($hjnr, '诊断:') !== false) {
                $hjnr = str_replace("诊断:", "初步诊断:", $hjnr);
            }
        }

        // 四诊字段名兼容：如果内容中不存在“四诊资料:”，但包含“中医望闻切诊:”，则统一替换为“四诊资料:”
        if (stripos($hjnr, '四诊资料:') === false && stripos($hjnr, '中医望闻切诊:') !== false) {
            $hjnr = str_replace('中医望闻切诊:', '四诊资料:', $hjnr);
        }

        //$keyword = ['zs|主诉:', 'xbs|现病史:', 'jws|既往史:', 'tgjc|体格检查:', 'fzjc|辅助检查:', 'cbzd|初步诊断:', 'zlyj|处理意见:', '|※提醒:', '|第 1 页', '|第1页', '|医师签名', 'mzh|门诊号:', 'xm|姓名:', 'xb|性别:', 'jzsj|就诊时间:', 'ks|科室:', 'nl|年龄:', 'xy|药品:', 'qjjl|抢救记录:', 'sz|四诊资料:', 'yz|医嘱:'];
        $data = [];
        foreach ($keyword as $k) {
            $tmp = explode("|", $k);
            if (empty($tmp[0])) {
                continue;
            }
            $data[$tmp[0]] = $this->getContent($keyword, $hjnr, $tmp[1], $filter);
        }

        // 特殊处理：如果cbzd为空，尝试提取"中医诊断"和"西医诊断"的组合
        if (empty($data['cbzd']) && (stripos($hjnr, '中医诊断:') !== false || stripos($hjnr, '西医诊断:') !== false)) {
            $cbzd_parts = [];

            // 提取中医诊断
            if (stripos($hjnr, '中医诊断:') !== false) {
                $zyzd = $this->getContent($keyword, $hjnr, '中医诊断:', $filter);
                if (!empty($zyzd)) {
                    $cbzd_parts[] = '中医诊断:' . $zyzd;
                }
            }

            // 提取西医诊断
            if (stripos($hjnr, '西医诊断:') !== false) {
                $xyzd = $this->getContent($keyword, $hjnr, '西医诊断:', $filter);
                if (!empty($xyzd)) {
                    $cbzd_parts[] = '西医诊断:' . $xyzd;
                }
            }

            if (!empty($cbzd_parts)) {
                $data['cbzd'] = implode('', $cbzd_parts);
            }
        }

        return $data;
    }

    function getContent($ruleMapArr, $str, $field, $filter = 1)
    {
        // 去除所有空格
        $str = preg_replace("/\s+/", "", $str);
        $field = preg_replace("/\s+/", "", $field);

        // 是否过滤小大括号
        if ($filter == 1) {
            $str = str_replace(["{", "}"], "", $str);
        }

        $str = str_replace("：", ":", $str);

        //如果有两个辅助检查：，把第二个辅助检查替换成辅助检查：（中文符号）
        if (substr_count($str, '辅助检查:') >= 2) {
            // 使用回调函数，跳过第一个匹配，替换第二个
            $count = 0;
            $str = preg_replace_callback('/辅助检查:/', function ($match) use (&$count) {
                $count++;
                return $count == 2 ? '辅助检查：' : $match[0];
            }, $str);
        }

        // 特殊处理：对于某些字段，如果出现多次，优先提取最后一个（通常更完整）
        $useLastOccurrence = ['就诊时间:', '姓名:', '性别:', '年龄:', '门诊号:', '科室:'];
        $start = false;

        if (in_array($field, $useLastOccurrence)) {
            // 查找所有出现位置，使用最后一个
            $offset = 0;
            $lastPos = false;
            while (($pos = mb_strpos($str, $field, $offset)) !== false) {
                $lastPos = $pos;
                $offset = $pos + 1;
            }
            $start = $lastPos;
        } else {
            // 1. 先找到指定字段的位置（第一个）
            $start = mb_strpos($str, "{$field}");
        }

        if ($start === false) {
            return "";
        }

        // 2. 起始位置（跳过字段名和冒号）
        $start += mb_strlen($field);

        // 3. 查找下一个分隔点
        $end = PHP_INT_MAX;

        // 中医病历特殊字段列表（这些字段应该作为分隔符）
        $specialFields = ['药品:', '医嘱:', '个人史:', '婚育史:', '家族史:', '医院名称:', '就诊科室:', '门诊病历', '上海市中医院'];

        // 特殊处理：如果当前字段是"四诊资料:"，则"望诊"、"闻诊"、"问诊"、"切诊"不应该作为分隔符
        if ($field !== '四诊资料:') {
            $specialFields[] = '望诊';
            $specialFields[] = '闻诊';
            $specialFields[] = '问诊';
            $specialFields[] = '切诊';
        }

        // 特殊处理：如果当前字段是"初步诊断:"，则"中医诊断:"和"西医诊断:"不应该作为分隔符
        // 因为初步诊断需要包含完整的中医诊断和西医诊断内容
        if ($field !== '初步诊断:') {
            $specialFields[] = '中医诊断:';
            $specialFields[] = '西医诊断:';
        }

        foreach ($ruleMapArr as $k) {
            $tmp = explode("|", $k);
            $keyword = $tmp[1];
            $keyword = preg_replace("/\s+/", "", $keyword);
            $keyword = str_replace("：", ":", $keyword);

            // 特殊处理：如果当前字段是"初步诊断:"，则跳过"中医诊断:"和"西医诊断:"作为分隔符
            if ($field == '初步诊断:' && ($keyword == '中医诊断:' || $keyword == '西医诊断:')) {
                continue;
            }

            // 特殊处理：如果当前字段是"四诊资料:"，则跳过"望诊"、"闻诊"、"问诊"、"切诊"（无冒号）作为分隔符
            if ($field == '四诊资料:' && in_array($keyword, ['望诊', '闻诊', '问诊', '切诊'])) {
                continue;
            }

            //如果字符串长度小于等于 $start 直接复制字符串长度 避免报错
            $pos = mb_strlen($str) <= $start ? mb_strlen($str) : mb_strpos($str, $keyword, $start);

            if ($pos !== false && $pos > $start && $pos < $end) {
                $end = $pos;
            } elseif ($pos !== false && $end == PHP_INT_MAX) {
                $end = $pos;
            }
        }

        // 额外检查中医病历特殊字段作为分隔符
        foreach ($specialFields as $specialField) {
            $pos = mb_strlen($str) <= $start ? mb_strlen($str) : mb_strpos($str, $specialField, $start);
            if ($pos !== false && $pos > $start && $pos < $end) {
                $end = $pos;
            }
        }

        // 4. 提取内容
        if ($end === PHP_INT_MAX) {
            $content = trim(mb_substr($str, $start));
        } else {
            $content = trim(mb_substr($str, $start, $end - $start));
        }
        return $content;
    }
}
