<?php
/**
 * 简单修复 autoload：移除缺失文件的引用
 */

echo "=== 修复 Autoload 缺失文件 ===\n\n";

$vendorDir = __DIR__ . '/vendor';
$autoloadStaticFile = $vendorDir . '/composer/autoload_static.php';

if (!file_exists($autoloadStaticFile)) {
    echo "❌ autoload_static.php 不存在\n";
    exit(1);
}

// 备份
$backupFile = $autoloadStaticFile . '.backup.' . date('YmdHis');
copy($autoloadStaticFile, $backupFile);
echo "✅ 已备份到: {$backupFile}\n\n";

// 读取文件
$content = file_get_contents($autoloadStaticFile);

// 查找并移除缺失的文件引用
$removedCount = 0;

// 1. 移除 $files 数组中的 amphp/amp/lib/functions.php 引用
$pattern = "/'e8aa6e4b5a1db2f56ae794f1505391a8' => __DIR__ \. '\/\.\.' \. '\/amphp\/amp\/lib\/functions\.php',\s*\n/";
if (preg_match($pattern, $content)) {
    $content = preg_replace($pattern, '', $content);
    $removedCount++;
    echo "✅ 移除了 amphp/amp/lib/functions.php 的引用\n";
}

// 2. 移除 PSR-4 映射中的 Amp\ 命名空间
$pattern = "/\s+'Amp\\\\' =>\s+array\s+\(\s+0 => __DIR__ \. '\/\.\.' \. '\/amphp\/amp\/lib',\s+\),\s*\n/";
if (preg_match($pattern, $content)) {
    $content = preg_replace($pattern, '', $content);
    $removedCount++;
    echo "✅ 移除了 Amp\\ PSR-4 映射\n";
}

// 3. 移除 classmap 中所有 Amp\ 类的引用
$lines = explode("\n", $content);
$newLines = [];
$inClassmap = false;
$removedClasses = 0;

foreach ($lines as $line) {
    // 检测是否进入 classmap 数组
    if (strpos($line, "public static \$classMap = array (") !== false) {
        $inClassmap = true;
        $newLines[] = $line;
        continue;
    }
    
    // 检测 classmap 数组结束
    if ($inClassmap && strpos($line, ');') !== false && substr_count($line, ')') > substr_count($line, '(')) {
        $inClassmap = false;
        $newLines[] = $line;
        continue;
    }
    
    // 在 classmap 中，移除所有 Amp\ 开头的类
    if ($inClassmap && preg_match("/'Amp\\\\/", $line)) {
        $removedClasses++;
        continue; // 跳过这一行
    }
    
    $newLines[] = $line;
}

if ($removedClasses > 0) {
    $content = implode("\n", $newLines);
    $removedCount += $removedClasses;
    echo "✅ 移除了 {$removedClasses} 个 Amp 类的 classmap 引用\n";
}

// 写入修复后的文件
file_put_contents($autoloadStaticFile, $content);
echo "\n✅ 已修复 autoload_static.php（共移除 {$removedCount} 个引用）\n";

// 验证
echo "\n=== 验证修复 ===\n";
try {
    require_once $vendorDir . '/autoload.php';
    echo "✅ autoload.php 加载成功\n";
    
    if (class_exists('PhpOffice\PhpWord\TemplateProcessor')) {
        echo "✅✅✅ TemplateProcessor 类可用！\n";
    } else {
        echo "⚠️  TemplateProcessor 类仍然不可用\n";
    }
} catch (\Exception $e) {
    echo "❌ 验证失败: " . $e->getMessage() . "\n";
    echo "   可以恢复备份: cp {$backupFile} {$autoloadStaticFile}\n";
}

echo "\n=== 完成 ===\n";

