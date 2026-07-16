#!/bin/bash

# 创建无网环境部署包

set -e

echo "=========================================="
echo "创建无网环境部署包"
echo "=========================================="

PROJECT_DIR="/Volumes/MACOS/work/zk/GY_homeQuality"
DEPLOY_DIR="/tmp/GY_homeQuality_deploy_$(date +%s)"
DEPLOY_PACKAGE="GY_homeQuality_deploy_$(date +%Y%m%d_%H%M%S).tar.gz"

cd "$PROJECT_DIR"

echo "1. 创建临时部署目录..."
mkdir -p "$DEPLOY_DIR"

echo "2. 复制代码文件..."

# 复制新增/修改的服务类
mkdir -p "$DEPLOY_DIR/app/Services"
cp app/Services/WordReportService.php "$DEPLOY_DIR/app/Services/"

# 复制修改的控制器
mkdir -p "$DEPLOY_DIR/app/Http/Controllers/Api"
cp app/Http/Controllers/Api/HomeSzQualityController.php "$DEPLOY_DIR/app/Http/Controllers/Api/"

# 复制更新的 composer.json
cp composer.json "$DEPLOY_DIR/"

echo "3. 复制 PhpWord 依赖..."

# 复制 PhpWord 相关文件
mkdir -p "$DEPLOY_DIR/vendor/phpoffice"
if [ -d "vendor/phpoffice/phpword" ]; then
    cp -r vendor/phpoffice/phpword "$DEPLOY_DIR/vendor/phpoffice/"
    echo "   ✓ 复制 phpword"
else
    echo "   ✗ 警告: vendor/phpoffice/phpword 不存在"
fi

if [ -d "vendor/phpoffice/common" ]; then
    cp -r vendor/phpoffice/common "$DEPLOY_DIR/vendor/phpoffice/"
    echo "   ✓ 复制 common"
else
    echo "   ✗ 警告: vendor/phpoffice/common 不存在"
fi

# 复制更新的 autoload 文件
mkdir -p "$DEPLOY_DIR/vendor/composer"
if [ -f "vendor/composer/autoload_psr4.php" ]; then
    cp vendor/composer/autoload_psr4.php "$DEPLOY_DIR/vendor/composer/"
    echo "   ✓ 复制 autoload_psr4.php"
fi

if [ -f "vendor/composer/autoload_static.php" ]; then
    cp vendor/composer/autoload_static.php "$DEPLOY_DIR/vendor/composer/"
    echo "   ✓ 复制 autoload_static.php"
fi

echo "4. 复制模板文件..."
if [ -f "病历质控分析报告.docx" ]; then
    cp "病历质控分析报告.docx" "$DEPLOY_DIR/"
    echo "   ✓ 复制 Word 模板"
else
    echo "   ✗ 警告: 模板文件不存在"
fi

echo "5. 创建部署脚本..."
cat > "$DEPLOY_DIR/部署.sh" << 'DEPLOY_SCRIPT'
#!/bin/bash

# 无网环境部署脚本

set -e

echo "=========================================="
echo "部署 Word 报告生成功能"
echo "=========================================="

# 获取项目根目录（假设部署包解压到项目根目录）
if [ -f "部署.sh" ]; then
    # 在部署包目录中
    DEPLOY_DIR=$(pwd)
    PROJECT_ROOT=$(dirname "$DEPLOY_DIR")
else
    # 在项目根目录中
    PROJECT_ROOT=$(pwd)
fi

echo "项目根目录: $PROJECT_ROOT"
cd "$PROJECT_ROOT"

echo "1. 检查 PHP 环境..."
PHP_VERSION=$(php -v | head -1)
echo "   PHP 版本: $PHP_VERSION"

# 检查必需的扩展
echo "2. 检查 PHP 扩展..."
REQUIRED_EXT=("zip" "xml" "dom" "json")
MISSING_EXT=()

for ext in "${REQUIRED_EXT[@]}"; do
    if php -m | grep -qi "^$ext$"; then
        echo "   ✓ $ext 扩展已安装"
    else
        echo "   ✗ $ext 扩展未安装"
        MISSING_EXT+=("$ext")
    fi
done

if [ ${#MISSING_EXT[@]} -gt 0 ]; then
    echo ""
    echo "错误: 缺少必需的 PHP 扩展: ${MISSING_EXT[*]}"
    echo "请先安装这些扩展"
    exit 1
fi

echo "3. 部署代码文件..."
if [ -f "$DEPLOY_DIR/app/Services/WordReportService.php" ]; then
    mkdir -p app/Services
    cp "$DEPLOY_DIR/app/Services/WordReportService.php" app/Services/
    echo "   ✓ 部署 WordReportService"
else
    echo "   ✗ 找不到 WordReportService.php"
    exit 1
fi

if [ -f "$DEPLOY_DIR/app/Http/Controllers/Api/HomeSzQualityController.php" ]; then
    mkdir -p app/Http/Controllers/Api
    cp "$DEPLOY_DIR/app/Http/Controllers/Api/HomeSzQualityController.php" app/Http/Controllers/Api/
    echo "   ✓ 更新 HomeSzQualityController"
fi

echo "4. 部署 PhpWord 依赖..."
if [ -d "$DEPLOY_DIR/vendor/phpoffice/phpword" ]; then
    mkdir -p vendor/phpoffice
    cp -r "$DEPLOY_DIR/vendor/phpoffice/phpword" vendor/phpoffice/
    echo "   ✓ 部署 PhpWord"
else
    echo "   ✗ 找不到 PhpWord 文件"
    exit 1
fi

if [ -d "$DEPLOY_DIR/vendor/phpoffice/common" ]; then
    cp -r "$DEPLOY_DIR/vendor/phpoffice/common" vendor/phpoffice/
    echo "   ✓ 部署 Common"
fi

echo "5. 更新 autoload 文件..."
if [ -f "$DEPLOY_DIR/vendor/composer/autoload_psr4.php" ]; then
    mkdir -p vendor/composer
    
    # 备份原文件
    if [ -f "vendor/composer/autoload_psr4.php" ]; then
        cp vendor/composer/autoload_psr4.php vendor/composer/autoload_psr4.php.backup
    fi
    
    # 合并 autoload 配置
    if [ -f "vendor/composer/autoload_psr4.php" ]; then
        # 检查是否已包含 PhpWord
        if ! grep -q "PhpOffice\\\\PhpWord" vendor/composer/autoload_psr4.php; then
            # 添加 PhpWord 映射
            sed -i.bak '/PhpOffice\\PhpSpreadsheet\\/a\
    '\''PhpOffice\\PhpWord\\'\'' => array($vendorDir . '\''/phpoffice/phpword/src'\''),\
    '\''PhpOffice\\Common\\'\'' => array($vendorDir . '\''/phpoffice/common/src'\''),' vendor/composer/autoload_psr4.php
            echo "   ✓ 更新 autoload_psr4.php"
        else
            echo "   ✓ autoload_psr4.php 已包含 PhpWord"
        fi
    else
        cp "$DEPLOY_DIR/vendor/composer/autoload_psr4.php" vendor/composer/
        echo "   ✓ 复制 autoload_psr4.php"
    fi
    
    if [ -f "$DEPLOY_DIR/vendor/composer/autoload_static.php" ]; then
        if [ -f "vendor/composer/autoload_static.php" ]; then
            cp vendor/composer/autoload_static.php vendor/composer/autoload_static.php.backup
        fi
        
        # 合并 autoload_static.php（需要手动处理或直接替换）
        # 这里简化处理：如果原文件存在，提示手动合并
        if [ -f "vendor/composer/autoload_static.php" ]; then
            echo "   ⚠ autoload_static.php 需要手动合并或使用部署包中的版本"
            echo "   备份文件: vendor/composer/autoload_static.php.backup"
        fi
        
        cp "$DEPLOY_DIR/vendor/composer/autoload_static.php" vendor/composer/
        echo "   ✓ 更新 autoload_static.php"
    fi
else
    echo "   ✗ 找不到 autoload 文件"
    exit 1
fi

echo "6. 部署模板文件..."
if [ -f "$DEPLOY_DIR/病历质控分析报告.docx" ]; then
    cp "$DEPLOY_DIR/病历质控分析报告.docx" .
    echo "   ✓ 部署 Word 模板"
else
    echo "   ⚠ 模板文件不存在，请手动复制"
fi

echo "7. 创建必要的目录..."
mkdir -p storage/app/reports
chmod 755 storage/app/reports
echo "   ✓ 创建 reports 目录"

echo "8. 更新 composer.json（如果需要）..."
if [ -f "$DEPLOY_DIR/composer.json" ]; then
    # 检查是否需要更新
    if ! grep -q "phpoffice/phpword" composer.json 2>/dev/null; then
        # 添加 phpoffice/phpword 到 composer.json
        # 这里简化处理，实际应该使用更安全的方法
        echo "   ⚠ 请手动检查 composer.json 是否包含 phpoffice/phpword"
    else
        echo "   ✓ composer.json 已包含 phpoffice/phpword"
    fi
fi

echo ""
echo "=========================================="
echo "部署完成！"
echo "=========================================="
echo ""
echo "下一步："
echo "1. 验证部署: php 验证部署.php"
echo "2. 测试功能: php test_word_report.php"
echo "3. 如果使用 Laravel，重启服务"
echo ""

DEPLOY_SCRIPT

chmod +x "$DEPLOY_DIR/部署.sh"

echo "6. 创建验证脚本..."
cat > "$DEPLOY_DIR/验证部署.php" << 'VERIFY_SCRIPT'
<?php
/**
 * 验证部署是否成功
 */

require __DIR__ . '/../vendor/autoload.php';

echo "==========================================\n";
echo "验证部署\n";
echo "==========================================\n\n";

$errors = [];
$warnings = [];

// 1. 检查 PhpWord 类
echo "1. 检查 PhpWord 类...\n";
if (class_exists('PhpOffice\PhpWord\TemplateProcessor')) {
    echo "   ✓ PhpWord TemplateProcessor 类可用\n";
} else {
    echo "   ✗ PhpWord TemplateProcessor 类不可用\n";
    $errors[] = "PhpWord 类未加载";
}

// 2. 检查服务类
echo "\n2. 检查 WordReportService...\n";
if (class_exists('App\Services\WordReportService')) {
    echo "   ✓ WordReportService 类存在\n";
} else {
    echo "   ✗ WordReportService 类不存在\n";
    $errors[] = "WordReportService 类未找到";
}

// 3. 检查模板文件
echo "\n3. 检查模板文件...\n";
$templatePath = __DIR__ . '/../病历质控分析报告.docx';
if (file_exists($templatePath)) {
    echo "   ✓ 模板文件存在: $templatePath\n";
} else {
    echo "   ✗ 模板文件不存在\n";
    $warnings[] = "模板文件不存在: $templatePath";
}

// 4. 检查 reports 目录
echo "\n4. 检查 reports 目录...\n";
$reportsDir = __DIR__ . '/../storage/app/reports';
if (is_dir($reportsDir)) {
    if (is_writable($reportsDir)) {
        echo "   ✓ reports 目录可写\n";
    } else {
        echo "   ✗ reports 目录不可写\n";
        $errors[] = "reports 目录权限不足";
    }
} else {
    echo "   ✗ reports 目录不存在\n";
    $errors[] = "reports 目录不存在";
}

// 5. 测试加载模板
echo "\n5. 测试加载模板...\n";
if (class_exists('App\Services\WordReportService') && file_exists($templatePath)) {
    try {
        $service = new App\Services\WordReportService();
        $service->loadTemplate($templatePath);
        echo "   ✓ 模板加载成功\n";
    } catch (\Exception $e) {
        echo "   ✗ 模板加载失败: " . $e->getMessage() . "\n";
        $errors[] = "模板加载失败: " . $e->getMessage();
    }
}

echo "\n==========================================\n";
if (empty($errors)) {
    echo "✓ 部署验证通过！\n";
    if (!empty($warnings)) {
        echo "\n警告:\n";
        foreach ($warnings as $warning) {
            echo "  - $warning\n";
        }
    }
} else {
    echo "✗ 部署验证失败\n";
    echo "\n错误:\n";
    foreach ($errors as $error) {
        echo "  - $error\n";
    }
    exit(1);
}
echo "==========================================\n";

VERIFY_SCRIPT

echo "7. 打包部署文件..."
cd "$(dirname "$DEPLOY_DIR")"
tar -czf "$PROJECT_DIR/$DEPLOY_PACKAGE" -C "$(dirname "$DEPLOY_DIR")" "$(basename "$DEPLOY_DIR")"

# 计算文件大小
FILE_SIZE=$(du -h "$PROJECT_DIR/$DEPLOY_PACKAGE" | cut -f1)

echo ""
echo "=========================================="
echo "部署包创建完成！"
echo "=========================================="
echo "文件位置: $PROJECT_DIR/$DEPLOY_PACKAGE"
echo "文件大小: $FILE_SIZE"
echo ""
echo "部署包包含："
echo "  - WordReportService.php"
echo "  - HomeSzQualityController.php (更新)"
echo "  - PhpWord 依赖包"
echo "  - 更新的 autoload 文件"
echo "  - Word 模板文件"
echo "  - 部署脚本"
echo "  - 验证脚本"
echo ""
echo "下一步："
echo "1. 将 $DEPLOY_PACKAGE 传输到目标服务器"
echo "2. 在服务器上解压: tar -xzf $DEPLOY_PACKAGE"
echo "3. 运行部署脚本: cd GY_homeQuality_deploy_* && ./部署.sh"
echo ""

# 清理临时目录
rm -rf "$DEPLOY_DIR"

