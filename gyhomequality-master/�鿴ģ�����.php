<?php
/**
 * 查看 Word 模板中的变量
 */

require __DIR__ . '/vendor/autoload.php';

use App\Services\WordReportService;

echo "==========================================\n";
echo "查看 Word 模板变量\n";
echo "==========================================\n\n";

try {
    $templatePath = __DIR__ . '/病历质控分析报告.docx';
    
    if (!file_exists($templatePath)) {
        echo "错误: 模板文件不存在\n";
        echo "路径: $templatePath\n";
        exit(1);
    }
    
    echo "模板文件: $templatePath\n";
    echo "文件大小: " . number_format(filesize($templatePath) / 1024, 2) . " KB\n\n";
    
    $service = new WordReportService();
    $service->loadTemplate($templatePath);
    
    echo "正在解析模板变量...\n\n";
    
    $variables = $service->getTemplateVariables();
    
    if (empty($variables)) {
        echo "⚠ 未找到任何变量\n";
        echo "\n提示: 在 Word 文档中使用 \${变量名} 格式设置变量\n";
        echo "例如: \${blCount}、\${averageScore} 等\n";
    } else {
        echo "找到 " . count($variables) . " 个变量：\n\n";
        foreach ($variables as $index => $var) {
            echo "  " . ($index + 1) . ". \${$var}\n";
        }
        
        // 针对表格变量做分组检查，方便你对照
        echo "\n==========================================\n";
        echo "表格相关变量分组检查：\n";
        echo "==========================================\n\n";

        $groupPrefixes = [
            '科室病案率列表(ks...)'      => 'ks',
            '质控合格率列表(ksHg...)'   => 'ksHg',
            '病案首页质控列表(sy...)'   => 'sy',
            '病案内涵质控列表(nh...)'   => 'nh',
        ];

        foreach ($groupPrefixes as $label => $prefix) {
            echo $label . ":\n";
            $found = 0;
            foreach ($variables as $var) {
                if (strpos($var, $prefix) === 0) {
                    echo "  - \${$var}\n";
                    $found++;
                }
            }
            if ($found === 0) {
                echo "  (未找到任何以 {$prefix} 开头的变量)\n";
            }
            echo "\n";
        }
        
        echo "==========================================\n";
        echo "代码中支持的单值变量：\n";
        echo "==========================================\n\n";
        
        $supportedVars = [
            'startTime', 'endTime', 'reportDate',
            'blCount', 'qxCount', 'averageScore',
            'youCount', 'liangCount', 'zhongCount', 'chaCount',
            'youRatio', 'liangRatio', 'zhongRatio', 'chaRatio'
        ];
        
        echo "时间相关:\n";
        echo "  - \${startTime} - 开始时间\n";
        echo "  - \${endTime} - 结束时间\n";
        echo "  - \${reportDate} - 报告日期\n\n";
        
        echo "基础统计:\n";
        echo "  - \${blCount} - 病历总数\n";
        echo "  - \${qxCount} - 缺陷数\n";
        echo "  - \${averageScore} - 平均得分\n\n";
        
        echo "优良中差数量:\n";
        echo "  - \${youCount} - 优的数量\n";
        echo "  - \${liangCount} - 良的数量\n";
        echo "  - \${zhongCount} - 中的数量\n";
        echo "  - \${chaCount} - 差的数量\n\n";
        
        echo "优良中差占比:\n";
        echo "  - \${youRatio} - 优的占比\n";
        echo "  - \${liangRatio} - 良的占比\n";
        echo "  - \${zhongRatio} - 中的占比\n";
        echo "  - \${chaRatio} - 差的占比\n\n";
        
        // 检查未定义的变量
        $undefined = array_diff($variables, $supportedVars);
        if (!empty($undefined)) {
            echo "⚠ 以下变量在模板中但未在代码中定义：\n";
            foreach ($undefined as $var) {
                echo "  - \${$var}\n";
            }
            echo "\n提示: 这些变量不会被替换，需要在代码中添加对应的数据映射\n";
        }
        
        // 检查代码中定义但模板中未使用的变量
        $unused = array_diff($supportedVars, $variables);
        if (!empty($unused)) {
            echo "\n提示: 以下变量在代码中已定义但模板中未使用：\n";
            foreach ($unused as $var) {
                echo "  - \${$var}\n";
            }
        }
    }
    
} catch (\Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
    if ($e->getTrace()) {
        echo "\n堆栈跟踪:\n";
        echo $e->getTraceAsString() . "\n";
    }
    exit(1);
}

