<?php
/**
 * 修复 PhpWord autoload 问题
 * 用于手动上传 PhpWord 后重新生成 autoload 文件
 */

echo "=== 修复 PhpWord Autoload ===\n\n";

$vendorDir = __DIR__ . '/vendor';
$phpwordDir = $vendorDir . '/phpoffice/phpword';

// 1. 检查 PhpWord 目录
if (!file_exists($phpwordDir)) {
    echo "❌ PhpWord 目录不存在: {$phpwordDir}\n";
    echo "请先确保 PhpWord 已上传到正确位置\n";
    exit(1);
}

echo "✅ PhpWord 目录存在: {$phpwordDir}\n";

// 2. 检查 composer.json
$composerJson = __DIR__ . '/composer.json';
if (!file_exists($composerJson)) {
    echo "❌ composer.json 不存在\n";
    exit(1);
}

$composerData = json_decode(file_get_contents($composerJson), true);
if (!isset($composerData['require']['phpoffice/phpword'])) {
    echo "⚠️  composer.json 中未找到 phpoffice/phpword 依赖\n";
    echo "但这不影响修复，继续...\n";
}

// 3. 检查 autoload_psr4.php
$autoloadPsr4File = $vendorDir . '/composer/autoload_psr4.php';
if (file_exists($autoloadPsr4File)) {
    $autoloadPsr4 = require $autoloadPsr4File;
    
    // 检查是否已有 PhpWord 映射
    if (isset($autoloadPsr4['PhpOffice\\PhpWord\\'])) {
        $currentPath = $autoloadPsr4['PhpOffice\\PhpWord\\'][0];
        $expectedPath = $phpwordDir . '/src';
        
        if ($currentPath === $expectedPath) {
            echo "✅ autoload_psr4.php 映射正确\n";
        } else {
            echo "⚠️  autoload_psr4.php 映射路径不匹配:\n";
            echo "   当前: {$currentPath}\n";
            echo "   期望: {$expectedPath}\n";
        }
    } else {
        echo "⚠️  autoload_psr4.php 中未找到 PhpWord 映射\n";
        echo "   需要添加: 'PhpOffice\\PhpWord\\' => array('{$phpwordDir}/src')\n";
    }
} else {
    echo "❌ autoload_psr4.php 不存在\n";
}

// 4. 尝试运行 composer dump-autoload
echo "\n=== 运行 composer dump-autoload ===\n";
$command = 'cd ' . escapeshellarg(__DIR__) . ' && composer dump-autoload 2>&1';
echo "执行命令: {$command}\n\n";

$output = [];
$returnVar = 0;
exec($command, $output, $returnVar);

if ($returnVar === 0) {
    echo "✅ composer dump-autoload 执行成功\n";
    foreach ($output as $line) {
        echo "   {$line}\n";
    }
} else {
    echo "❌ composer dump-autoload 执行失败\n";
    foreach ($output as $line) {
        echo "   {$line}\n";
    }
    echo "\n如果 composer 命令不可用，可以手动修复 autoload_psr4.php\n";
}

// 5. 验证修复结果
echo "\n=== 验证修复结果 ===\n";
if (file_exists($vendorDir . '/autoload.php')) {
    require_once $vendorDir . '/autoload.php';
}

if (class_exists('PhpOffice\PhpWord\TemplateProcessor')) {
    echo "✅ TemplateProcessor 类现在可用！\n";
} else {
    echo "❌ TemplateProcessor 类仍然不可用\n";
    echo "\n手动修复步骤:\n";
    echo "1. 编辑文件: {$autoloadPsr4File}\n";
    echo "2. 在 return array( 中添加:\n";
    echo "   'PhpOffice\\PhpWord\\' => array('{$phpwordDir}/src'),\n";
    echo "3. 保存文件\n";
    echo "4. 重新运行此脚本验证\n";
}

echo "\n=== 修复完成 ===\n";

