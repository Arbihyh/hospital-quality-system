<?php
/**
 * 修复 autoload 文件中缺失的依赖引用
 * 移除不存在的文件引用，避免 fatal error
 */

echo "=== 修复 Autoload 缺失文件引用 ===\n\n";

$vendorDir = __DIR__ . '/vendor';
$autoloadStaticFile = $vendorDir . '/composer/autoload_static.php';
$autoloadRealFile = $vendorDir . '/composer/autoload_real.php';

if (!file_exists($autoloadStaticFile)) {
    echo "❌ autoload_static.php 不存在\n";
    exit(1);
}

echo "1. 检查 autoload_static.php 中的文件引用:\n";
$content = file_get_contents($autoloadStaticFile);

// 提取所有文件引用
preg_match_all("/__DIR__ \. '\/\.\.' \. '([^']+)'/", $content, $matches);
$missingFiles = [];

foreach ($matches[1] as $filePath) {
    $fullPath = $vendorDir . $filePath;
    if (!file_exists($fullPath)) {
        $missingFiles[] = $filePath;
        echo "   ❌ 缺失: {$filePath}\n";
    }
}

if (empty($missingFiles)) {
    echo "   ✅ 所有文件都存在\n";
    echo "\n=== 无需修复 ===\n";
    exit(0);
}

echo "\n2. 修复 autoload_static.php:\n";

// 读取文件
$lines = file($autoloadStaticFile);
$newLines = [];
$inFilesArray = false;
$arrayDepth = 0;
$removedCount = 0;

foreach ($lines as $lineNum => $line) {
    // 检查是否在 $files 数组中
    if (strpos($line, "public static \$files = array (") !== false) {
        $inFilesArray = true;
        $arrayDepth = 0;
        $newLines[] = $line;
        continue;
    }
    
    if ($inFilesArray) {
        // 计算数组深度
        $arrayDepth += substr_count($line, '(') + substr_count($line, '[');
        $arrayDepth -= substr_count($line, ')') + substr_count($line, ']');
        
        // 检查这一行是否引用了缺失的文件
        $shouldRemove = false;
        foreach ($missingFiles as $missingFile) {
            if (strpos($line, $missingFile) !== false) {
                $shouldRemove = true;
                break;
            }
        }
        
        if ($shouldRemove) {
            echo "   移除第 " . ($lineNum + 1) . " 行: " . trim($line) . "\n";
            $removedCount++;
            continue; // 跳过这一行
        }
        
        $newLines[] = $line;
        
        // 检查数组是否结束
        if ($arrayDepth < 0) {
            $inFilesArray = false;
        }
    } else {
        $newLines[] = $line;
    }
}

// 备份原文件
$backupFile = $autoloadStaticFile . '.backup.' . date('YmdHis');
copy($autoloadStaticFile, $backupFile);
echo "\n   ✅ 已备份原文件到: {$backupFile}\n";

// 写入修复后的文件
file_put_contents($autoloadStaticFile, implode('', $newLines));
echo "   ✅ 已修复 autoload_static.php（移除了 {$removedCount} 个缺失文件的引用）\n";

// 3. 修复 autoload_psr4.php（如果需要）
echo "\n3. 检查 autoload_psr4.php:\n";
$autoloadPsr4File = $vendorDir . '/composer/autoload_psr4.php';
if (file_exists($autoloadPsr4File)) {
    $autoloadPsr4 = require $autoloadPsr4File;
    $removedPsr4 = 0;
    
    foreach ($autoloadPsr4 as $namespace => $paths) {
        foreach ($paths as $key => $path) {
            $fullPath = $vendorDir . '/' . str_replace($vendorDir, '', $path);
            if (!file_exists($fullPath) && !is_dir($fullPath)) {
                echo "   ⚠️  路径不存在: {$namespace} => {$path}\n";
                // 注意：autoload_psr4.php 是动态生成的，通常不需要手动修复
            }
        }
    }
    
    echo "   ✅ autoload_psr4.php 检查完成\n";
}

// 4. 验证修复
echo "\n4. 验证修复:\n";
try {
    require_once $vendorDir . '/autoload.php';
    echo "   ✅ autoload.php 加载成功\n";
    
    // 检查 PhpWord
    if (class_exists('PhpOffice\PhpWord\TemplateProcessor')) {
        echo "   ✅✅✅ TemplateProcessor 类现在可用！\n";
    } else {
        echo "   ⚠️  TemplateProcessor 类仍然不可用\n";
        echo "   可能需要检查 PhpWord 文件是否存在\n";
    }
} catch (\Exception $e) {
    echo "   ❌ autoload.php 加载失败: " . $e->getMessage() . "\n";
    echo "   可能需要恢复备份文件\n";
    echo "   恢复命令: cp {$backupFile} {$autoloadStaticFile}\n";
}

echo "\n=== 修复完成 ===\n";
echo "\n注意：这只是临时修复。建议运行 'composer install' 安装完整的依赖。\n";

