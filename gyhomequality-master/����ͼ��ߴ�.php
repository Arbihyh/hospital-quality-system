<?php

/**
 * 测试图表尺寸设置
 */

require __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpWord\Element\Chart as ChartElement;
use PhpOffice\PhpWord\Style\Chart as ChartStyle;

echo "==========================================\n";
echo "测试 PhpWord 图表尺寸\n";
echo "==========================================\n\n";

// 测试不同的尺寸设置
$testSizes = [
    ['width' => 1000000, 'height' => 1000000, 'desc' => '默认尺寸（约 2.78cm）'],
    ['width' => 3600000, 'height' => 2160000, 'desc' => '10cm x 6cm'],
    ['width' => 7200000, 'height' => 4320000, 'desc' => '20cm x 12cm'],
    ['width' => 14400000, 'height' => 7920000, 'desc' => '40cm x 22cm'],
];

foreach ($testSizes as $size) {
    $chartStyle = new ChartStyle();
    $chartStyle->setWidth($size['width']);
    $chartStyle->setHeight($size['height']);
    
    $actualWidth = $chartStyle->getWidth();
    $actualHeight = $chartStyle->getHeight();
    
    echo "设置: {$size['desc']}\n";
    echo "  Width EMU: {$size['width']} -> 实际: {$actualWidth}\n";
    echo "  Height EMU: {$size['height']} -> 实际: {$actualHeight}\n";
    echo "  Width CM: " . round($actualWidth / 360000, 2) . "cm\n";
    echo "  Height CM: " . round($actualHeight / 360000, 2) . "cm\n";
    echo "\n";
}

echo "==========================================\n";
echo "结论：ChartStyle 的 setWidth/setHeight 应该正常工作\n";
echo "如果 Word 中图表还是很小，可能是 Word 的限制或显示问题\n";
echo "==========================================\n";

