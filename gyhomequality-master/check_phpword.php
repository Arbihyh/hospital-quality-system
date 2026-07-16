<?php
/**
 * PhpWord 环境检查脚本
 * 用于诊断 TemplateProcessor 类不可用的问题
 */

echo "=== PhpWord 环境检查 ===\n\n";

// 1. 检查 PHP 版本
echo "1. PHP 版本: " . PHP_VERSION . "\n";
if (version_compare(PHP_VERSION, '7.2.5', '<')) {
    echo "   ❌ PHP 版本过低，需要 >= 7.2.5\n";
} else {
    echo "   ✅ PHP 版本符合要求\n";
}

// 2. 检查必要的 PHP 扩展
echo "\n2. PHP 扩展检查:\n";
$requiredExtensions = ['zip', 'xml', 'zlib'];
foreach ($requiredExtensions as $ext) {
    if (extension_loaded($ext)) {
        echo "   ✅ {$ext} 扩展已安装\n";
    } else {
        echo "   ❌ {$ext} 扩展未安装\n";
    }
}

// 3. 检查 vendor 目录
echo "\n3. Composer 依赖检查:\n";
$vendorPath = __DIR__ . '/vendor';
if (file_exists($vendorPath)) {
    echo "   ✅ vendor 目录存在\n";
    
    // 检查 autoload 文件
    $autoloadPath = $vendorPath . '/autoload.php';
    if (file_exists($autoloadPath)) {
        echo "   ✅ autoload.php 文件存在\n";
        
        // 尝试加载 autoload
        try {
            require_once $autoloadPath;
            echo "   ✅ autoload.php 加载成功\n";
        } catch (\Exception $e) {
            echo "   ❌ autoload.php 加载失败: " . $e->getMessage() . "\n";
        }
    } else {
        echo "   ❌ autoload.php 文件不存在\n";
    }
    
    // 检查 phpoffice/phpword 目录
    $phpwordPath = $vendorPath . '/phpoffice/phpword';
    if (file_exists($phpwordPath)) {
        echo "   ✅ phpoffice/phpword 目录存在\n";
        
        // 检查 TemplateProcessor 文件
        $templateProcessorPath = $phpwordPath . '/src/TemplateProcessor.php';
        if (file_exists($templateProcessorPath)) {
            echo "   ✅ TemplateProcessor.php 文件存在\n";
        } else {
            echo "   ❌ TemplateProcessor.php 文件不存在\n";
        }
    } else {
        echo "   ❌ phpoffice/phpword 目录不存在\n";
    }
} else {
    echo "   ❌ vendor 目录不存在\n";
    echo "   请运行: composer install\n";
}

// 4. 检查 TemplateProcessor 类
echo "\n4. TemplateProcessor 类检查:\n";

// 先尝试加载 autoload
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

// 检查文件是否存在
$templateProcessorFile = __DIR__ . '/vendor/phpoffice/phpword/src/TemplateProcessor.php';
if (file_exists($templateProcessorFile)) {
    echo "   ✅ TemplateProcessor.php 文件存在: {$templateProcessorFile}\n";
    
    // 检查文件内容
    $fileContent = file_get_contents($templateProcessorFile);
    if (strpos($fileContent, 'namespace PhpOffice\\PhpWord') !== false) {
        echo "   ✅ 文件包含正确的命名空间\n";
    } else {
        echo "   ⚠️  文件命名空间可能不正确\n";
    }
    
    if (strpos($fileContent, 'class TemplateProcessor') !== false) {
        echo "   ✅ 文件包含 TemplateProcessor 类定义\n";
    } else {
        echo "   ❌ 文件不包含 TemplateProcessor 类定义\n";
    }
} else {
    echo "   ❌ TemplateProcessor.php 文件不存在\n";
    echo "   期望路径: {$templateProcessorFile}\n";
}

// 检查 autoload 映射
$autoloadPsr4File = __DIR__ . '/vendor/composer/autoload_psr4.php';
if (file_exists($autoloadPsr4File)) {
    $autoloadPsr4 = require $autoloadPsr4File;
    if (isset($autoloadPsr4['PhpOffice\\PhpWord\\'])) {
        $phpwordPath = $autoloadPsr4['PhpOffice\\PhpWord\\'][0];
        echo "   ✅ autoload_psr4.php 包含 PhpWord 映射: {$phpwordPath}\n";
        
        if (file_exists($phpwordPath)) {
            echo "   ✅ 映射路径存在\n";
        } else {
            echo "   ❌ 映射路径不存在: {$phpwordPath}\n";
        }
    } else {
        echo "   ❌ autoload_psr4.php 不包含 PhpWord 映射\n";
    }
}

// 尝试直接 require 文件
if (file_exists($templateProcessorFile)) {
    try {
        require_once $templateProcessorFile;
        echo "   ✅ 直接 require 文件成功\n";
    } catch (\Exception $e) {
        echo "   ❌ 直接 require 文件失败: " . $e->getMessage() . "\n";
    }
}

// 检查类是否存在
if (class_exists('PhpOffice\PhpWord\TemplateProcessor')) {
    echo "   ✅ TemplateProcessor 类可用\n";
    
    // 尝试实例化
    try {
        $reflection = new \ReflectionClass('PhpOffice\PhpWord\TemplateProcessor');
        echo "   ✅ TemplateProcessor 类可以反射\n";
    } catch (\Exception $e) {
        echo "   ❌ TemplateProcessor 类反射失败: " . $e->getMessage() . "\n";
    }
} else {
    echo "   ❌ TemplateProcessor 类不可用\n";
    echo "   可能的原因:\n";
    echo "   - 需要运行: composer dump-autoload\n";
    echo "   - 文件路径不正确\n";
    echo "   - 命名空间不匹配\n";
}

// 5. 检查 WordReportService
echo "\n5. WordReportService 检查:\n";
$servicePath = __DIR__ . '/app/Services/WordReportService.php';
if (file_exists($servicePath)) {
    echo "   ✅ WordReportService.php 文件存在\n";
} else {
    echo "   ❌ WordReportService.php 文件不存在\n";
}

// 6. 提供解决方案
echo "\n=== 解决方案 ===\n";
echo "如果 TemplateProcessor 类不可用，请按以下步骤操作:\n\n";
echo "1. 确保已安装必要的 PHP 扩展:\n";
echo "   - Ubuntu/Debian: sudo apt-get install php-zip php-xml php-zlib\n";
echo "   - CentOS/RHEL: sudo yum install php-zip php-xml php-zlib\n\n";

echo "2. 在项目根目录运行 Composer 安装:\n";
echo "   composer install --no-dev --optimize-autoloader\n\n";

echo "3. 确保 vendor 目录已上传到服务器\n\n";

echo "4. 检查文件权限:\n";
echo "   chmod -R 755 vendor/\n";
echo "   chown -R www-data:www-data vendor/  # 根据实际用户调整\n\n";

echo "5. 如果使用 Laravel，清除缓存:\n";
echo "   php artisan config:clear\n";
echo "   php artisan cache:clear\n\n";

echo "=== 检查完成 ===\n";

