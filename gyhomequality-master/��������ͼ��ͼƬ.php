<?php

/**
 * 创建一个简单的测试图表图片
 * 使用 GD 库生成一个简单的柱状图
 */

$width = 800;
$height = 500;
$image = imagecreatetruecolor($width, $height);

// 设置颜色
$white = imagecolorallocate($image, 255, 255, 255);
$black = imagecolorallocate($image, 0, 0, 0);
$blue = imagecolorallocate($image, 79, 129, 189);
$orange = imagecolorallocate($image, 255, 192, 0);
$gray = imagecolorallocate($image, 200, 200, 200);

// 填充白色背景
imagefill($image, 0, 0, $white);

// 绘制标题
$title = "病案内涵质控趋势图";
imagestring($image, 5, $width / 2 - 100, 20, $title, $black);

// 绘制坐标轴
$marginLeft = 80;
$marginBottom = 60;
$chartWidth = $width - $marginLeft - 40;
$chartHeight = $height - $marginBottom - 80;

// X 轴
imageline($image, $marginLeft, $height - $marginBottom, $width - 40, $height - $marginBottom, $black);
// Y 轴
imageline($image, $marginLeft, 80, $marginLeft, $height - $marginBottom, $black);

// 绘制数据
$data = [
    '2025年11月' => ['total' => 123456, 'defect' => 12345],
    '2025年12月' => ['total' => 123456, 'defect' => 22345],
];

$maxValue = max(123456, 12345, 22345) * 1.1; // 最大值加10%留白
$barWidth = ($chartWidth / count($data)) * 0.3;
$barSpacing = ($chartWidth / count($data)) * 0.7;

$x = $marginLeft + $barSpacing / 2;
$index = 0;

foreach ($data as $month => $values) {
    // 绘制月份标签
    imagestring($image, 3, $x - 30, $height - $marginBottom + 10, $month, $black);
    
    // 绘制病历总数柱状图（蓝色）
    $barHeight1 = ($values['total'] / $maxValue) * $chartHeight;
    imagefilledrectangle($image, $x, $height - $marginBottom - $barHeight1, $x + $barWidth, $height - $marginBottom, $blue);
    
    // 绘制缺陷病历柱状图（橙色）
    $barHeight2 = ($values['defect'] / $maxValue) * $chartHeight;
    imagefilledrectangle($image, $x + $barWidth + 5, $height - $marginBottom - $barHeight2, $x + $barWidth * 2 + 5, $height - $marginBottom, $orange);
    
    // 绘制数值标签
    imagestring($image, 2, $x - 20, $height - $marginBottom - $barHeight1 - 20, number_format($values['total']), $black);
    imagestring($image, 2, $x + $barWidth + 5, $height - $marginBottom - $barHeight2 - 20, number_format($values['defect']), $black);
    
    $x += $barSpacing + $barWidth * 2 + 5;
    $index++;
}

// 绘制图例
$legendY = 60;
imagestring($image, 3, $width - 200, $legendY, "图例:", $black);
imagefilledrectangle($image, $width - 180, $legendY, $width - 160, $legendY + 15, $blue);
imagestring($image, 3, $width - 155, $legendY, "病历总数", $black);
imagefilledrectangle($image, $width - 180, $legendY + 20, $width - 160, $legendY + 35, $orange);
imagestring($image, 3, $width - 155, $legendY + 20, "缺陷病历", $black);

// 保存图片
$outputPath = __DIR__ . '/storage/app/charts/病案内涵质控趋势图.png';
imagepng($image, $outputPath);
imagedestroy($image);

echo "测试图表图片已创建: $outputPath\n";
echo "图片尺寸: {$width} x {$height} 像素\n";

