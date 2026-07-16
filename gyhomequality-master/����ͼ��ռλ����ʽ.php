<?php

/**
 * 测试图表占位符格式
 */

require __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpWord\TemplateProcessor;
use PhpOffice\PhpWord\Element\Chart as ChartElement;
use PhpOffice\PhpWord\Style\Chart as ChartStyle;

$baseDir = __DIR__;
$templatePath = $baseDir . '/病历质控分析报告.docx';

if (!file_exists($templatePath)) {
    echo "模板文件不存在: $templatePath\n";
    exit(1);
}

echo "==========================================\n";
echo "测试图表占位符格式\n";
echo "==========================================\n\n";

try {
    $templateProcessor = new TemplateProcessor($templatePath);
    
    // 读取模板的 XML 内容
    $zip = new ZipArchive();
    if ($zip->open($templatePath) === true) {
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        
        // 查找 chartImage 占位符
        echo "1. 查找 chartImage 占位符...\n";
        $placeholder = '${chartImage}';
        $pos = strpos($xml, $placeholder);
        
        if ($pos === false) {
            echo "   ⚠ 未找到占位符: $placeholder\n";
            echo "   提示: 占位符必须在模板中独立存在\n";
        } else {
            echo "   ✓ 找到占位符: $placeholder\n";
            echo "   位置: $pos\n";
            
            // 检查占位符周围的 XML 结构
            $context = substr($xml, max(0, $pos - 200), 400);
            echo "\n2. 占位符周围的 XML 结构:\n";
            echo htmlspecialchars($context) . "\n";
            
            // 检查是否在独立的段落中
            $beforePlaceholder = substr($xml, max(0, $pos - 500), 500);
            $afterPlaceholder = substr($xml, $pos, 500);
            
            $hasParagraphBefore = strpos($beforePlaceholder, '<w:p>') !== false || strpos($beforePlaceholder, '</w:p>') !== false;
            $hasParagraphAfter = strpos($afterPlaceholder, '</w:p>') !== false;
            
            echo "\n3. 段落结构检查:\n";
            echo "   占位符前有段落标记: " . ($hasParagraphBefore ? '是' : '否') . "\n";
            echo "   占位符后有段落标记: " . ($hasParagraphAfter ? '是' : '否') . "\n";
            
            if (!$hasParagraphBefore || !$hasParagraphAfter) {
                echo "\n   ⚠ 警告: 占位符可能不在独立的段落中\n";
                echo "   建议: 在 Word 模板中，将 ${chartImage} 放在一个独立的段落中\n";
            }
        }
        
        // 测试设置图表
        echo "\n4. 测试设置图表（超大尺寸）...\n";
        $chartStyle = new ChartStyle();
        $chartStyle->setWidth(18000000);  // 约 50cm
        $chartStyle->setHeight(10800000);  // 约 30cm
        $chartStyle->setTitle('测试图表');
        $chartStyle->setShowLegend(true);
        
        $chart = new ChartElement('column', ['测试1', '测试2'], [100, 200], $chartStyle, '测试系列');
        
        try {
            $templateProcessor->setChart('chartImage', $chart);
            echo "   ✓ 图表设置成功\n";
            echo "   设置的尺寸: 宽度=" . round($chartStyle->getWidth() / 360000, 2) . "cm, 高度=" . round($chartStyle->getHeight() / 360000, 2) . "cm\n";
            
            // 保存测试文件
            $testPath = $baseDir . '/storage/app/reports/测试图表格式_' . date('YmdHis') . '.docx';
            $templateProcessor->saveAs($testPath);
            echo "   测试文件已保存: $testPath\n";
        } catch (\Exception $e) {
            echo "   ✗ 图表设置失败: " . $e->getMessage() . "\n";
        }
        
    } else {
        echo "无法打开模板文件\n";
        exit(1);
    }
    
} catch (\Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
    echo "位置: " . $e->getFile() . ":" . $e->getLine() . "\n";
    exit(1);
}

echo "\n==========================================\n";
echo "测试完成\n";
echo "==========================================\n";

