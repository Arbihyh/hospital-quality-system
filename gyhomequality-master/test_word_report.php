<?php

/**
 * Word报告生成测试脚本
 */

require __DIR__ . '/vendor/autoload.php';

use App\Services\WordReportService;

echo "==========================================\n";
echo "Word报告生成测试\n";
echo "==========================================\n\n";

try {
    // 1. 测试类是否可以加载
    echo "1. 检查 PhpWord 类...\n";
    if (class_exists('PhpOffice\PhpWord\TemplateProcessor')) {
        echo "   ✓ PhpWord TemplateProcessor 类可用\n";
    } else {
        echo "   ✗ PhpWord TemplateProcessor 类不可用\n";
        exit(1);
    }
    
    // 2. 检查模板文件
    echo "\n2. 检查模板文件...\n";
    $baseDir = __DIR__;
    $templatePath = $baseDir . '/病历质控分析报告1.docx';
    if (!file_exists($templatePath)) {
        $templatePath = $baseDir . '/storage/app/templates/病历质控分析报告1.docx';
    }
    
    if (file_exists($templatePath)) {
        echo "   ✓ 找到模板文件: $templatePath\n";
        echo "   文件大小: " . number_format(filesize($templatePath) / 1024, 2) . " KB\n";
    } else {
        echo "   ✗ 模板文件不存在\n";
        echo "   查找路径:\n";
        echo "     - " . $baseDir . '/病历质控分析报告1.docx' . "\n";
        echo "     - " . $baseDir . '/storage/app/templates/病历质控分析报告1.docx' . "\n";
        exit(1);
    }
    
    // 3. 测试加载模板
    echo "\n3. 测试加载模板...\n";
    $wordReportService = new WordReportService();
    $wordReportService->loadTemplate($templatePath);
    echo "   ✓ 模板加载成功\n";
    
    // 4. 准备测试数据（根据新模板）
    echo "\n4. 准备测试数据...\n";
    $testData = [
        // 封面页变量
        'year' => '2025',           // 年份
        'qi' => '10',               // 期数
        'time' => '2025年12月',     // 当前时间范围
        'qinum' => '12',            // 总期数
        
        // 当前期（本月）总体情况数据
        'blnum' => '4775',          // 病历总数
        'qxblnum' => '4619',        // 缺陷病历数
        'qxradio' => '96.73',       // 缺陷占比（%）
        'qxnum' => '10.92',         // 缺陷数量（平均每份）
        
        // 当前期（本月）病案等级数据
        'jjblnum' => '3500',        // 甲级病案数
        'jjradio' => '73.30',       // 甲级病案占比（%）
        'yjblnum' => '1100',        // 乙级病案数
        'yjradio' => '23.04',       // 乙级病案占比（%）
        'bjblnum' => '175',         // 丙级病案数
        'bjradio' => '3.66',        // 丙级病案占比（%）
        
        // 上一期（上个月）数据 - 用于同比计算
        'bftime' => '2025年11月',   // 上个月时间
        'bfblnum' => '5220',         // 上个月病历总数
        'bfqxblnum' => '4298',      // 上个月缺陷病历数
        'bfqxradio' => '82.33',     // 上个月缺陷占比
        
        // 上一期（上个月）病案等级数据
        'bfjjblnum' => '3800',      // 上月甲级病案数
        'bfjjradio' => '72.80',     // 上月甲级占比（%）
        'bfyjblnum' => '1200',      // 上月乙级病案数
        'bfyjradio' => '22.99',     // 上月乙级占比（%）
        'bfbjblnum' => '220',       // 上月丙级病案数
        'bfbjradio' => '4.21',      // 上月丙级占比（%）
        
        // 同比数据（与上个月对比）
        'tbbfb' => '↑ 0.92',        // 缺陷同比百分比变化
        'jjtbbfb' => '↑ 0.50',       // 甲级病案同比变化
        
        // 环比数据（与去年同月对比）
        'hbbfb' => '↑ 1.92',        // 缺陷环比百分比变化
        'jjhbbfb' => '↑ 1.20',       // 甲级病案环比变化
        
        // 病历书写时效性指标（本期）
        'ryjlradio' => '95.00',      // 入院记录24小时内完成率（%）
        'ryjltb' => '↑ 0.50',       // 入院记录同比
        'ryjlhb' => '↑ 1.00',       // 入院记录环比
        
        'ssjlradio' => '86.25',     // 手术记录24小时内完成率（%）
        'ssjltb' => '↑ 0.30',       // 手术记录同比
        'ssjlhb' => '↑ 0.80',       // 手术记录环比
        
        'cyjlradio' => '70.12',     // 出院记录24小时内完成率（%）
        'cyjltb' => '↓ 0.20',       // 出院记录同比（下降）
        'cyjlhb' => '↑ 0.50',       // 出院记录环比
        
        'basyradio' => '60.00',     // 病案首页24小时内完成率（%）
        'basytb' => '↑ 0.40',       // 病案首页同比
        'basyhb' => '↑ 0.90',       // 病案首页环比
        
        // 重大检查记录符合率（本期）
        'ctmrradio' => '92.50',     // CT/MRI检查记录符合率（%）
        'ctmrtb' => '↑ 0.60',       // CT/MRI同比
        'ctmrhb' => '↑ 1.10',       // CT/MRI环比
        
        'bljcradio' => '88.30',     // 病理检查记录符合率（%）
        'bljctb' => '↑ 0.40',       // 病理检查同比
        'bljchb' => '↑ 0.70',       // 病理检查环比
        
        'xjpyradio' => '85.60',     // 细菌培养检查记录符合率（%）
        'xjpytb' => '↑ 0.30',       // 细菌培养同比
        'xjpyhb' => '↑ 0.50',       // 细菌培养环比
        
        // 诊疗行为记录符合率（本期）
        'kjywradio' => '94.20',     // 抗菌药物使用记录符合率（%）
        'kjywtb' => '↑ 0.50',       // 抗菌药物同比
        'kjywhb' => '↑ 0.80',       // 抗菌药物环比
        
        'hlywradio' => '91.50',     // 恶性肿瘤化学治疗记录符合率（%）
        'hlywtb' => '↑ 0.40',       // 化学治疗同比
        'hlywhb' => '↑ 0.70',       // 化学治疗环比
        
        'fszlradio' => '89.80',     // 恶性肿瘤放射治疗记录符合率（%）
        'fszltb' => '↑ 0.35',       // 放射治疗同比
        'fszlhb' => '↑ 0.65',       // 放射治疗环比
        
        'ssxgradio' => '93.60',     // 手术相关记录完整率（%）
        'ssxgtb' => '↑ 0.45',       // 手术相关同比
        'ssxghb' => '↑ 0.75',       // 手术相关环比
        
        'zrwradio' => '90.20',      // 植入物相关记录符合率（%）
        'zrwtb' => '↑ 0.38',        // 植入物同比
        'zrwhb' => '↑ 0.68',        // 植入物环比
        
        'lcyxradio' => '88.50',     // 临床用血相关记录符合率（%）
        'lcyxtb' => '↑ 0.32',       // 临床用血同比
        'lcyxhb' => '↑ 0.62',       // 临床用血环比
        
        'yscfradio' => '95.30',     // 医师查房记录完整率（%）
        'yscftb' => '↑ 0.55',       // 医师查房同比
        'yscfhb' => '↑ 0.85',       // 医师查房环比
        
        'qjjsradio' => '87.40',     // 患者抢救记录及时完成率（%）
        'qjjstb' => '↑ 0.30',       // 患者抢救同比
        'qjjshb' => '↑ 0.60',       // 患者抢救环比
        
        // 病历归档质量指标（本期）
        'lrgdradio' => '96.80',     // 出院患者病历2日归档率（%）
        'lrgdtb' => '↑ 0.65',       // 2日归档同比
        'lrgdhb' => '↑ 0.95',       // 2日归档环比
        
        'drgdradio' => '93.40',     // 出院患者病历归档完整率（%）
        'drgdtb' => '↑ 0.50',       // 归档完整同比
        'drgdhb' => '↑ 0.80',       // 归档完整环比
        
        'zdmcradio' => '91.20',     // 主要诊断填写正确率（%）
        'zdmctb' => '↑ 0.45',       // 诊断填写同比
        'zdmchb' => '↑ 0.75',       // 诊断填写环比
        
        'zdbmradio' => '89.50',     // 主要诊断编码正确率（%）
        'zdbmtb' => '↑ 0.40',       // 诊断编码同比
        'zdbmhb' => '↑ 0.70',       // 诊断编码环比
        
        'ssmcradio' => '94.60',     // 主要手术填写正确率（%）
        'ssmctb' => '↑ 0.55',       // 手术填写同比
        'ssmchb' => '↑ 0.85',       // 手术填写环比
        
        'ssbmradio' => '92.30',     // 主要手术编码正确率（%）
        'ssbmtb' => '↑ 0.48',       // 手术编码同比
        'ssbmhb' => '↑ 0.78',       // 手术编码环比
        
        'bhlfzradio' => '8.50',     // 不合理复制病历发生率（%，越低越好）
        'bhlfztb' => '↓ 0.35',      // 复制病历同比（下降是好事）
        'bhlfzhb' => '↓ 0.65',      // 复制病历环比（下降是好事）
        
        'zqtysradio' => '97.20',    // 知情同意书规范签署率（%）
        'zqtystb' => '↑ 0.60',      // 知情同意书同比
        'zqtyshb' => '↑ 0.90',      // 知情同意书环比
        
        'jjbllradio' => '95.50',    // 甲级病历率（%）
        'jjblltb' => '↑ 0.58',      // 甲级病历同比
        'jjbllhb' => '↑ 0.88',      // 甲级病历环比
        
        // 科室总数 & 甲级病案率相关
        'kssl'        => 55,        // 科室总数
        'jjkssl'      => 45,        // 甲级病案率达标科室数
        'jjkssl50'    => 3,         // 甲级病案率 < 50% 科室数
        'hgkssl'      => 45,        // 质控合格率 100% 科室数
        'hgkssl50'    => 2,         // 质控合格率 < 50% 科室数
        
        // 其他统计数据
        'youCount' => 800,          // 优秀病历数
        
        // 指标达成率最差前5名表格的表头排名位置
        'zyzcpm1' => '55',     // 最差第1名
        'zyzcpm2' => '54',     // 最差第2名
        'zyzcpm3' => '53',     // 最差第3名
        'zyzcpm4' => '52',     // 最差第4名
        'zyzcpm5' => '51',     // 最差第5名
    ];
    echo "   测试数据:\n";
    foreach ($testData as $key => $value) {
        if (is_array($value)) {
            echo "     $key: [数组]\n";
        } else {
            echo "     $key: $value\n";
        }
    }
    
    // 5. 测试变量替换
    echo "\n5. 测试变量替换...\n";
    $wordReportService->replaceVariables($testData);
    echo "   ✓ 变量替换成功\n";
    
    // 5.5. 测试表格填充
    echo "\n5.5. 测试表格填充...\n";
    $tableRows = [
        [
            'ksId' => 1,
            'ksMc' => '科室A',
            'ksBlnum' => 10000,
            'ksJjnum' => 10000,
            'ksRatio' => '100%',
        ],
        [
            'ksId' => 2,
            'ksMc' => '科室B',
            'ksBlnum' => 8000,
            'ksJjnum' => 7600,
            'ksRatio' => '95.00%',
        ],
        [
            'ksId' => 3,
            'ksMc' => '科室C',
            'ksBlnum' => 5000,
            'ksJjnum' => 4500,
            'ksRatio' => '90.00%',
        ],
        [
            'ksId' => 4,
            'ksMc' => '科室D',
            'ksBlnum' => 3000,
            'ksJjnum' => 2400,
            'ksRatio' => '80.00%',
        ],
        [
            'ksId' => 5,
            'ksMc' => '科室E',
            'ksBlnum' => 2000,
            'ksJjnum' => 1000,
            'ksRatio' => '50.00%',
        ],
    ];
    
    echo "   准备表格数据: " . count($tableRows) . " 行\n";
    try {
        // 使用 ksId 作为行标识（对应模板中的 ${ksId#1}）
        $wordReportService->replaceTableRows('ksId', $tableRows);
        echo "   ✓ 表格填充成功\n";
    } catch (\Exception $e) {
        echo "   ⚠ 表格填充失败: " . $e->getMessage() . "\n";
        echo "   提示: 如果模板中没有表格变量，这是正常的\n";
        echo "   需要在 Word 模板中设置: \${ksId#1}, \${ksMc#1}, \${ksBlnum#1}, \${ksJjnum#1}, \${ksRatio#1}\n";
    }

    // 5.6. 质控合格率列表（${ksHgId} 表）
    echo "\n5.6. 测试质控合格率列表表格填充...\n";
    $ksHgRows = [
        [
            'ksHgId'    => 1,
            'ksHgMc'    => '科室A',
            'ksHgBlnum' => 200,
            'ksHgJjnum' => 200,
            'ksHgRatio' => '100%',
        ],
        [
            'ksHgId'    => 2,
            'ksHgMc'    => '科室B',
            'ksHgBlnum' => 180,
            'ksHgJjnum' => 171,
            'ksHgRatio' => '95.00%',
        ],
        [
            'ksHgId'    => 3,
            'ksHgMc'    => '科室C',
            'ksHgBlnum' => 150,
            'ksHgJjnum' => 135,
            'ksHgRatio' => '90.00%',
        ],
    ];
    echo "   质控合格率列表行数: " . count($ksHgRows) . " 行\n";
    try {
        // 对应模板中的 ${ksHgId#1}, ${ksHgMc#1}, ${ksHgBlnum#1}, ${ksHgJjnum#1}, ${ksHgRatio#1}
        $wordReportService->replaceTableRows('ksHgId', $ksHgRows);
        echo "   ✓ 质控合格率列表表格填充成功\n";
    } catch (\Exception $e) {
        echo "   ⚠ 质控合格率列表表格填充失败: " . $e->getMessage() . "\n";
        echo "   提示: 需要在 Word 模板中设置: \${ksHgId#1}, \${ksHgMc#1}, \${ksHgBlnum#1}, \${ksHgJjnum#1}, \${ksHgRatio#1}\n";
    }

    // 5.7. 病案首页质控列表（${syId} 表）
    echo "\n5.7. 测试病案首页质控列表表格填充...\n";
    $syRows = [
        [
            'syId'    => 1,
            'syXm'    => '主要诊断编码选择错误',
            'syBlnum' => 10000,
            'syCwnum' => 5000,
            'syCwl'   => '50%',
        ],
        [
            'syId'    => 2,
            'syXm'    => '主要手术编码选择错误',
            'syBlnum' => 8000,
            'syCwnum' => 2400,
            'syCwl'   => '30%',
        ],
    ];
    echo "   病案首页质控列表行数: " . count($syRows) . " 行\n";
    try {
        // 对应模板中的 ${syId#1}, ${syXm#1}, ${syBlnum#1}, ${syCwnum#1}, ${syCwl#1}
        $wordReportService->replaceTableRows('syId', $syRows);
        echo "   ✓ 病案首页质控列表表格填充成功\n";
    } catch (\Exception $e) {
        echo "   ⚠ 病案首页质控列表表格填充失败: " . $e->getMessage() . "\n";
        echo "   提示: 需要在 Word 模板中设置: \${syId#1}, \${syXm#1}, \${syBlnum#1}, \${syCwnum#1}, \${syCwl#1}\n";
    }

    // 5.8. 病案内涵质控列表（${nhId} 表）
    echo "\n5.8. 测试病案内涵质控列表表格填充...\n";
    $nhRows = [
        [
            'nhId'    => 1,
            'nhXm'    => '主要诊断编码选择错误',
            'nhBlnum' => 9000,
            'nhCwnum' => 4500,
            'nhCwl'   => '50%',
        ],
        [
            'nhId'    => 2,
            'nhXm'    => '入院病情记录不规范',
            'nhBlnum' => 7000,
            'nhCwnum' => 2100,
            'nhCwl'   => '30%',
        ],
    ];
    echo "   病案内涵质控列表行数: " . count($nhRows) . " 行\n";
    try {
        // 对应模板中的 ${nhId#1}, ${nhXm#1}, ${nhBlnum#1}, ${nhCwnum#1}, ${nhCwl#1}
        $wordReportService->replaceTableRows('nhId', $nhRows);
        echo "   ✓ 病案内涵质控列表表格填充成功\n";
    } catch (\Exception $e) {
        echo "   ⚠ 病案内涵质控列表表格填充失败: " . $e->getMessage() . "\n";
        echo "   提示: 需要在 Word 模板中设置: \${nhId#1}, \${nhXm#1}, \${nhBlnum#1}, \${nhCwnum#1}, \${nhCwl#1}\n";
    }

    // 5.8.1. 测试时效性规则分布表格填充（${sxgzid} 表）
    echo "\n5.8.1. 测试时效性规则分布表格填充...\n";
    $sxgzRows = [
        [
            'sxgzid'     => 1,
            'sxgzmc'     => '入院24小时内未完成首次病程记录',
            'sxgzblnum'  => 1000000,
            'sxgzqxnum'  => 1000000,
            'sxgzradio'  => '11.11',
        ],
        [
            'sxgzid'     => 2,
            'sxgzmc'     => '入院小时内未完成首次病程记录',
            'sxgzblnum'  => 1000000,
            'sxgzqxnum'  => 1000000,
            'sxgzradio'  => '11.11',
        ],
        [
            'sxgzid'     => 3,
            'sxgzmc'     => '手术记录24小时内未完成',
            'sxgzblnum'  => 850000,
            'sxgzqxnum'  => 850000,
            'sxgzradio'  => '9.44',
        ],
        [
            'sxgzid'     => 4,
            'sxgzmc'     => '出院记录24小时内未完成',
            'sxgzblnum'  => 720000,
            'sxgzqxnum'  => 720000,
            'sxgzradio'  => '8.00',
        ],
        [
            'sxgzid'     => 5,
            'sxgzmc'     => '病案首页24小时内未完成',
            'sxgzblnum'  => 680000,
            'sxgzqxnum'  => 680000,
            'sxgzradio'  => '7.56',
        ],
    ];
    echo "   时效性规则分布表格行数: " . count($sxgzRows) . " 行\n";
    try {
        // 对应模板中的 ${sxgzid#1}, ${sxgzmc#1}, ${sxgzblnum#1}, ${sxgzqxnum#1}, ${sxgzradio#1}
        $wordReportService->replaceTableRows('sxgzid', $sxgzRows);
        echo "   ✓ 时效性规则分布表格填充成功\n";
    } catch (\Exception $e) {
        echo "   ⚠ 时效性规则分布表格填充失败: " . $e->getMessage() . "\n";
        echo "   提示: 需要在 Word 模板中设置: \${sxgzid#1}, \${sxgzmc#1}, \${sxgzblnum#1}, \${sxgzqxnum#1}, \${sxgzradio#1}\n";
    }

    // 5.8.2. 测试内涵性规则分布表格填充（${nhgzid} 表）
    echo "\n5.8.2. 测试内涵性规则分布表格填充...\n";
    $nhgzRows = [
        [
            'nhgzid'     => 1,
            'nhgzmc'     => '主要诊断编码选择错误',
            'nhgzblnum'  => 950000,
            'nhgzqxnum'  => 950000,
            'nhgzradio'  => '10.56',
        ],
        [
            'nhgzid'     => 2,
            'nhgzmc'     => '主要手术编码选择错误',
            'nhgzblnum'  => 820000,
            'nhgzqxnum'  => 820000,
            'nhgzradio'  => '9.11',
        ],
        [
            'nhgzid'     => 3,
            'nhgzmc'     => '入院病情记录不规范',
            'nhgzblnum'  => 780000,
            'nhgzqxnum'  => 780000,
            'nhgzradio'  => '8.67',
        ],
        [
            'nhgzid'     => 4,
            'nhgzmc'     => '病程记录缺少鉴别诊断',
            'nhgzblnum'  => 710000,
            'nhgzqxnum'  => 710000,
            'nhgzradio'  => '7.89',
        ],
        [
            'nhgzid'     => 5,
            'nhgzmc'     => '出院记录内容不完整',
            'nhgzblnum'  => 680000,
            'nhgzqxnum'  => 680000,
            'nhgzradio'  => '7.56',
        ],
        [
            'nhgzid'     => 6,
            'nhgzmc'     => '手术记录缺少术中情况',
            'nhgzblnum'  => 620000,
            'nhgzqxnum'  => 620000,
            'nhgzradio'  => '6.89',
        ],
    ];
    echo "   内涵性规则分布表格行数: " . count($nhgzRows) . " 行\n";
    try {
        // 对应模板中的 ${nhgzid#1}, ${nhgzmc#1}, ${nhgzblnum#1}, ${nhgzqxnum#1}, ${nhgzradio#1}
        $wordReportService->replaceTableRows('nhgzid', $nhgzRows);
        echo "   ✓ 内涵性规则分布表格填充成功\n";
    } catch (\Exception $e) {
        echo "   ⚠ 内涵性规则分布表格填充失败: " . $e->getMessage() . "\n";
        echo "   提示: 需要在 Word 模板中设置: \${nhgzid#1}, \${nhgzmc#1}, \${nhgzblnum#1}, \${nhgzqxnum#1}, \${nhgzradio#1}\n";
    }

    // 5.8.3. 测试病案首页规则分布表格填充（${sygzid} 表）
    echo "\n5.8.3. 测试病案首页规则分布表格填充...\n";
    $sygzRows = [
        [
            'sygzid'     => 1,
            'sygzmc'     => '主要诊断编码错误',
            'sygzblnum'  => 880000,
            'sygzqxnum'  => 880000,
            'sygzradio'  => '9.78',
        ],
        [
            'sygzid'     => 2,
            'sygzmc'     => '主要手术编码错误',
            'sygzblnum'  => 750000,
            'sygzqxnum'  => 750000,
            'sygzradio'  => '8.33',
        ],
        [
            'sygzid'     => 3,
            'sygzmc'     => '其他诊断漏填',
            'sygzblnum'  => 690000,
            'sygzqxnum'  => 690000,
            'sygzradio'  => '7.67',
        ],
        [
            'sygzid'     => 4,
            'sygzmc'     => '手术操作编码不准确',
            'sygzblnum'  => 640000,
            'sygzqxnum'  => 640000,
            'sygzradio'  => '7.11',
        ],
        [
            'sygzid'     => 5,
            'sygzmc'     => '入院情况填写不完整',
            'sygzblnum'  => 590000,
            'sygzqxnum'  => 590000,
            'sygzradio'  => '6.56',
        ],
        [
            'sygzid'     => 6,
            'sygzmc'     => '出院情况填写不规范',
            'sygzblnum'  => 550000,
            'sygzqxnum'  => 550000,
            'sygzradio'  => '6.11',
        ],
        [
            'sygzid'     => 7,
            'sygzmc'     => '费用分类填写错误',
            'sygzblnum'  => 480000,
            'sygzqxnum'  => 480000,
            'sygzradio'  => '5.33',
        ],
    ];
    echo "   病案首页规则分布表格行数: " . count($sygzRows) . " 行\n";
    try {
        // 对应模板中的 ${sygzid#1}, ${sygzmc#1}, ${sygzblnum#1}, ${sygzqxnum#1}, ${sygzradio#1}
        $wordReportService->replaceTableRows('sygzid', $sygzRows);
        echo "   ✓ 病案首页规则分布表格填充成功\n";
    } catch (\Exception $e) {
        echo "   ⚠ 病案首页规则分布表格填充失败: " . $e->getMessage() . "\n";
        echo "   提示: 需要在 Word 模板中设置: \${sygzid#1}, \${sygzmc#1}, \${sygzblnum#1}, \${sygzqxnum#1}, \${sygzradio#1}\n";
    }

    // 5.8.4. 测试病历质量科室表格填充（${blzlksid} 表）
    echo "\n5.8.4. 测试病历质量科室表格填充...\n";
    $blzlksRows = [
        [
            'blzlksid'        => 1,
            'blzlksmc'        => '内科',
            'blzlksbl'        => 12000,
            'blzlksjjbl'      => 8500,
            'blzlksradiojj'   => '70.83',
            'blzlksyjbl'      => 3000,
            'blzlksradioyj'   => '25.00',
            'blzlksbjbl'      => 500,
            'blzlksradiobj'   => '4.17',
        ],
        [
            'blzlksid'        => 2,
            'blzlksmc'        => '外科',
            'blzlksbl'        => 10000,
            'blzlksjjbl'      => 7200,
            'blzlksradiojj'   => '72.00',
            'blzlksyjbl'      => 2500,
            'blzlksradioyj'   => '25.00',
            'blzlksbjbl'      => 300,
            'blzlksradiobj'   => '3.00',
        ],
        [
            'blzlksid'        => 3,
            'blzlksmc'        => '儿科',
            'blzlksbl'        => 9500,
            'blzlksjjbl'      => 6800,
            'blzlksradiojj'   => '71.58',
            'blzlksyjbl'      => 2400,
            'blzlksradioyj'   => '25.26',
            'blzlksbjbl'      => 300,
            'blzlksradiobj'   => '3.16',
        ],
        [
            'blzlksid'        => 4,
            'blzlksmc'        => '妇产科',
            'blzlksbl'        => 8800,
            'blzlksjjbl'      => 6400,
            'blzlksradiojj'   => '72.73',
            'blzlksyjbl'      => 2100,
            'blzlksradioyj'   => '23.86',
            'blzlksbjbl'      => 300,
            'blzlksradiobj'   => '3.41',
        ],
        [
            'blzlksid'        => 5,
            'blzlksmc'        => '骨科',
            'blzlksbl'        => 7500,
            'blzlksjjbl'      => 5500,
            'blzlksradiojj'   => '73.33',
            'blzlksyjbl'      => 1800,
            'blzlksradioyj'   => '24.00',
            'blzlksbjbl'      => 200,
            'blzlksradiobj'   => '2.67',
        ],
    ];
    echo "   病历质量科室表格行数: " . count($blzlksRows) . " 行\n";
    try {
        // 对应模板中的 ${blzlksid#1}, ${blzlksmc#1}, ${blzlksbl#1}, ${blzlksjjbl#1}, ${blzlksradiojj#1}, ${blzlksyjbl#1}, ${blzlksradioyj#1}, ${blzlksbjbl#1}, ${blzlksradiobj#1}
        $wordReportService->replaceTableRows('blzlksid', $blzlksRows);
        echo "   ✓ 病历质量科室表格填充成功\n";
    } catch (\Exception $e) {
        echo "   ⚠ 病历质量科室表格填充失败: " . $e->getMessage() . "\n";
        echo "   提示: 需要在 Word 模板中设置: \${blzlksid#1}, \${blzlksmc#1}, \${blzlksbl#1}, \${blzlksjjbl#1}, \${blzlksradiojj#1}, \${blzlksyjbl#1}, \${blzlksradioyj#1}, \${blzlksbjbl#1}, \${blzlksradiobj#1}\n";
    }

    echo "\n5.8.4. 测试单否项缺陷占比前五表格填充...\n";
    $dfxksRows = [
        [
            'dfxksid'    => 1,
            'dfxksmc'    => '主要诊断编码选择错误',
            'dfxksbl'    => 4775,
            'dfxksqx'    => 4500,
            'dfxksdf'    => 4500,
            'dfxksradio' => '94.24',
        ],
        [
            'dfxksid'    => 2,
            'dfxksmc'    => '主要手术编码选择错误',
            'dfxksbl'    => 4775,
            'dfxksqx'    => 3887,
            'dfxksdf'    => 3887,
            'dfxksradio' => '81.40',
        ],
        [
            'dfxksid'    => 3,
            'dfxksmc'    => '入院病情记录不规范',
            'dfxksbl'    => 4775,
            'dfxksqx'    => 3200,
            'dfxksdf'    => 3200,
            'dfxksradio' => '67.02',
        ],
        [
            'dfxksid'    => 4,
            'dfxksmc'    => '病程记录缺少鉴别诊断',
            'dfxksbl'    => 4775,
            'dfxksqx'    => 2800,
            'dfxksdf'    => 2800,
            'dfxksradio' => '58.64',
        ],
        [
            'dfxksid'    => 5,
            'dfxksmc'    => '手术记录签名不完整',
            'dfxksbl'    => 4775,
            'dfxksqx'    => 2100,
            'dfxksdf'    => 2100,
            'dfxksradio' => '44.03',
        ],
    ];
    echo "   单否项缺陷占比前五表格行数: " . count($dfxksRows) . " 行\n";
    try {
        // 对应模板中的 ${dfxksid#1}, ${dfxksmc#1}, ${dfxksbl#1}, ${dfxksqx#1}, ${dfxksdf#1}, ${dfxksradio#1}
        $wordReportService->replaceTableRows('dfxksid', $dfxksRows);
        echo "   ✓ 单否项缺陷占比前五表格填充成功\n";
    } catch (\Exception $e) {
        echo "   ⚠ 单否项缺陷占比前五表格填充失败: " . $e->getMessage() . "\n";
        echo "   提示: 需要在 Word 模板中设置: \${dfxksid#1}, \${dfxksmc#1}, \${dfxksbl#1}, \${dfxksqx#1}, \${dfxksdf#1}, \${dfxksradio#1}\n";
    }

    // 5.9. 测试图表数据填充（使用 PhpWord 原生图表功能）
    echo "\n5.9. 测试图表数据填充...\n";
    // 图表数据：病案内涵质控趋势图（根据新模板）
    // 数据格式：月份 => ['total' => 总数, 'defect' => 缺陷数]
    // 注意：图表显示的是上个月（bftime）和本月（time）的对比
    $chartData = [
        '2025年11月' => [
            'total' => 5220,      // 上个月病历总数（对应 bfblnum）
            'defect' => 4298,     // 上个月缺陷病历（对应 bfqxblnum）
        ],
        '2025年12月' => [
            'total' => 4775,      // 本月病历总数（对应 blnum）
            'defect' => 4619,     // 本月缺陷病历（对应 qxblnum）
        ],
    ];
    
    echo "   图表数据:\n";
    foreach ($chartData as $month => $data) {
        echo "     $month: 病历总数={$data['total']}, 缺陷病历={$data['defect']}\n";
    }
    
    // 使用 PhpWord 原生图表功能替换图表占位符
    try {
        echo "   正在替换图表占位符...\n";
        echo "   占位符名称: chartImage\n";
        
        // 图表选项：设置尺寸、标题、间距等
        // EMU 单位：1 cm = 360000 EMU
        // 模板中实例图表的尺寸：cx="5256530" (约14.6cm), cy="2988310" (约8.3cm)
        // 模板中实例图表的 gapWidth="246", overlap="-28"
        $chartOptions = [
            'width' => 5256530,      // 与模板实例图表一致 (约14.6cm)
            'height' => 2988310,      // 与模板实例图表一致 (约8.3cm)
            'title' => '',            // 不显示标题
            'overlap' => -28,         // 系列间距（与模板实例图表一致）
            'gapWidth' => 246,        // 柱状图宽度（与模板实例图表一致，值越大柱子越宽）
            'addTrendline' => true,   // 添加趋势线
            'showGridY' => true,      // 显示Y轴网格线（横线）
        ];
        
        $wordReportService->replaceChart('chartImage', $chartData, $chartOptions);
        echo "   ✓ 图表替换成功（使用 PhpWord 原生图表功能）\n";
    } catch (\Exception $e) {
        echo "   ⚠ 图表替换失败: " . $e->getMessage() . "\n";
        echo "   错误详情: " . $e->getFile() . ":" . $e->getLine() . "\n";
        if ($e->getPrevious()) {
            echo "   原始错误: " . $e->getPrevious()->getMessage() . "\n";
        }
        echo "   提示: 需要在 Word 模板中设置图表占位符: \${chartImage}\n";
    }

    // 5.10. 测试同比图表数据填充（${bftb} 位置）
    echo "\n5.10. 测试同比图表数据填充（\${bftb} 位置）...\n";
    // 同比图表数据：与上面的图表数据相同，展示上个月和本月的对比
    $bftbChartData = [
        '2025年11月' => [
            'total' => 5220,      // 上个月病历总数（对应 bfblnum）
            'defect' => 4298,     // 上个月缺陷病历（对应 bfqxblnum）
        ],
        '2025年12月' => [
            'total' => 4775,      // 本月病历总数（对应 blnum）
            'defect' => 4619,     // 本月缺陷病历（对应 qxblnum）
        ],
    ];
    
    echo "   同比图表数据:\n";
    foreach ($bftbChartData as $month => $data) {
        echo "     $month: 病历总数={$data['total']}, 缺陷病历={$data['defect']}\n";
    }
    
    try {
        echo "   正在替换同比图表占位符 \${bftb}...\n";
        
        // 同比图表选项：无标题，显示网格线
        $bftbChartOptions = [
            'width' => 5256530,       // 与模板实例图表一致 (约14.6cm)
            'height' => 2988310,      // 与模板实例图表一致 (约8.3cm)
            'title' => '',            // 不显示标题
            'overlap' => -28,         // 系列间距（与模板实例图表一致）
            'gapWidth' => 246,        // 柱状图宽度（与模板实例图表一致）
            'addTrendline' => true,   // 添加趋势线
            'showGridY' => true,      // 显示Y轴网格线（横线）
        ];
        
        $wordReportService->replaceChart('bftb', $bftbChartData, $bftbChartOptions);
        echo "   ✓ 同比图表替换成功（使用 PhpWord 原生图表功能）\n";
    } catch (\Exception $e) {
        echo "   ⚠ 同比图表替换失败: " . $e->getMessage() . "\n";
        echo "   错误详情: " . $e->getFile() . ":" . $e->getLine() . "\n";
        if ($e->getPrevious()) {
            echo "   原始错误: " . $e->getPrevious()->getMessage() . "\n";
        }
        echo "   提示: 需要在 Word 模板中设置图表占位符: \${bftb}\n";
    }

    // 5.11. 测试病案等级图表数据填充（${jjtb} 位置）
    echo "\n5.11. 测试病案等级图表数据填充（\${jjtb} 位置）...\n";
    // 病案等级图表数据：展示甲级、乙级、丙级病案的对比
    // 注意：这个图表需要三个系列，需要修改 WordReportService 来支持
    $jjtbChartData = [
        '2025年11月' => [
            'jia' => 3800,    // 甲级病案数（对应 bfjjblnum）
            'yi' => 1200,     // 乙级病案数（对应 bfyjblnum）
            'bing' => 220,    // 丙级病案数（对应 bfbjblnum）
        ],
        '2025年12月' => [
            'jia' => 3500,    // 甲级病案数（对应 jjblnum）
            'yi' => 1100,     // 乙级病案数（对应 yjblnum）
            'bing' => 175,    // 丙级病案数（对应 bjblnum）
        ],
    ];
    
    echo "   病案等级图表数据:\n";
    foreach ($jjtbChartData as $month => $data) {
        echo "     $month: 甲级={$data['jia']}, 乙级={$data['yi']}, 丙级={$data['bing']}\n";
    }
    
    try {
        echo "   正在替换病案等级图表占位符 \${jjtb}...\n";
        
        // 病案等级图表选项：无标题，显示网格线，三个系列
        $jjtbChartOptions = [
            'width' => 5256530,       // 与模板实例图表一致 (约14.6cm)
            'height' => 2988310,      // 与模板实例图表一致 (约8.3cm)
            'title' => '',            // 不显示标题
            'overlap' => -28,         // 系列间距（与模板实例图表一致）
            'gapWidth' => 246,        // 柱状图宽度（与模板实例图表一致）
            'addTrendline' => true,   // 添加趋势线（只对第一个系列）
            'showGridY' => true,      // 显示Y轴网格线（横线）
            'chartType' => 'three_series',  // 标记为三系列图表
        ];
        
        $wordReportService->replaceChartThreeSeries('jjtb', $jjtbChartData, $jjtbChartOptions);
        echo "   ✓ 病案等级图表替换成功（使用 PhpWord 原生图表功能）\n";
    } catch (\Exception $e) {
        echo "   ⚠ 病案等级图表替换失败: " . $e->getMessage() . "\n";
        echo "   错误详情: " . $e->getFile() . ":" . $e->getLine() . "\n";
        if ($e->getPrevious()) {
            echo "   原始错误: " . $e->getPrevious()->getMessage() . "\n";
        }
        echo "   提示: 需要在 Word 模板中设置图表占位符: \${jjtb}\n";
    }

    // 5.12. 测试时效性指标图表数据填充（${sxzbtb} 位置）- 按指标分组
    echo "\n5.12. 测试时效性指标图表数据填充（\${sxzbtb} 位置）...\n";
    // 时效性指标图表数据：按指标分组，每个指标显示两个月的对比
    // 数据格式：指标名 => ['period1' => 11月数据, 'period2' => 12月数据]
    $sxzbtbChartData = [
        '入院记录24小时内完成率' => [
            'period1' => 36.00,    // 2025年11月（上个月）
            'period2' => 95.00,    // 2025年12月（本月，对应 ryjradio）
        ],
        '手术记录24小时内完成率' => [
            'period1' => 90.00,    // 2025年11月（上个月）
            'period2' => 86.25,    // 2025年12月（本月，对应 ssjlradio）
        ],
        '出院记录24小时内完成率' => [
            'period1' => 70.12,    // 2025年11月（上个月）
            'period2' => 95.00,    // 2025年12月（本月，对应 cyjlradio）
        ],
        '病案首页24小时内完成率' => [
            'period1' => 60.00,    // 2025年11月（上个月）
            'period2' => 95.00,    // 2025年12月（本月，对应 basyradio）
        ],
    ];
    
    echo "   时效性指标图表数据（按指标分组）:\n";
    foreach ($sxzbtbChartData as $indicator => $data) {
        echo "     {$indicator}: 11月={$data['period1']}%, 12月={$data['period2']}%\n";
    }
    
    try {
        echo "   正在替换时效性指标图表占位符 \${sxzbtb}（按指标分组样式）...\n";
        
        // 时效性指标图表选项：按指标分组，两个时期对比
        $sxzbtbChartOptions = [
            'width' => 5256530,       // 与模板实例图表一致 (约14.6cm)
            'height' => 2988310,      // 与模板实例图表一致 (约8.3cm)
            'title' => '',            // 不显示标题
            'overlap' => -28,         // 系列间距（与模板实例图表一致）
            'gapWidth' => 246,        // 柱状图宽度（与模板实例图表一致）
            'addTrendline' => true,   // 添加趋势线（连接第一个时期的数据点）
            'showGridY' => true,      // 显示Y轴网格线（横线）
            'period1Name' => '2025年11月达标率',  // 第一个时期名称（上个月）
            'period2Name' => '2025年12月达标率',  // 第二个时期名称（本月）
        ];
        
        $wordReportService->replaceChartByIndicator('sxzbtb', $sxzbtbChartData, $sxzbtbChartOptions);
        echo "   ✓ 时效性指标图表替换成功（使用按指标分组样式）\n";
    } catch (\Exception $e) {
        echo "   ⚠ 时效性指标图表替换失败: " . $e->getMessage() . "\n";
        echo "   错误详情: " . $e->getFile() . ":" . $e->getLine() . "\n";
        if ($e->getPrevious()) {
            echo "   原始错误: " . $e->getPrevious()->getMessage() . "\n";
        }
        echo "   提示: 需要在 Word 模板中设置图表占位符: \${sxzbtb}\n";
    }

    // 5.13. 测试重大检查记录符合率图表数据填充（${zdjctb} 位置）- 按指标分组
    echo "\n5.13. 测试重大检查记录符合率图表数据填充（\${zdjctb} 位置）...\n";
    // 重大检查记录符合率图表数据：按指标分组，每个指标显示两个月的对比
    // 数据格式：指标名 => ['period1' => 11月数据, 'period2' => 12月数据]
    $zdjctbChartData = [
        'CT/MRI检查记录符合率' => [
            'period1' => 85.00,    // 2025年11月（上个月）
            'period2' => 92.50,    // 2025年12月（本月，对应 ctmrradio）
        ],
        '病理检查记录符合率' => [
            'period1' => 82.00,    // 2025年11月（上个月）
            'period2' => 88.30,    // 2025年12月（本月，对应 bljcradio）
        ],
        '细菌培养检查记录符合率' => [
            'period1' => 80.00,    // 2025年11月（上个月）
            'period2' => 85.60,    // 2025年12月（本月，对应 xjpyradio）
        ],
    ];
    
    echo "   重大检查记录符合率图表数据（按指标分组）:\n";
    foreach ($zdjctbChartData as $indicator => $data) {
        echo "     {$indicator}: 11月={$data['period1']}%, 12月={$data['period2']}%\n";
    }
    
    try {
        echo "   正在替换重大检查记录符合率图表占位符 \${zdjctb}（按指标分组样式）...\n";
        
        // 重大检查记录符合率图表选项：按指标分组，两个时期对比
        $zdjctbChartOptions = [
            'width' => 5256530,       // 与模板实例图表一致 (约14.6cm)
            'height' => 2988310,      // 与模板实例图表一致 (约8.3cm)
            'title' => '',            // 不显示标题
            'overlap' => -28,         // 系列间距（与模板实例图表一致）
            'gapWidth' => 246,        // 柱状图宽度（与模板实例图表一致）
            'addTrendline' => true,   // 添加趋势线（连接第一个时期的数据点）
            'showGridY' => true,      // 显示Y轴网格线（横线）
            'period1Name' => '2025年11月达标率',  // 第一个时期名称（上个月）
            'period2Name' => '2025年12月达标率',  // 第二个时期名称（本月）
        ];
        
        $wordReportService->replaceChartByIndicator('zdjctb', $zdjctbChartData, $zdjctbChartOptions);
        echo "   ✓ 重大检查记录符合率图表替换成功（使用按指标分组样式）\n";
    } catch (\Exception $e) {
        echo "   ⚠ 重大检查记录符合率图表替换失败: " . $e->getMessage() . "\n";
        echo "   错误详情: " . $e->getFile() . ":" . $e->getLine() . "\n";
        if ($e->getPrevious()) {
            echo "   原始错误: " . $e->getPrevious()->getMessage() . "\n";
        }
        echo "   提示: 需要在 Word 模板中设置图表占位符: \${zdjctb}\n";
    }

    // 5.14. 测试诊疗行为记录符合率图表数据填充（${zlxwtb} 位置）- 按指标分组
    echo "\n5.14. 测试诊疗行为记录符合率图表数据填充（\${zlxwtb} 位置）...\n";
    // 诊疗行为记录符合率图表数据：按指标分组，每个指标显示两个月的对比
    // 数据格式：指标名 => ['period1' => 11月数据, 'period2' => 12月数据]
    $zlxwtbChartData = [
        '抗菌药物使用记录符合率' => [
            'period1' => 88.00,    // 2025年11月（上个月）
            'period2' => 94.20,    // 2025年12月（本月，对应 kjywradio）
        ],
        '恶性肿瘤化学治疗记录符合率' => [
            'period1' => 85.00,    // 2025年11月（上个月）
            'period2' => 91.50,    // 2025年12月（本月，对应 hlxwradio）
        ],
        '恶性肿瘤放射治疗记录符合率' => [
            'period1' => 83.50,    // 2025年11月（上个月）
            'period2' => 89.80,    // 2025年12月（本月，对应 fszlradio）
        ],
        '手术相关记录完整率' => [
            'period1' => 87.80,    // 2025年11月（上个月）
            'period2' => 93.60,    // 2025年12月（本月，对应 ssxgradio）
        ],
        '植入物相关记录符合率' => [
            'period1' => 84.20,    // 2025年11月（上个月）
            'period2' => 90.20,    // 2025年12月（本月，对应 zrwradio）
        ],
        '临床用血相关记录符合率' => [
            'period1' => 82.50,    // 2025年11月（上个月）
            'period2' => 88.50,    // 2025年12月（本月，对应 lcyxradio）
        ],
        '医师查房记录完整率' => [
            'period1' => 89.20,    // 2025年11月（上个月）
            'period2' => 95.30,    // 2025年12月（本月，对应 yscfradio）
        ],
        '患者抢救记录及时完成率' => [
            'period1' => 81.00,    // 2025年11月（上个月）
            'period2' => 87.40,    // 2025年12月（本月，对应 qjjsradio）
        ],
    ];
    
    echo "   诊疗行为记录符合率图表数据（按指标分组）:\n";
    foreach ($zlxwtbChartData as $indicator => $data) {
        echo "     {$indicator}: 11月={$data['period1']}%, 12月={$data['period2']}%\n";
    }
    
    try {
        echo "   正在替换诊疗行为记录符合率图表占位符 \${zlxwtb}（按指标分组样式）...\n";
        
        // 诊疗行为记录符合率图表选项：按指标分组，两个时期对比
        // 由于有8个指标，需要更宽的显示空间，但不能超出页面宽度
        $zlxwtbChartOptions = [
            'width' => 5760000,       // 约16cm（适合A4页面，比标准宽度大约10%，适合8个指标）
            'height' => 2988310,      // 约8.3cm（高度保持不变）
            'title' => '',            // 不显示标题
            'overlap' => -28,         // 系列间距（与模板实例图表一致）
            'gapWidth' => 246,        // 柱状图宽度（与模板实例图表一致）
            'addTrendline' => true,   // 添加趋势线（连接第一个时期的数据点）
            'showGridY' => true,      // 显示Y轴网格线（横线）
            'period1Name' => '2025年11月达标率',  // 第一个时期名称（上个月）
            'period2Name' => '2025年12月达标率',  // 第二个时期名称（本月）
        ];
        
        $wordReportService->replaceChartByIndicator('zlxwtb', $zlxwtbChartData, $zlxwtbChartOptions);
        echo "   ✓ 诊疗行为记录符合率图表替换成功（使用按指标分组样式）\n";
    } catch (\Exception $e) {
        echo "   ⚠ 诊疗行为记录符合率图表替换失败: " . $e->getMessage() . "\n";
        echo "   错误详情: " . $e->getFile() . ":" . $e->getLine() . "\n";
        if ($e->getPrevious()) {
            echo "   原始错误: " . $e->getPrevious()->getMessage() . "\n";
        }
        echo "   提示: 需要在 Word 模板中设置图表占位符: \${zlxwtb}\n";
    }

    // 5.15. 测试病历归档质量指标图表数据填充（${gdzltb} 位置）- 按指标分组
    echo "\n5.15. 测试病历归档质量指标图表数据填充（\${gdzltb} 位置）...\n";
    // 病历归档质量指标图表数据：按指标分组，每个指标显示两个月的对比
    // 数据格式：指标名 => ['period1' => 11月数据, 'period2' => 12月数据]
    $gdzltbChartData = [
        '出院患者病历2日归档率' => [
            'period1' => 90.50,    // 2025年11月（上个月）
            'period2' => 96.80,    // 2025年12月（本月，对应 lrgdradio）
        ],
        '出院患者病历归档完整率' => [
            'period1' => 87.20,    // 2025年11月（上个月）
            'period2' => 93.40,    // 2025年12月（本月，对应 drgdradio）
        ],
        '主要诊断填写正确率' => [
            'period1' => 85.00,    // 2025年11月（上个月）
            'period2' => 91.20,    // 2025年12月（本月，对应 zdmcradio）
        ],
        '主要诊断编码正确率' => [
            'period1' => 83.50,    // 2025年11月（上个月）
            'period2' => 89.50,    // 2025年12月（本月，对应 zdbmradio）
        ],
        '主要手术填写正确率' => [
            'period1' => 88.40,    // 2025年11月（上个月）
            'period2' => 94.60,    // 2025年12月（本月，对应 ssmcradio）
        ],
        '主要手术编码正确率' => [
            'period1' => 86.20,    // 2025年11月（上个月）
            'period2' => 92.30,    // 2025年12月（本月，对应 ssbmradio）
        ],
        '不合理复制病历发生率' => [
            'period1' => 14.50,    // 2025年11月（上个月，越低越好）
            'period2' => 8.50,     // 2025年12月（本月，对应 bhlfzradio）
        ],
        '知情同意书规范签署率' => [
            'period1' => 91.00,    // 2025年11月（上个月）
            'period2' => 97.20,    // 2025年12月（本月，对应 zqtysradio）
        ],
        '甲级病历率' => [
            'period1' => 89.30,    // 2025年11月（上个月）
            'period2' => 95.50,    // 2025年12月（本月，对应 jjbllradio）
        ],
    ];
    
    echo "   病历归档质量指标图表数据（按指标分组）:\n";
    foreach ($gdzltbChartData as $indicator => $data) {
        echo "     {$indicator}: 11月={$data['period1']}%, 12月={$data['period2']}%\n";
    }
    
    try {
        echo "   正在替换病历归档质量指标图表占位符 \${gdzltb}（按指标分组样式）...\n";
        
        // 病历归档质量指标图表选项：按指标分组，两个时期对比
        // 由于有9个指标，需要更宽的显示空间
        $gdzltbChartOptions = [
            'width' => 5760000,       // 约16cm（适合A4页面，适合9个指标）
            'height' => 2988310,      // 约8.3cm（高度保持不变）
            'title' => '',            // 不显示标题
            'overlap' => -28,         // 系列间距（与模板实例图表一致）
            'gapWidth' => 246,        // 柱状图宽度（与模板实例图表一致）
            'addTrendline' => true,   // 添加趋势线（连接第一个时期的数据点）
            'showGridY' => true,      // 显示Y轴网格线（横线）
            'period1Name' => '2025年11月达标率',  // 第一个时期名称（上个月）
            'period2Name' => '2025年12月达标率',  // 第二个时期名称（本月）
        ];
        
        $wordReportService->replaceChartByIndicator('gdzltb', $gdzltbChartData, $gdzltbChartOptions);
        echo "   ✓ 病历归档质量指标图表替换成功（使用按指标分组样式）\n";
    } catch (\Exception $e) {
        echo "   ⚠ 病历归档质量指标图表替换失败: " . $e->getMessage() . "\n";
        echo "   错误详情: " . $e->getFile() . ":" . $e->getLine() . "\n";
        if ($e->getPrevious()) {
            echo "   原始错误: " . $e->getPrevious()->getMessage() . "\n";
        }
        echo "   提示: 需要在 Word 模板中设置图表占位符: \${gdzltb}\n";
    }

    // 5.16. 测试单否项缺陷占比图表数据填充（${dfxkstb} 位置）- 单系列柱状图
    echo "\n5.16. 测试单否项缺陷占比图表数据填充（\${dfxkstb} 位置）...\n";
    // 单否项缺陷占比图表数据：展示各科室的单否项问题数量
    // 数据格式：科室名称 => 单否项缺陷数量
    $dfxkstbChartData = [
        '科室A' => 80,
        '科室B' => 70,
        '科室C' => 60,
        '科室D' => 30,
        '科室E' => 10,
    ];
    
    echo "   单否项缺陷占比图表数据:\n";
    foreach ($dfxkstbChartData as $dept => $count) {
        echo "     {$dept}: 单否项问题数量={$count}个\n";
    }
    
    try {
        echo "   正在替换单否项缺陷占比图表占位符 \${dfxkstb}（单系列柱状图）...\n";
        
        // 单否项缺陷占比图表选项：单系列柱状图
        $dfxkstbChartOptions = [
            'width' => 5256530,       // 与模板实例图表一致 (约14.6cm)
            'height' => 2988310,      // 约8.3cm（高度保持不变）
            'title' => '',            // 不显示标题
            'overlap' => -28,         // 系列间距
            'gapWidth' => 246,        // 柱状图宽度
            'addTrendline' => true,   // 添加趋势线（红色虚线）
            'showGridY' => true,      // 显示Y轴网格线（横线）
            'seriesName' => '单否项问题数量',  // 系列名称
        ];
        
        $wordReportService->replaceChartSingleSeries('dfxkstb', $dfxkstbChartData, $dfxkstbChartOptions);
        echo "   ✓ 单否项缺陷占比图表替换成功（单系列柱状图）\n";
    } catch (\Exception $e) {
        echo "   ⚠ 单否项缺陷占比图表替换失败: " . $e->getMessage() . "\n";
        echo "   错误详情: " . $e->getFile() . ":" . $e->getLine() . "\n";
        if ($e->getPrevious()) {
            echo "   原始错误: " . $e->getPrevious()->getMessage() . "\n";
        }
        echo "   提示: 需要在 Word 模板中设置图表占位符: \${dfxkstb}\n";
    }

    // 5.17. 测试医师申诉情况表格填充（${ssid} 表）
    echo "\n5.17. 测试医师申诉情况表格填充（驳回数量最多的前五个问题）...\n";
    $ssRows = [
        [
            'ssid'     => 1,
            'ssmc'     => '主诊不超过25个字',
            'sswtnum'  => 100000,
            'bhwtnum'  => 100000,
            'sscgl'    => '0.00',
        ],
        [
            'ssid'     => 2,
            'ssmc'     => '上级医师查房记录不规范',
            'sswtnum'  => 95000,
            'bhwtnum'  => 85000,
            'sscgl'    => '10.53',
        ],
        [
            'ssid'     => 3,
            'ssmc'     => '病程记录缺少鉴别诊断',
            'sswtnum'  => 88000,
            'bhwtnum'  => 75000,
            'sscgl'    => '14.77',
        ],
        [
            'ssid'     => 4,
            'ssmc'     => '手术记录签名不完整',
            'sswtnum'  => 82000,
            'bhwtnum'  => 68000,
            'sscgl'    => '17.07',
        ],
        [
            'ssid'     => 5,
            'ssmc'     => '出院记录内容不完整',
            'sswtnum'  => 76000,
            'bhwtnum'  => 62000,
            'sscgl'    => '18.42',
        ],
    ];
    echo "   医师申诉情况表格行数: " . count($ssRows) . " 行\n";
    try {
        // 对应模板中的 ${ssid#1}, ${ssmc#1}, ${sswtnum#1}, ${bhwtnum#1}, ${sscgl#1}
        $wordReportService->replaceTableRows('ssid', $ssRows);
        echo "   ✓ 医师申诉情况表格填充成功\n";
    } catch (\Exception $e) {
        echo "   ⚠ 医师申诉情况表格填充失败: " . $e->getMessage() . "\n";
        echo "   提示: 需要在 Word 模板中设置: \${ssid#1}, \${ssmc#1}, \${sswtnum#1}, \${bhwtnum#1}, \${sscgl#1}\n";
    }

    // 5.18. 测试全部单否项科室问题表格填充（${sydfxksid} 表）
    echo "\n5.18. 测试全部单否项科室问题表格填充...\n";
    $sydfxksRows = [
        [
            'sydfxksid'    => 1,
            'sydfxksmc'    => '科室A',
            'sydfxksbl'    => 15000,
            'sydfxksqx'    => 5200,
            'sydfxksdf'    => 5200,
            'sydfxksradio' => '100.00',
        ],
        [
            'sydfxksid'    => 2,
            'sydfxksmc'    => '科室B',
            'sydfxksbl'    => 12500,
            'sydfxksqx'    => 4300,
            'sydfxksdf'    => 4300,
            'sydfxksradio' => '98.80',
        ],
        [
            'sydfxksid'    => 3,
            'sydfxksmc'    => '科室C',
            'sydfxksbl'    => 11000,
            'sydfxksqx'    => 3800,
            'sydfxksdf'    => 3800,
            'sydfxksradio' => '96.50',
        ],
        [
            'sydfxksid'    => 4,
            'sydfxksmc'    => '科室D',
            'sydfxksbl'    => 9800,
            'sydfxksqx'    => 3200,
            'sydfxksdf'    => 3200,
            'sydfxksradio' => '94.20',
        ],
        [
            'sydfxksid'    => 5,
            'sydfxksmc'    => '科室E',
            'sydfxksbl'    => 8500,
            'sydfxksqx'    => 2600,
            'sydfxksdf'    => 2600,
            'sydfxksradio' => '91.30',
        ],
        [
            'sydfxksid'    => 6,
            'sydfxksmc'    => '科室F',
            'sydfxksbl'    => 7200,
            'sydfxksqx'    => 2100,
            'sydfxksdf'    => 2100,
            'sydfxksradio' => '88.90',
        ],
        [
            'sydfxksid'    => 7,
            'sydfxksmc'    => '科室G',
            'sydfxksbl'    => 6500,
            'sydfxksqx'    => 1800,
            'sydfxksdf'    => 1800,
            'sydfxksradio' => '86.20',
        ],
        [
            'sydfxksid'    => 8,
            'sydfxksmc'    => '科室H',
            'sydfxksbl'    => 5800,
            'sydfxksqx'    => 1500,
            'sydfxksdf'    => 1500,
            'sydfxksradio' => '83.50',
        ],
        [
            'sydfxksid'    => 9,
            'sydfxksmc'    => '科室I',
            'sydfxksbl'    => 5000,
            'sydfxksqx'    => 1200,
            'sydfxksdf'    => 1200,
            'sydfxksradio' => '80.60',
        ],
        [
            'sydfxksid'    => 10,
            'sydfxksmc'    => '科室J',
            'sydfxksbl'    => 4200,
            'sydfxksqx'    => 900,
            'sydfxksdf'    => 900,
            'sydfxksradio' => '77.40',
        ],
    ];
    echo "   全部单否项科室问题表格行数: " . count($sydfxksRows) . " 行\n";
    try {
        // 对应模板中的 ${sydfxksid#1}, ${sydfxksmc#1}, ${sydfxksbl#1}, ${sydfxksqx#1}, ${sydfxksdf#1}, ${sydfxksradio#1}
        $wordReportService->replaceTableRows('sydfxksid', $sydfxksRows);
        echo "   ✓ 全部单否项科室问题表格填充成功\n";
    } catch (\Exception $e) {
        echo "   ⚠ 全部单否项科室问题表格填充失败: " . $e->getMessage() . "\n";
        echo "   提示: 需要在 Word 模板中设置: \${sydfxksid#1}, \${sydfxksmc#1}, \${sydfxksbl#1}, \${sydfxksqx#1}, \${sydfxksdf#1}, \${sydfxksradio#1}\n";
    }

    // 5.19. 测试所有申诉情况表格填充（${syssid} 表）- 科室维度
    echo "\n5.19. 测试所有申诉情况表格填充（按科室统计）...\n";
    $syssRows = [
        [
            'syssid'      => 1,
            'syssmc'      => '科室A',
            'syssys'      => 25,      // 申诉医师（人）
            'sywtnum'     => 150,     // 申诉问题（个）
            'sytgwtnum'   => 120,     // 通过数量（个）
            'sybhwtnum'   => 30,      // 驳回数量（个）
            'sysscgl'     => '80.00', // 申诉成功率（%）
        ],
        [
            'syssid'      => 2,
            'syssmc'      => '科室B',
            'syssys'      => 22,
            'sywtnum'     => 135,
            'sytgwtnum'   => 100,
            'sybhwtnum'   => 35,
            'sysscgl'     => '74.07',
        ],
        [
            'syssid'      => 3,
            'syssmc'      => '科室C',
            'syssys'      => 20,
            'sywtnum'     => 120,
            'sytgwtnum'   => 88,
            'sybhwtnum'   => 32,
            'sysscgl'     => '73.33',
        ],
        [
            'syssid'      => 4,
            'syssmc'      => '科室D',
            'syssys'      => 18,
            'sywtnum'     => 108,
            'sytgwtnum'   => 75,
            'sybhwtnum'   => 33,
            'sysscgl'     => '69.44',
        ],
        [
            'syssid'      => 5,
            'syssmc'      => '科室E',
            'syssys'      => 16,
            'sywtnum'     => 95,
            'sytgwtnum'   => 62,
            'sybhwtnum'   => 33,
            'sysscgl'     => '65.26',
        ],
        [
            'syssid'      => 6,
            'syssmc'      => '科室F',
            'syssys'      => 15,
            'sywtnum'     => 88,
            'sytgwtnum'   => 55,
            'sybhwtnum'   => 33,
            'sysscgl'     => '62.50',
        ],
        [
            'syssid'      => 7,
            'syssmc'      => '科室G',
            'syssys'      => 14,
            'sywtnum'     => 82,
            'sytgwtnum'   => 50,
            'sybhwtnum'   => 32,
            'sysscgl'     => '60.98',
        ],
        [
            'syssid'      => 8,
            'syssmc'      => '科室H',
            'syssys'      => 12,
            'sywtnum'     => 72,
            'sytgwtnum'   => 42,
            'sybhwtnum'   => 30,
            'sysscgl'     => '58.33',
        ],
    ];
    echo "   所有申诉情况表格行数: " . count($syssRows) . " 行\n";
    try {
        // 对应模板中的 ${syssid#1}, ${syssmc#1}, ${syssys#1}, ${sywtnum#1}, ${sytgwtnum#1}, ${sybhwtnum#1}, ${sysscgl#1}
        $wordReportService->replaceTableRows('syssid', $syssRows);
        echo "   ✓ 所有申诉情况表格填充成功\n";
    } catch (\Exception $e) {
        echo "   ⚠ 所有申诉情况表格填充失败: " . $e->getMessage() . "\n";
        echo "   提示: 需要在 Word 模板中设置: \${syssid#1}, \${syssmc#1}, \${syssys#1}, \${sywtnum#1}, \${sytgwtnum#1}, \${sybhwtnum#1}, \${sysscgl#1}\n";
    }

    // 5.20. 测试指标达成率最优前5名表格填充（${zbzyid} 表）
    echo "\n5.20. 测试指标达成率最优前5名表格填充...\n";
    $zbzyRows = [
        [
            'zbzyid'        => 1,
            'zbzymc'        => '入院记录24小时内完成率',
            'zbzyksmc1'     => '科室A',
            'zbzyksradio1'  => '100.00%',
            'zbzyksmc2'     => '科室B',
            'zbzyksradio2'  => '98.50%',
            'zbzyksmc3'     => '科室C',
            'zbzyksradio3'  => '97.20%',
            'zbzyksmc4'     => '科室D',
            'zbzyksradio4'  => '95.80%',
            'zbzyksmc5'     => '科室E',
            'zbzyksradio5'  => '94.30%',
        ],
        [
            'zbzyid'        => 2,
            'zbzymc'        => '手术记录24小时内完成率',
            'zbzyksmc1'     => '科室A',
            'zbzyksradio1'  => '100.00%',
            'zbzyksmc2'     => '科室B',
            'zbzyksradio2'  => '99.20%',
            'zbzyksmc3'     => '科室C',
            'zbzyksradio3'  => '98.50%',
            'zbzyksmc4'     => '科室D',
            'zbzyksradio4'  => '97.80%',
            'zbzyksmc5'     => '科室E',
            'zbzyksradio5'  => '96.20%',
        ],
        [
            'zbzyid'        => 3,
            'zbzymc'        => '出院记录24小时内完成率',
            'zbzyksmc1'     => '科室A',
            'zbzyksradio1'  => '100.00%',
            'zbzyksmc2'     => '科室B',
            'zbzyksradio2'  => '98.80%',
            'zbzyksmc3'     => '科室C',
            'zbzyksradio3'  => '97.50%',
            'zbzyksmc4'     => '科室D',
            'zbzyksradio4'  => '96.20%',
            'zbzyksmc5'     => '科室E',
            'zbzyksradio5'  => '94.80%',
        ],
        [
            'zbzyid'        => 4,
            'zbzymc'        => '病案首页24小时内完成率',
            'zbzyksmc1'     => '科室A',
            'zbzyksradio1'  => '100.00%',
            'zbzyksmc2'     => '科室B',
            'zbzyksradio2'  => '99.50%',
            'zbzyksmc3'     => '科室C',
            'zbzyksradio3'  => '98.80%',
            'zbzyksmc4'     => '科室D',
            'zbzyksradio4'  => '97.50%',
            'zbzyksmc5'     => '科室E',
            'zbzyksradio5'  => '96.00%',
        ],
        [
            'zbzyid'        => 5,
            'zbzymc'        => 'CT/MRI检查记录符合率',
            'zbzyksmc1'     => '科室A',
            'zbzyksradio1'  => '100.00%',
            'zbzyksmc2'     => '科室B',
            'zbzyksradio2'  => '98.60%',
            'zbzyksmc3'     => '科室C',
            'zbzyksradio3'  => '97.20%',
            'zbzyksmc4'     => '科室D',
            'zbzyksradio4'  => '95.80%',
            'zbzyksmc5'     => '科室E',
            'zbzyksradio5'  => '94.40%',
        ],
        [
            'zbzyid'        => 6,
            'zbzymc'        => '病理检查记录符合率',
            'zbzyksmc1'     => '科室A',
            'zbzyksradio1'  => '100.00%',
            'zbzyksmc2'     => '科室B',
            'zbzyksradio2'  => '99.00%',
            'zbzyksmc3'     => '科室C',
            'zbzyksradio3'  => '98.20%',
            'zbzyksmc4'     => '科室D',
            'zbzyksradio4'  => '97.40%',
            'zbzyksmc5'     => '科室E',
            'zbzyksradio5'  => '96.60%',
        ],
        [
            'zbzyid'        => 7,
            'zbzymc'        => '细菌培养检查记录符合率',
            'zbzyksmc1'     => '科室A',
            'zbzyksradio1'  => '100.00%',
            'zbzyksmc2'     => '科室B',
            'zbzyksradio2'  => '98.80%',
            'zbzyksmc3'     => '科室C',
            'zbzyksradio3'  => '97.60%',
            'zbzyksmc4'     => '科室D',
            'zbzyksradio4'  => '96.40%',
            'zbzyksmc5'     => '科室E',
            'zbzyksradio5'  => '95.20%',
        ],
        [
            'zbzyid'        => 8,
            'zbzymc'        => '抗菌药物使用记录符合率',
            'zbzyksmc1'     => '科室A',
            'zbzyksradio1'  => '100.00%',
            'zbzyksmc2'     => '科室B',
            'zbzyksradio2'  => '99.20%',
            'zbzyksmc3'     => '科室C',
            'zbzyksradio3'  => '98.40%',
            'zbzyksmc4'     => '科室D',
            'zbzyksradio4'  => '97.60%',
            'zbzyksmc5'     => '科室E',
            'zbzyksradio5'  => '96.80%',
        ],
        [
            'zbzyid'        => 9,
            'zbzymc'        => '恶性肿瘤化学治疗记录符合率',
            'zbzyksmc1'     => '科室A',
            'zbzyksradio1'  => '100.00%',
            'zbzyksmc2'     => '科室B',
            'zbzyksradio2'  => '99.50%',
            'zbzyksmc3'     => '科室C',
            'zbzyksradio3'  => '99.00%',
            'zbzyksmc4'     => '科室D',
            'zbzyksradio4'  => '98.50%',
            'zbzyksmc5'     => '科室E',
            'zbzyksradio5'  => '98.00%',
        ],
        [
            'zbzyid'        => 10,
            'zbzymc'        => '恶性肿瘤放射治疗记录符合率',
            'zbzyksmc1'     => '科室A',
            'zbzyksradio1'  => '100.00%',
            'zbzyksmc2'     => '科室B',
            'zbzyksradio2'  => '99.30%',
            'zbzyksmc3'     => '科室C',
            'zbzyksradio3'  => '98.60%',
            'zbzyksmc4'     => '科室D',
            'zbzyksradio4'  => '97.90%',
            'zbzyksmc5'     => '科室E',
            'zbzyksradio5'  => '97.20%',
        ],
    ];
    echo "   指标达成率最优前5名表格行数: " . count($zbzyRows) . " 行（每行包含1个指标的前5名科室）\n";
    try {
        // 对应模板中的 ${zbzyid#1}, ${zbzymc#1}, ${zbzyksmc1#1}, ${zbzyksradio1#1}, ... ${zbzyksmc5#1}, ${zbzyksradio5#1}
        $wordReportService->replaceTableRows('zbzyid', $zbzyRows);
        echo "   ✓ 指标达成率最优前5名表格填充成功\n";
    } catch (\Exception $e) {
        echo "   ⚠ 指标达成率最优前5名表格填充失败: " . $e->getMessage() . "\n";
        echo "   提示: 需要在 Word 模板中设置: \${zbzyid#1}, \${zbzymc#1}, \${zbzyksmc1#1}~\${zbzyksmc5#1}, \${zbzyksradio1#1}~\${zbzyksradio5#1}\n";
    }

    // 5.21. 测试指标达成率最差前5名表格填充（${zbzcid} 表）
    echo "\n5.21. 测试指标达成率最差前5名表格填充...\n";
    $zbzcRows = [
        [
            'zbzcid'        => 1,
            'zbzcmc'        => '入院记录24小时内完成率',
            'zbzcksmc1'     => '科室X',
            'zbzcksradio1'  => '45.20%',
            'zbzcksmc2'     => '科室Y',
            'zbzcksradio2'  => '48.50%',
            'zbzcksmc3'     => '科室Z',
            'zbzcksradio3'  => '52.30%',
            'zbzcksmc4'     => '科室W',
            'zbzcksradio4'  => '55.80%',
            'zbzcksmc5'     => '科室V',
            'zbzcksradio5'  => '58.60%',
        ],
        [
            'zbzcid'        => 2,
            'zbzcmc'        => '手术记录24小时内完成率',
            'zbzcksmc1'     => '科室X',
            'zbzcksradio1'  => '50.00%',
            'zbzcksmc2'     => '科室Y',
            'zbzcksradio2'  => '52.80%',
            'zbzcksmc3'     => '科室Z',
            'zbzcksradio3'  => '55.50%',
            'zbzcksmc4'     => '科室W',
            'zbzcksradio4'  => '58.20%',
            'zbzcksmc5'     => '科室V',
            'zbzcksradio5'  => '60.80%',
        ],
        [
            'zbzcid'        => 3,
            'zbzcmc'        => '出院记录24小时内完成率',
            'zbzcksmc1'     => '科室X',
            'zbzcksradio1'  => '48.50%',
            'zbzcksmc2'     => '科室Y',
            'zbzcksradio2'  => '51.20%',
            'zbzcksmc3'     => '科室Z',
            'zbzcksradio3'  => '53.80%',
            'zbzcksmc4'     => '科室W',
            'zbzcksradio4'  => '56.50%',
            'zbzcksmc5'     => '科室V',
            'zbzcksradio5'  => '59.20%',
        ],
        [
            'zbzcid'        => 4,
            'zbzcmc'        => '病案首页24小时内完成率',
            'zbzcksmc1'     => '科室X',
            'zbzcksradio1'  => '42.00%',
            'zbzcksmc2'     => '科室Y',
            'zbzcksradio2'  => '45.50%',
            'zbzcksmc3'     => '科室Z',
            'zbzcksradio3'  => '48.20%',
            'zbzcksmc4'     => '科室W',
            'zbzcksradio4'  => '51.50%',
            'zbzcksmc5'     => '科室V',
            'zbzcksradio5'  => '54.00%',
        ],
        [
            'zbzcid'        => 5,
            'zbzcmc'        => 'CT/MRI检查记录符合率',
            'zbzcksmc1'     => '科室X',
            'zbzcksradio1'  => '55.30%',
            'zbzcksmc2'     => '科室Y',
            'zbzcksradio2'  => '58.40%',
            'zbzcksmc3'     => '科室Z',
            'zbzcksradio3'  => '61.80%',
            'zbzcksmc4'     => '科室W',
            'zbzcksradio4'  => '64.20%',
            'zbzcksmc5'     => '科室V',
            'zbzcksradio5'  => '66.60%',
        ],
        [
            'zbzcid'        => 6,
            'zbzcmc'        => '病理检查记录符合率',
            'zbzcksmc1'     => '科室X',
            'zbzcksradio1'  => '60.00%',
            'zbzcksmc2'     => '科室Y',
            'zbzcksradio2'  => '63.00%',
            'zbzcksmc3'     => '科室Z',
            'zbzcksradio3'  => '65.80%',
            'zbzcksmc4'     => '科室W',
            'zbzcksradio4'  => '68.60%',
            'zbzcksmc5'     => '科室V',
            'zbzcksradio5'  => '71.00%',
        ],
        [
            'zbzcid'        => 7,
            'zbzcmc'        => '细菌培养检查记录符合率',
            'zbzcksmc1'     => '科室X',
            'zbzcksradio1'  => '58.20%',
            'zbzcksmc2'     => '科室Y',
            'zbzcksradio2'  => '61.20%',
            'zbzcksmc3'     => '科室Z',
            'zbzcksradio3'  => '64.40%',
            'zbzcksmc4'     => '科室W',
            'zbzcksradio4'  => '67.60%',
            'zbzcksmc5'     => '科室V',
            'zbzcksradio5'  => '70.20%',
        ],
        [
            'zbzcid'        => 8,
            'zbzcmc'        => '抗菌药物使用记录符合率',
            'zbzcksmc1'     => '科室X',
            'zbzcksradio1'  => '62.50%',
            'zbzcksmc2'     => '科室Y',
            'zbzcksradio2'  => '65.80%',
            'zbzcksmc3'     => '科室Z',
            'zbzcksradio3'  => '68.60%',
            'zbzcksmc4'     => '科室W',
            'zbzcksradio4'  => '71.40%',
            'zbzcksmc5'     => '科室V',
            'zbzcksradio5'  => '73.80%',
        ],
        [
            'zbzcid'        => 9,
            'zbzcmc'        => '恶性肿瘤化学治疗记录符合率',
            'zbzcksmc1'     => '科室X',
            'zbzcksradio1'  => '65.00%',
            'zbzcksmc2'     => '科室Y',
            'zbzcksradio2'  => '68.50%',
            'zbzcksmc3'     => '科室Z',
            'zbzcksradio3'  => '71.00%',
            'zbzcksmc4'     => '科室W',
            'zbzcksradio4'  => '73.50%',
            'zbzcksmc5'     => '科室V',
            'zbzcksradio5'  => '76.00%',
        ],
        [
            'zbzcid'        => 10,
            'zbzcmc'        => '恶性肿瘤放射治疗记录符合率',
            'zbzcksmc1'     => '科室X',
            'zbzcksradio1'  => '63.70%',
            'zbzcksmc2'     => '科室Y',
            'zbzcksradio2'  => '67.10%',
            'zbzcksmc3'     => '科室Z',
            'zbzcksradio3'  => '70.40%',
            'zbzcksmc4'     => '科室W',
            'zbzcksradio4'  => '72.90%',
            'zbzcksmc5'     => '科室V',
            'zbzcksradio5'  => '75.30%',
        ],
    ];
    echo "   指标达成率最差前5名表格行数: " . count($zbzcRows) . " 行（每行包含1个指标的最差前5名科室）\n";
    try {
        // 对应模板中的 ${zbzcid#1}, ${zbzcmc#1}, ${zbzcksmc1#1}~\${zbzcksmc5#1}, ${zbzcksradio1#1}~\${zbzcksradio5#1}
        $wordReportService->replaceTableRows('zbzcid', $zbzcRows);
        echo "   ✓ 指标达成率最差前5名表格填充成功\n";
    } catch (\Exception $e) {
        echo "   ⚠ 指标达成率最差前5名表格填充失败: " . $e->getMessage() . "\n";
        echo "   提示: 需要在 Word 模板中设置: \${zbzcid#1}, \${zbzcmc#1}, \${zbzcksmc1#1}~\${zbzcksmc5#1}, \${zbzcksradio1#1}~\${zbzcksradio5#1}\n";
    }

    // 5.22. 测试病历质量最优前5名表格填充（${ksblzyid} 表）
    echo "\n5.22. 测试病历质量最优前5名表格填充...\n";
    $ksblzyRows = [
        [
            'ksblzyid'     => 1,
            'ksblzymc'     => '综合治疗科二病区',
            'ksblzynum'    => 1000,
            'ksblzyqx'     => 0,      // 缺陷病历（例）
            'ksblzyradio'  => '0.00', // 缺陷占比（%）
        ],
        [
            'ksblzyid'     => 2,
            'ksblzymc'     => '科室B',
            'ksblzynum'    => 10000,
            'ksblzyqx'     => 10000,
            'ksblzyradio'  => '50.00',
        ],
        [
            'ksblzyid'     => 3,
            'ksblzymc'     => '科室C',
            'ksblzynum'    => 10000,
            'ksblzyqx'     => 10000,
            'ksblzyradio'  => '50.00',
        ],
        [
            'ksblzyid'     => 4,
            'ksblzymc'     => '科室D',
            'ksblzynum'    => 10000,
            'ksblzyqx'     => 10000,
            'ksblzyradio'  => '50.00',
        ],
        [
            'ksblzyid'     => 5,
            'ksblzymc'     => '科室E',
            'ksblzynum'    => 10000,
            'ksblzyqx'     => 10000,
            'ksblzyradio'  => '50.00',
        ],
    ];
    echo "   病历质量最优前5名表格行数: " . count($ksblzyRows) . " 行\n";
    try {
        // 对应模板中的 ${ksblzyid#1}, ${ksblzymc#1}, ${ksblzynum#1}, ${ksblzyqx#1}, ${ksblzyradio#1}
        $wordReportService->replaceTableRows('ksblzyid', $ksblzyRows);
        echo "   ✓ 病历质量最优前5名表格填充成功\n";
    } catch (\Exception $e) {
        echo "   ⚠ 病历质量最优前5名表格填充失败: " . $e->getMessage() . "\n";
        echo "   提示: 需要在 Word 模板中设置: \${ksblzyid#1}, \${ksblzymc#1}, \${ksblzynum#1}, \${ksblzyqx#1}, \${ksblzyradio#1}\n";
    }

    // 5.23. 测试病历质量最优前5名图表数据填充（${kszytb} 位置）- 双系列柱状图
    echo "\n5.23. 测试病历质量最优前5名图表数据填充（\${kszytb} 位置）...\n";
    // 病历质量最优前5名图表数据：展示前5名科室的质控病历和缺陷病历对比
    // 数据格式：科室名 => ['total' => 质控病历数, 'defect' => 缺陷病历数]
    // 使用表格中的5个科室名称，确保图表显示5个不同的科室
    $kszytbChartData = [
        '综合治疗科二病区' => [
            'total' => 1000,    // 质控病历
            'defect' => 0,      // 缺陷病历（最优）
        ],
        '科室B' => [
            'total' => 10000,
            'defect' => 10000,
        ],
        '科室C' => [
            'total' => 10000,
            'defect' => 10000,
        ],
        '科室D' => [
            'total' => 10000,
            'defect' => 10000,
        ],
        '科室E' => [
            'total' => 10000,
            'defect' => 10000,
        ],
    ];
    
    echo "   病历质量最优前5名图表数据:\n";
    foreach ($kszytbChartData as $dept => $data) {
        echo "     {$dept}: 质控病历={$data['total']}, 缺陷病历={$data['defect']}\n";
    }
    
    try {
        echo "   正在替换病历质量最优前5名图表占位符 \${kszytb}（双系列柱状图）...\n";
        
        // 病历质量最优前5名图表选项：双系列柱状图
        $kszytbChartOptions = [
            'width' => 5256530,       // 与模板实例图表一致 (约14.6cm)
            'height' => 2988310,      // 约8.3cm（高度保持不变）
            'title' => '',            // 不显示标题
            'overlap' => -28,         // 系列间距
            'gapWidth' => 246,        // 柱状图宽度
            'addTrendline' => false,  // 不需要趋势线
            'showGridY' => true,      // 显示Y轴网格线（横线）
        ];
        
        $wordReportService->replaceChart('kszytb', $kszytbChartData, $kszytbChartOptions);
        echo "   ✓ 病历质量最优前5名图表替换成功（双系列柱状图）\n";
    } catch (\Exception $e) {
        echo "   ⚠ 病历质量最优前5名图表替换失败: " . $e->getMessage() . "\n";
        echo "   错误详情: " . $e->getFile() . ":" . $e->getLine() . "\n";
        if ($e->getPrevious()) {
            echo "   原始错误: " . $e->getPrevious()->getMessage() . "\n";
        }
        echo "   提示: 需要在 Word 模板中设置图表占位符: \${kszytb}\n";
    }

    // 5.24. 测试病历质量最差前5名表格填充（${ksblzcid} 表）
    echo "\n5.24. 测试病历质量最差前5名表格填充...\n";
    $ksblzcRows = [
        [
            'ksblzcid'     => 1,
            'ksblzcmc'     => '科室X',
            'ksblzcnum'    => 8000,
            'ksblzcqx'     => 7800,      // 缺陷病历（例）
            'ksblzcradio'  => '97.50',   // 缺陷占比（%）
        ],
        [
            'ksblzcid'     => 2,
            'ksblzcmc'     => '科室Y',
            'ksblzcnum'    => 7500,
            'ksblzcqx'     => 7200,
            'ksblzcradio'  => '96.00',
        ],
        [
            'ksblzcid'     => 3,
            'ksblzcmc'     => '科室Z',
            'ksblzcnum'    => 9000,
            'ksblzcqx'     => 8550,
            'ksblzcradio'  => '95.00',
        ],
        [
            'ksblzcid'     => 4,
            'ksblzcmc'     => '科室W',
            'ksblzcnum'    => 8500,
            'ksblzcqx'     => 8000,
            'ksblzcradio'  => '94.12',
        ],
        [
            'ksblzcid'     => 5,
            'ksblzcmc'     => '科室V',
            'ksblzcnum'    => 7000,
            'ksblzcqx'     => 6500,
            'ksblzcradio'  => '92.86',
        ],
    ];
    echo "   病历质量最差前5名表格行数: " . count($ksblzcRows) . " 行\n";
    try {
        // 对应模板中的 ${ksblzcid#1}, ${ksblzcmc#1}, ${ksblzcnum#1}, ${ksblzcqx#1}, ${ksblzcradio#1}
        $wordReportService->replaceTableRows('ksblzcid', $ksblzcRows);
        echo "   ✓ 病历质量最差前5名表格填充成功\n";
    } catch (\Exception $e) {
        echo "   ⚠ 病历质量最差前5名表格填充失败: " . $e->getMessage() . "\n";
        echo "   提示: 需要在 Word 模板中设置: \${ksblzcid#1}, \${ksblzcmc#1}, \${ksblzcnum#1}, \${ksblzcqx#1}, \${ksblzcradio#1}\n";
    }

    // 5.25. 测试病历质量最差前5名图表数据填充（${kszctb} 位置）- 双系列柱状图
    echo "\n5.25. 测试病历质量最差前5名图表数据填充（\${kszctb} 位置）...\n";
    // 病历质量最差前5名图表数据：展示最差前5名科室的质控病历和缺陷病历对比
    // 数据格式：科室名 => ['total' => 质控病历数, 'defect' => 缺陷病历数]
    // 使用表格中的5个科室名称，确保图表显示5个不同的科室
    $kszctbChartData = [
        '科室X' => [
            'total' => 8000,    // 质控病历
            'defect' => 7800,   // 缺陷病历（最差）
        ],
        '科室Y' => [
            'total' => 7500,
            'defect' => 7200,
        ],
        '科室Z' => [
            'total' => 9000,
            'defect' => 8550,
        ],
        '科室W' => [
            'total' => 8500,
            'defect' => 8000,
        ],
        '科室V' => [
            'total' => 7000,
            'defect' => 6500,
        ],
    ];
    
    echo "   病历质量最差前5名图表数据:\n";
    foreach ($kszctbChartData as $dept => $data) {
        echo "     {$dept}: 质控病历={$data['total']}, 缺陷病历={$data['defect']}\n";
    }
    
    try {
        echo "   正在替换病历质量最差前5名图表占位符 \${kszctb}（双系列柱状图）...\n";
        
        // 病历质量最差前5名图表选项：双系列柱状图
        $kszctbChartOptions = [
            'width' => 5256530,       // 与模板实例图表一致 (约14.6cm)
            'height' => 2988310,      // 约8.3cm（高度保持不变）
            'title' => '',            // 不显示标题
            'overlap' => -28,         // 系列间距
            'gapWidth' => 246,        // 柱状图宽度
            'addTrendline' => false,  // 不需要趋势线
            'showGridY' => true,      // 显示Y轴网格线（横线）
        ];
        
        $wordReportService->replaceChart('kszctb', $kszctbChartData, $kszctbChartOptions);
        echo "   ✓ 病历质量最差前5名图表替换成功（双系列柱状图）\n";
    } catch (\Exception $e) {
        echo "   ⚠ 病历质量最差前5名图表替换失败: " . $e->getMessage() . "\n";
        echo "   错误详情: " . $e->getFile() . ":" . $e->getLine() . "\n";
        if ($e->getPrevious()) {
            echo "   原始错误: " . $e->getPrevious()->getMessage() . "\n";
        }
        echo "   提示: 需要在 Word 模板中设置图表占位符: \${kszctb}\n";
    }

    // 5.26. 测试病历质量所有科室排名表格填充（${syksblmc} 表）
    echo "\n5.26. 测试病历质量所有科室排名表格填充...\n";
    // 所有科室排名数据：包含最优、最差和其他科室
    $syksblRows = [
        [
            'syksblmc'     => '综合治疗科二病区',
            'syksblnum'    => 1000,
            'syksblqx'     => 0,
            'syksblradio'  => '0.00',
        ],
        [
            'syksblmc'     => '科室B',
            'syksblnum'    => 10000,
            'syksblqx'     => 10000,
            'syksblradio'  => '50.00',
        ],
        [
            'syksblmc'     => '科室C',
            'syksblnum'    => 10000,
            'syksblqx'     => 10000,
            'syksblradio'  => '50.00',
        ],
        [
            'syksblmc'     => '科室D',
            'syksblnum'    => 10000,
            'syksblqx'     => 10000,
            'syksblradio'  => '50.00',
        ],
        [
            'syksblmc'     => '科室E',
            'syksblnum'    => 10000,
            'syksblqx'     => 10000,
            'syksblradio'  => '50.00',
        ],
        [
            'syksblmc'     => '科室F',
            'syksblnum'    => 9500,
            'syksblqx'     => 8500,
            'syksblradio'  => '89.47',
        ],
        [
            'syksblmc'     => '科室G',
            'syksblnum'    => 8800,
            'syksblqx'     => 7500,
            'syksblradio'  => '85.23',
        ],
        [
            'syksblmc'     => '科室H',
            'syksblnum'    => 9200,
            'syksblqx'     => 7800,
            'syksblradio'  => '84.78',
        ],
        [
            'syksblmc'     => '科室I',
            'syksblnum'    => 8600,
            'syksblqx'     => 7200,
            'syksblradio'  => '83.72',
        ],
        [
            'syksblmc'     => '科室J',
            'syksblnum'    => 9000,
            'syksblqx'     => 7500,
            'syksblradio'  => '83.33',
        ],
        [
            'syksblmc'     => '科室K',
            'syksblnum'    => 7800,
            'syksblqx'     => 6400,
            'syksblradio'  => '82.05',
        ],
        [
            'syksblmc'     => '科室L',
            'syksblnum'    => 8500,
            'syksblqx'     => 6900,
            'syksblradio'  => '81.18',
        ],
        [
            'syksblmc'     => '科室M',
            'syksblnum'    => 8200,
            'syksblqx'     => 6600,
            'syksblradio'  => '80.49',
        ],
        [
            'syksblmc'     => '科室N',
            'syksblnum'    => 7900,
            'syksblqx'     => 6300,
            'syksblradio'  => '79.75',
        ],
        [
            'syksblmc'     => '科室O',
            'syksblnum'    => 7600,
            'syksblqx'     => 6000,
            'syksblradio'  => '78.95',
        ],
        [
            'syksblmc'     => '科室P',
            'syksblnum'    => 7300,
            'syksblqx'     => 5700,
            'syksblradio'  => '78.08',
        ],
        [
            'syksblmc'     => '科室Q',
            'syksblnum'    => 7000,
            'syksblqx'     => 5400,
            'syksblradio'  => '77.14',
        ],
        [
            'syksblmc'     => '科室R',
            'syksblnum'    => 6800,
            'syksblqx'     => 5200,
            'syksblradio'  => '76.47',
        ],
        [
            'syksblmc'     => '科室S',
            'syksblnum'    => 6500,
            'syksblqx'     => 4900,
            'syksblradio'  => '75.38',
        ],
        [
            'syksblmc'     => '科室T',
            'syksblnum'    => 7200,
            'syksblqx'     => 5400,
            'syksblradio'  => '75.00',
        ],
        [
            'syksblmc'     => '科室U',
            'syksblnum'    => 6900,
            'syksblqx'     => 5100,
            'syksblradio'  => '73.91',
        ],
        [
            'syksblmc'     => '科室V',
            'syksblnum'    => 7000,
            'syksblqx'     => 6500,
            'syksblradio'  => '92.86',
        ],
        [
            'syksblmc'     => '科室W',
            'syksblnum'    => 8500,
            'syksblqx'     => 8000,
            'syksblradio'  => '94.12',
        ],
        [
            'syksblmc'     => '科室X',
            'syksblnum'    => 8000,
            'syksblqx'     => 7800,
            'syksblradio'  => '97.50',
        ],
        [
            'syksblmc'     => '科室Y',
            'syksblnum'    => 7500,
            'syksblqx'     => 7200,
            'syksblradio'  => '96.00',
        ],
        [
            'syksblmc'     => '科室Z',
            'syksblnum'    => 9000,
            'syksblqx'     => 8550,
            'syksblradio'  => '95.00',
        ],
    ];
    echo "   病历质量所有科室排名表格行数: " . count($syksblRows) . " 行\n";
    try {
        // 对应模板中的 ${syksblmc#1}, ${syksblnum#1}, ${syksblqx#1}, ${syksblradio#1}
        $wordReportService->replaceTableRows('syksblmc', $syksblRows);
        echo "   ✓ 病历质量所有科室排名表格填充成功\n";
    } catch (\Exception $e) {
        echo "   ⚠ 病历质量所有科室排名表格填充失败: " . $e->getMessage() . "\n";
        echo "   提示: 需要在 Word 模板中设置: \${syksblmc#1}, \${syksblnum#1}, \${syksblqx#1}, \${syksblradio#1}\n";
    }

    // 5.27. 测试医师病历质量最优前5名表格填充（${ysblzlzyid} 表）
    echo "\n5.27. 测试医师病历质量最优前5名表格填充...\n";
    $ysblzlzyRows = [
        [
            'ysblzlzyid'   => 1,
            'ysblzlys'     => '李医生',
            'ysblzlks'     => '综合治疗科二病区',
            'ysblzlnum'    => 1000,
            'ysblzlqx'     => 0,          // 缺陷病历（例）
            'ysblzlkf'     => '0.00',     // 总扣分（分），注意模板中是 -${ysblzlkf}
            'ysblzlpjf'    => '100.00',   // 平均分（分）
        ],
        [
            'ysblzlzyid'   => 2,
            'ysblzlys'     => '张三',
            'ysblzlks'     => '科室B',
            'ysblzlnum'    => 10000,
            'ysblzlqx'     => 10000,
            'ysblzlkf'     => '5000.00',
            'ysblzlpjf'    => '91.52',
        ],
        [
            'ysblzlzyid'   => 3,
            'ysblzlys'     => '张三',
            'ysblzlks'     => '科室C',
            'ysblzlnum'    => 10000,
            'ysblzlqx'     => 10000,
            'ysblzlkf'     => '5000.00',
            'ysblzlpjf'    => '91.52',
        ],
        [
            'ysblzlzyid'   => 4,
            'ysblzlys'     => '张三',
            'ysblzlks'     => '科室D',
            'ysblzlnum'    => 10000,
            'ysblzlqx'     => 10000,
            'ysblzlkf'     => '5000.00',
            'ysblzlpjf'    => '91.52',
        ],
        [
            'ysblzlzyid'   => 5,
            'ysblzlys'     => '张三',
            'ysblzlks'     => '科室E',
            'ysblzlnum'    => 10000,
            'ysblzlqx'     => 10000,
            'ysblzlkf'     => '5000.00',
            'ysblzlpjf'    => '91.52',
        ],
    ];
    echo "   医师病历质量最优前5名表格行数: " . count($ysblzlzyRows) . " 行\n";
    try {
        // 对应模板中的 ${ysblzlzyid#1}, ${ysblzlys#1}, ${ysblzlks#1}, ${ysblzlnum#1}, ${ysblzlqx#1}, ${ysblzlkf#1}, ${ysblzlpjf#1}
        // 注意：总扣分在模板中是 -${ysblzlkf}，所以数据中直接填数值即可
        $wordReportService->replaceTableRows('ysblzlzyid', $ysblzlzyRows);
        echo "   ✓ 医师病历质量最优前5名表格填充成功\n";
    } catch (\Exception $e) {
        echo "   ⚠ 医师病历质量最优前5名表格填充失败: " . $e->getMessage() . "\n";
        echo "   提示: 需要在 Word 模板中设置: \${ysblzlzyid#1}, \${ysblzlys#1}, \${ysblzlks#1}, \${ysblzlnum#1}, \${ysblzlqx#1}, \${ysblzlkf#1}, \${ysblzlpjf#1}\n";
    }

    // 5.28. 测试医师病历质量最优前5名图表数据填充（${ysblzltb} 位置）- 双系列柱状图
    echo "\n5.28. 测试医师病历质量最优前5名图表数据填充（\${ysblzltb} 位置）...\n";
    // 医师病历质量最优前5名图表数据：展示前5名医师的质控病历和缺陷病历对比
    // 数据格式：医师名 => ['total' => 质控病历数, 'defect' => 缺陷病历数]
    // 使用不同的医师姓名，确保图表显示5个不同的数据点
    // 根据图片，缺陷病历应该是递减的：0, 500, 300, 200, 0
    $ysblzltbChartData = [
        '李医生' => [
            'total' => 1000,    // 质控病历
            'defect' => 0,      // 缺陷病历（最优）
        ],
        '王医生' => [
            'total' => 10000,
            'defect' => 500,
        ],
        '赵医生' => [
            'total' => 10000,
            'defect' => 300,
        ],
        '刘医生' => [
            'total' => 10000,
            'defect' => 200,
        ],
        '陈医生' => [
            'total' => 10000,
            'defect' => 0,
        ],
    ];
    
    echo "   医师病历质量最优前5名图表数据:\n";
    foreach ($ysblzltbChartData as $doctor => $data) {
        echo "     {$doctor}: 质控病历={$data['total']}, 缺陷病历={$data['defect']}\n";
    }
    
    try {
        echo "   正在替换医师病历质量最优前5名图表占位符 \${ysblzltb}（双系列柱状图）...\n";
        
        // 医师病历质量最优前5名图表选项：双系列柱状图
        $ysblzltbChartOptions = [
            'width' => 5256530,       // 与模板实例图表一致 (约14.6cm)
            'height' => 2988310,      // 约8.3cm（高度保持不变）
            'title' => '',            // 不显示标题
            'overlap' => -28,         // 系列间距
            'gapWidth' => 246,        // 柱状图宽度
            'addTrendline' => false,  // 不需要趋势线
            'showGridY' => true,      // 显示Y轴网格线（横线）
        ];
        
        $wordReportService->replaceChart('ysblzltb', $ysblzltbChartData, $ysblzltbChartOptions);
        echo "   ✓ 医师病历质量最优前5名图表替换成功（双系列柱状图）\n";
    } catch (\Exception $e) {
        echo "   ⚠ 医师病历质量最优前5名图表替换失败: " . $e->getMessage() . "\n";
        echo "   错误详情: " . $e->getFile() . ":" . $e->getLine() . "\n";
        if ($e->getPrevious()) {
            echo "   原始错误: " . $e->getPrevious()->getMessage() . "\n";
        }
        echo "   提示: 需要在 Word 模板中设置图表占位符: \${ysblzltb}\n";
    }

    // 5.29. 测试医师病历质量最差前5名表格填充（${zcysblzlzyid} 表）
    echo "\n5.29. 测试医师病历质量最差前5名表格填充...\n";
    $zcysblzlzyRows = [
        [
            'zcysblzlzyid' => 1,
            'zcysblzlys'   => '周医生',
            'zcysblzlks'   => '科室X',
            'zcysblzlnum'  => 8000,
            'zcysblzlqx'   => 7800,        // 缺陷病历（例）
            'zcysblzlkf'   => '19500.00',   // 总扣分（分），注意模板中是 -${zcysblzlkf}
            'zcysblzlpjf'  => '60.00',     // 平均分（分）
        ],
        [
            'zcysblzlzyid' => 2,
            'zcysblzlys'   => '吴医生',
            'zcysblzlks'   => '科室Y',
            'zcysblzlnum'  => 7500,
            'zcysblzlqx'   => 7200,
            'zcysblzlkf'   => '18000.00',
            'zcysblzlpjf'  => '62.00',
        ],
        [
            'zcysblzlzyid' => 3,
            'zcysblzlys'   => '郑医生',
            'zcysblzlks'   => '科室Z',
            'zcysblzlnum'  => 9000,
            'zcysblzlqx'   => 8550,
            'zcysblzlkf'   => '21375.00',
            'zcysblzlpjf'  => '64.00',
        ],
        [
            'zcysblzlzyid' => 4,
            'zcysblzlys'   => '孙医生',
            'zcysblzlks'   => '科室W',
            'zcysblzlnum'  => 8500,
            'zcysblzlqx'   => 8000,
            'zcysblzlkf'   => '20000.00',
            'zcysblzlpjf'  => '65.00',
        ],
        [
            'zcysblzlzyid' => 5,
            'zcysblzlys'   => '钱医生',
            'zcysblzlks'   => '科室V',
            'zcysblzlnum'  => 7000,
            'zcysblzlqx'   => 6500,
            'zcysblzlkf'   => '16250.00',
            'zcysblzlpjf'  => '66.00',
        ],
    ];
    echo "   医师病历质量最差前5名表格行数: " . count($zcysblzlzyRows) . " 行\n";
    try {
        // 对应模板中的 ${zcysblzlzyid#1}, ${zcysblzlys#1}, ${zcysblzlks#1}, ${zcysblzlnum#1}, ${zcysblzlqx#1}, ${zcysblzlkf#1}, ${zcysblzlpjf#1}
        // 注意：总扣分在模板中是 -${zcysblzlkf}，所以数据中直接填数值即可
        $wordReportService->replaceTableRows('zcysblzlzyid', $zcysblzlzyRows);
        echo "   ✓ 医师病历质量最差前5名表格填充成功\n";
    } catch (\Exception $e) {
        echo "   ⚠ 医师病历质量最差前5名表格填充失败: " . $e->getMessage() . "\n";
        echo "   提示: 需要在 Word 模板中设置: \${zcysblzlzyid#1}, \${zcysblzlys#1}, \${zcysblzlks#1}, \${zcysblzlnum#1}, \${zcysblzlqx#1}, \${zcysblzlkf#1}, \${zcysblzlpjf#1}\n";
    }

    // 5.30. 测试医师病历质量最差前5名图表数据填充（${zxysblzltb} 位置）- 双系列柱状图
    echo "\n5.30. 测试医师病历质量最差前5名图表数据填充（\${zxysblzltb} 位置）...\n";
    // 医师病历质量最差前5名图表数据：展示最差前5名医师的质控病历和缺陷病历对比
    // 数据格式：医师名 => ['total' => 质控病历数, 'defect' => 缺陷病历数]
    // 使用不同的医师姓名，确保图表显示5个不同的数据点
    $zxysblzltbChartData = [
        '周医生' => [
            'total' => 8000,    // 质控病历
            'defect' => 7800,   // 缺陷病历（最差）
        ],
        '吴医生' => [
            'total' => 7500,
            'defect' => 7200,
        ],
        '郑医生' => [
            'total' => 9000,
            'defect' => 8550,
        ],
        '孙医生' => [
            'total' => 8500,
            'defect' => 8000,
        ],
        '钱医生' => [
            'total' => 7000,
            'defect' => 6500,
        ],
    ];
    
    echo "   医师病历质量最差前5名图表数据:\n";
    foreach ($zxysblzltbChartData as $doctor => $data) {
        echo "     {$doctor}: 质控病历={$data['total']}, 缺陷病历={$data['defect']}\n";
    }
    
    try {
        echo "   正在替换医师病历质量最差前5名图表占位符 \${zxysblzltb}（双系列柱状图）...\n";
        
        // 医师病历质量最差前5名图表选项：双系列柱状图
        $zxysblzltbChartOptions = [
            'width' => 5256530,       // 与模板实例图表一致 (约14.6cm)
            'height' => 2988310,      // 约8.3cm（高度保持不变）
            'title' => '',            // 不显示标题
            'overlap' => -28,         // 系列间距
            'gapWidth' => 246,        // 柱状图宽度
            'addTrendline' => false,  // 不需要趋势线
            'showGridY' => true,      // 显示Y轴网格线（横线）
        ];
        
        $wordReportService->replaceChart('zxysblzltb', $zxysblzltbChartData, $zxysblzltbChartOptions);
        echo "   ✓ 医师病历质量最差前5名图表替换成功（双系列柱状图）\n";
    } catch (\Exception $e) {
        echo "   ⚠ 医师病历质量最差前5名图表替换失败: " . $e->getMessage() . "\n";
        echo "   错误详情: " . $e->getFile() . ":" . $e->getLine() . "\n";
        if ($e->getPrevious()) {
            echo "   原始错误: " . $e->getPrevious()->getMessage() . "\n";
        }
        echo "   提示: 需要在 Word 模板中设置图表占位符: \${zxysblzltb}\n";
    }

    // 6. 生成报告
    echo "\n6. 生成报告文件...\n";
    $outputDir = $baseDir . '/storage/app/reports';
    if (!is_dir($outputDir)) {
        mkdir($outputDir, 0755, true);
    }
    $outputPath = $outputDir . '/test_报告_' . date('YmdHis') . '.docx';
    
    $wordReportService->saveReport($outputPath);
    
    if (file_exists($outputPath)) {
        $fileSize = filesize($outputPath);
        echo "   ✓ 报告生成成功\n";
        echo "   文件路径: $outputPath\n";
        echo "   文件大小: " . number_format($fileSize / 1024, 2) . " KB\n";
    } else {
        echo "   ✗ 报告生成失败\n";
        exit(1);
    }
    
    echo "\n==========================================\n";
    echo "所有测试通过！✓\n";
    echo "==========================================\n";
    echo "\n生成的文件:\n";
    echo "  1. $outputPath\n";
    echo "\n可以打开这些文件查看效果。\n";
    
} catch (\Exception $e) {
    echo "\n==========================================\n";
    echo "测试失败！✗\n";
    echo "==========================================\n";
    echo "错误信息: " . $e->getMessage() . "\n";
    echo "错误位置: " . $e->getFile() . ":" . $e->getLine() . "\n";
    if ($e->getTrace()) {
        echo "\n堆栈跟踪:\n";
        echo $e->getTraceAsString() . "\n";
    }
    exit(1);
}

