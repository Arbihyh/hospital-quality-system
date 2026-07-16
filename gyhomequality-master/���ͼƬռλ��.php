<?php

/**
 * 检查 Word 模板中的图片占位符
 */

require __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpWord\TemplateProcessor;

$baseDir = __DIR__;
$templatePath = $baseDir . '/病历质控分析报告.docx';

if (!file_exists($templatePath)) {
    echo "模板文件不存在: $templatePath\n";
    exit(1);
}

echo "==========================================\n";
echo "检查图片占位符\n";
echo "==========================================\n\n";

try {
    $templateProcessor = new TemplateProcessor($templatePath);
    
    // 读取模板的 XML 内容
    $zip = new ZipArchive();
    if ($zip->open($templatePath) === true) {
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        
        if ($xml === false) {
            echo "无法读取模板内容\n";
            exit(1);
        }
        
        // 查找所有变量（包括图片占位符）
        echo "1. 查找所有占位符变量...\n";
        preg_match_all('/\$\{([^}]+)\}/', $xml, $matches);
        $allVariables = array_unique($matches[1]);
        
        echo "   找到 " . count($allVariables) . " 个变量:\n";
        foreach ($allVariables as $var) {
            echo "     - \${$var}\n";
        }
        
        // 查找图片相关的占位符
        echo "\n2. 查找图片占位符（包含 'chart' 或 'image'）...\n";
        $imageVars = array_filter($allVariables, function($var) {
            return stripos($var, 'chart') !== false || stripos($var, 'image') !== false;
        });
        
        if (empty($imageVars)) {
            echo "   ⚠ 未找到图片占位符\n";
            echo "   提示: 在 Word 模板中插入文本占位符: \${chartImage}\n";
        } else {
            echo "   找到 " . count($imageVars) . " 个图片占位符:\n";
            foreach ($imageVars as $var) {
                echo "     - \${$var}\n";
            }
        }
        
        // 检查占位符的 XML 结构
        echo "\n3. 检查占位符的 XML 结构...\n";
        foreach ($imageVars as $var) {
            $placeholder = '${' . $var . '}';
            $pattern = '/' . preg_quote($placeholder, '/') . '/';
            if (preg_match($pattern, $xml, $matches, PREG_OFFSET_CAPTURE)) {
                $pos = $matches[0][1];
                $context = substr($xml, max(0, $pos - 100), 200);
                echo "   占位符 \${$var} 的上下文:\n";
                echo "   " . htmlspecialchars($context) . "\n\n";
            }
        }
        
        // 测试图片文件是否存在
        echo "4. 检查测试图片文件...\n";
        $testImagePath = $baseDir . '/storage/app/charts/病案内涵质控趋势图.png';
        if (file_exists($testImagePath)) {
            echo "   ✓ 图片文件存在: $testImagePath\n";
            $imageInfo = getimagesize($testImagePath);
            if ($imageInfo) {
                echo "   图片尺寸: {$imageInfo[0]} x {$imageInfo[1]} 像素\n";
                echo "   图片类型: " . image_type_to_mime_type($imageInfo[2]) . "\n";
            }
        } else {
            echo "   ⚠ 图片文件不存在: $testImagePath\n";
            echo "   提示: 请创建图表图片并保存到此路径\n";
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
echo "检查完成\n";
echo "==========================================\n";

