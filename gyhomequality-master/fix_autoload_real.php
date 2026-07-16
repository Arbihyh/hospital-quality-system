<?php
/**
 * 修复 autoload_real.php：添加文件存在性检查，避免缺失文件导致 fatal error
 */

echo "=== 修复 autoload_real.php ===\n\n";

$vendorDir = __DIR__ . '/vendor';
$autoloadRealFile = $vendorDir . '/composer/autoload_real.php';

if (!file_exists($autoloadRealFile)) {
    echo "❌ autoload_real.php 不存在\n";
    exit(1);
}

// 备份
$backupFile = $autoloadRealFile . '.backup.' . date('YmdHis');
copy($autoloadRealFile, $backupFile);
echo "✅ 已备份到: {$backupFile}\n\n";

// 读取文件
$content = file_get_contents($autoloadRealFile);

// 查找 composerRequire 函数
$pattern = '/function composerRequire[^(]+\([^)]+\)\s*\{[^}]*require \$file;[^}]*\}/s';

if (preg_match($pattern, $content, $matches)) {
    $oldFunction = $matches[0];
    
    // 检查是否已经修复过
    if (strpos($oldFunction, 'file_exists') !== false) {
        echo "✅ autoload_real.php 已经修复过了\n";
        exit(0);
    }
    
    // 替换为修复后的版本
    $newFunction = preg_replace(
        '/require \$file;/',
        "// 检查文件是否存在，如果不存在则跳过（避免 fatal error）\n        if (file_exists(\$file)) {\n            require \$file;\n        }",
        $oldFunction
    );
    
    $content = str_replace($oldFunction, $newFunction, $content);
    
    // 写入修复后的文件
    file_put_contents($autoloadRealFile, $content);
    echo "✅ 已修复 autoload_real.php\n";
    echo "   现在缺失的文件会被自动跳过，不会导致 fatal error\n";
} else {
    echo "⚠️  未找到 composerRequire 函数，可能格式不同\n";
    echo "   尝试手动查找并修复...\n";
    
    // 尝试更简单的替换
    $oldCode = '        require $file;';
    $newCode = "        // 检查文件是否存在，如果不存在则跳过（避免 fatal error）\n        if (file_exists(\$file)) {\n            require \$file;\n        }";
    
    if (strpos($content, $oldCode) !== false) {
        $content = str_replace($oldCode, $newCode, $content);
        file_put_contents($autoloadRealFile, $content);
        echo "✅ 已修复 autoload_real.php（使用简单替换）\n";
    } else {
        echo "❌ 无法找到需要替换的代码\n";
        echo "   请手动编辑文件，找到 'require \$file;' 并替换为：\n";
        echo "   if (file_exists(\$file)) {\n";
        echo "       require \$file;\n";
        echo "   }\n";
        exit(1);
    }
}

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
    echo "   可以恢复备份: cp {$backupFile} {$autoloadRealFile}\n";
}

echo "\n=== 完成 ===\n";
echo "\n现在即使 vendor 目录缺少某些依赖包，也不会导致 fatal error。\n";
echo "缺失的文件会被自动跳过。\n";

