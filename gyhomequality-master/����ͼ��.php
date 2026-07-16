<?php

/**
 * 生成病案内涵质控趋势图（柱状图+折线图）
 * 使用 SVG 生成图表，然后转换为 PNG
 */

function generateChart($data, $outputPath) {
    $months = array_keys($data);
    $totals = array_column($data, 'total');
    $defects = array_column($data, 'defect');
    
    $width = 800;
    $height = 500;
    $marginLeft = 80;
    $marginBottom = 60;
    $marginTop = 60;
    $marginRight = 100;
    $chartWidth = $width - $marginLeft - $marginRight;
    $chartHeight = $height - $marginTop - $marginBottom;
    
    $maxValue = max(max($totals), max($defects)) * 1.1;
    
    // 创建 SVG
    $svg = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $svg .= '<svg width="' . $width . '" height="' . $height . '" xmlns="http://www.w3.org/2000/svg">' . "\n";
    
    // 背景
    $svg .= '<rect width="' . $width . '" height="' . $height . '" fill="#FFFFFF"/>' . "\n";
    
    // 标题
    $svg .= '<text x="' . ($width / 2) . '" y="30" text-anchor="middle" font-size="16" font-weight="bold" fill="#000000">病案内涵质控趋势图</text>' . "\n";
    
    // 坐标轴
    $svg .= '<line x1="' . $marginLeft . '" y1="' . $marginTop . '" x2="' . $marginLeft . '" y2="' . ($height - $marginBottom) . '" stroke="#000000" stroke-width="2"/>' . "\n";
    $svg .= '<line x1="' . $marginLeft . '" y1="' . ($height - $marginBottom) . '" x2="' . ($width - $marginRight) . '" y2="' . ($height - $marginBottom) . '" stroke="#000000" stroke-width="2"/>' . "\n";
    
    // 绘制柱状图和折线图
    $barWidth = ($chartWidth / count($months)) * 0.3;
    $barSpacing = ($chartWidth / count($months)) * 0.7;
    $x = $marginLeft + $barSpacing / 2;
    
    $linePoints = [];
    
    foreach ($months as $index => $month) {
        $total = $totals[$index];
        $defect = $defects[$index];
        
        // 计算柱状图高度
        $barHeight1 = ($total / $maxValue) * $chartHeight;
        $barHeight2 = ($defect / $maxValue) * $chartHeight;
        
        // 绘制病历总数柱状图（蓝色）
        $svg .= '<rect x="' . $x . '" y="' . ($height - $marginBottom - $barHeight1) . '" width="' . $barWidth . '" height="' . $barHeight1 . '" fill="#4F81BD" opacity="0.8"/>' . "\n";
        
        // 绘制缺陷病历柱状图（橙色）
        $svg .= '<rect x="' . ($x + $barWidth + 5) . '" y="' . ($height - $marginBottom - $barHeight2) . '" width="' . $barWidth . '" height="' . $barHeight2 . '" fill="#FFC000" opacity="0.8"/>' . "\n";
        
        // 数值标签
        $svg .= '<text x="' . ($x + $barWidth / 2) . '" y="' . ($height - $marginBottom - $barHeight1 - 5) . '" text-anchor="middle" font-size="10" fill="#000000">' . number_format($total) . '</text>' . "\n";
        $svg .= '<text x="' . ($x + $barWidth * 1.5 + 5) . '" y="' . ($height - $marginBottom - $barHeight2 - 5) . '" text-anchor="middle" font-size="10" fill="#000000">' . number_format($defect) . '</text>' . "\n";
        
        // 月份标签
        $svg .= '<text x="' . ($x + $barWidth + 2.5) . '" y="' . ($height - $marginBottom + 20) . '" text-anchor="middle" font-size="11" fill="#000000">' . htmlspecialchars($month) . '</text>' . "\n";
        
        // 折线图点
        $linePoints[] = [
            'x' => $x + $barWidth * 1.5 + 5,
            'y' => $height - $marginBottom - $barHeight2
        ];
        
        $x += $barSpacing + $barWidth * 2 + 5;
    }
    
    // 绘制折线图（缺陷病历趋势）
    if (count($linePoints) > 1) {
        $path = 'M ' . $linePoints[0]['x'] . ' ' . $linePoints[0]['y'];
        for ($i = 1; $i < count($linePoints); $i++) {
            $path .= ' L ' . $linePoints[$i]['x'] . ' ' . $linePoints[$i]['y'];
        }
        $svg .= '<path d="' . $path . '" stroke="#FFC000" stroke-width="2" fill="none" stroke-dasharray="5,5" opacity="0.8"/>' . "\n";
        
        // 绘制点
        foreach ($linePoints as $point) {
            $svg .= '<circle cx="' . $point['x'] . '" cy="' . $point['y'] . '" r="4" fill="#FFC000"/>' . "\n";
        }
    }
    
    // 图例
    $legendY = $marginTop + 20;
    $svg .= '<rect x="' . ($width - 180) . '" y="' . $legendY . '" width="15" height="15" fill="#4F81BD" opacity="0.8"/>' . "\n";
    $svg .= '<text x="' . ($width - 160) . '" y="' . ($legendY + 12) . '" font-size="11" fill="#000000">病历总数</text>' . "\n";
    $svg .= '<rect x="' . ($width - 180) . '" y="' . ($legendY + 20) . '" width="15" height="15" fill="#FFC000" opacity="0.8"/>' . "\n";
    $svg .= '<text x="' . ($width - 160) . '" y="' . ($legendY + 32) . '" font-size="11" fill="#000000">缺陷病历</text>' . "\n";
    $svg .= '<line x1="' . ($width - 180) . '" y1="' . ($legendY + 45) . '" x2="' . ($width - 165) . '" y2="' . ($legendY + 45) . '" stroke="#FFC000" stroke-width="2" stroke-dasharray="5,5" opacity="0.8"/>' . "\n";
    $svg .= '<text x="' . ($width - 160) . '" y="' . ($legendY + 48) . '" font-size="11" fill="#000000">线性(缺陷病历)</text>' . "\n";
    
    $svg .= '</svg>';
    
    // 保存 SVG
    $svgPath = str_replace('.png', '.svg', $outputPath);
    file_put_contents($svgPath, $svg);
    
    // 尝试转换为 PNG（需要 ImageMagick 或 Inkscape）
    $converted = false;
    
    // 方法1: 使用 ImageMagick
    if (function_exists('exec')) {
        $cmd = "convert -background white -density 300 \"$svgPath\" \"$outputPath\" 2>&1";
        exec($cmd, $output, $returnCode);
        if ($returnCode === 0 && file_exists($outputPath)) {
            $converted = true;
            unlink($svgPath); // 删除临时 SVG
        }
    }
    
    // 方法2: 使用 Inkscape
    if (!$converted && function_exists('exec')) {
        $cmd = "inkscape --export-type=png --export-filename=\"$outputPath\" \"$svgPath\" 2>&1";
        exec($cmd, $output, $returnCode);
        if ($returnCode === 0 && file_exists($outputPath)) {
            $converted = true;
            unlink($svgPath);
        }
    }
    
    if (!$converted) {
        // 如果无法转换，返回 SVG 路径并提示
        echo "注意: 无法转换为 PNG，已生成 SVG 文件: $svgPath\n";
        echo "提示: 请安装 ImageMagick (brew install imagemagick) 或 Inkscape\n";
        echo "或者: 在浏览器中打开 SVG 文件并截图保存为 PNG\n";
        return $svgPath;
    }
    
    return $outputPath;
}

// 测试数据
$chartData = [
    '2025年11月' => [
        'total' => 123456,    // 病历总数
        'defect' => 12345,   // 缺陷病历
    ],
    '2025年12月' => [
        'total' => 123456,    // 病历总数
        'defect' => 22345,    // 缺陷病历
    ],
];

// 输出路径
$baseDir = __DIR__;
$outputPath = $baseDir . '/storage/app/charts/病案内涵质控趋势图.png';

// 确保目录存在
$chartDir = dirname($outputPath);
if (!is_dir($chartDir)) {
    mkdir($chartDir, 0755, true);
}

// 生成图表
$result = generateChart($chartData, $outputPath);

if (file_exists($outputPath)) {
    echo "图表已生成: $outputPath\n";
    echo "图片大小: " . number_format(filesize($outputPath) / 1024, 2) . " KB\n";
} else {
    echo "图表生成失败，请检查错误信息\n";
}

