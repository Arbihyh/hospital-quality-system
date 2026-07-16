<?php

/**
 * 生成病案内涵质控趋势图（柱状图+折线图）
 * 使用 HTML5 Canvas + Chart.js 生成图表，然后通过浏览器截图
 */

function generateChartHTML($data, $outputPath) {
    $months = json_encode(array_keys($data));
    $totals = json_encode(array_column($data, 'total'));
    $defects = json_encode(array_column($data, 'defect'));
    
    $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>病案内涵质控趋势图</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <style>
        body {
            margin: 0;
            padding: 20px;
            background: white;
            font-family: Arial, "Microsoft YaHei", sans-serif;
        }
        #chartContainer {
            width: 800px;
            height: 500px;
            margin: 0 auto;
        }
    </style>
</head>
<body>
    <div id="chartContainer">
        <canvas id="myChart"></canvas>
    </div>
    <script>
        const ctx = document.getElementById('myChart').getContext('2d');
        const months = {$months};
        const totals = {$totals};
        const defects = {$defects};
        
        const chart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: months,
                datasets: [
                    {
                        label: '病历总数',
                        data: totals,
                        backgroundColor: 'rgba(79, 129, 189, 0.8)',
                        borderColor: 'rgba(79, 129, 189, 1)',
                        borderWidth: 1,
                        yAxisID: 'y'
                    },
                    {
                        label: '缺陷病历',
                        data: defects,
                        backgroundColor: 'rgba(255, 192, 0, 0.8)',
                        borderColor: 'rgba(255, 192, 0, 1)',
                        borderWidth: 1,
                        yAxisID: 'y'
                    },
                    {
                        label: '线性(缺陷病历)',
                        data: defects,
                        type: 'line',
                        borderColor: 'rgba(255, 192, 0, 1)',
                        borderWidth: 2,
                        borderDash: [5, 5],
                        fill: false,
                        pointRadius: 5,
                        pointBackgroundColor: 'rgba(255, 192, 0, 1)',
                        yAxisID: 'y'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                        text: '病案内涵质控趋势图',
                        font: {
                            size: 16,
                            weight: 'bold'
                        }
                    },
                    legend: {
                        display: true,
                        position: 'top'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ' + context.parsed.y.toLocaleString();
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
        
        // 等待图表渲染完成后，可以手动截图
        setTimeout(function() {
            console.log('图表已渲染完成，请使用浏览器截图功能保存为 PNG');
        }, 1000);
    </script>
</body>
</html>
HTML;
    
    $htmlPath = str_replace('.png', '.html', $outputPath);
    file_put_contents($htmlPath, $html);
    
    return $htmlPath;
}

// 测试数据
$chartData = [
    '2025年11月' => [
        'total' => 123456,
        'defect' => 12345,
    ],
    '2025年12月' => [
        'total' => 123456,
        'defect' => 22345,
    ],
];

$baseDir = __DIR__;
$outputPath = $baseDir . '/storage/app/charts/病案内涵质控趋势图.png';
$chartDir = dirname($outputPath);
if (!is_dir($chartDir)) {
    mkdir($chartDir, 0755, true);
}

$htmlPath = generateChartHTML($chartData, $outputPath);
echo "HTML 图表已生成: $htmlPath\n";
echo "请在浏览器中打开此文件，然后使用截图工具保存为 PNG 图片\n";
echo "或者使用 headless browser (如 Puppeteer) 自动截图\n";

