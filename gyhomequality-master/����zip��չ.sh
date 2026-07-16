#!/bin/bash

# 启用 PHP Zip 扩展脚本

echo "=========================================="
echo "启用 PHP Zip 扩展"
echo "=========================================="

PHP_INI="/usr/local/php/etc/php.ini"
EXT_DIR="/usr/local/php/lib/php/extensions/no-debug-non-zts-20240924"

echo "1. 检查 PHP 配置..."
if [ ! -f "$PHP_INI" ]; then
    echo "   错误: 找不到 php.ini 文件: $PHP_INI"
    exit 1
fi

echo "   ✓ 找到 php.ini: $PHP_INI"

echo "\n2. 检查扩展目录..."
if [ ! -d "$EXT_DIR" ]; then
    echo "   错误: 找不到扩展目录: $EXT_DIR"
    exit 1
fi

echo "   ✓ 找到扩展目录: $EXT_DIR"

echo "\n3. 检查 zip 扩展文件..."
if [ -f "$EXT_DIR/zip.so" ]; then
    echo "   ✓ 找到 zip.so 文件"
    ZIP_EXT_EXISTS=true
else
    echo "   ✗ 未找到 zip.so 文件"
    echo "   需要安装 zip 扩展"
    ZIP_EXT_EXISTS=false
fi

echo "\n4. 检查 php.ini 配置..."
if grep -q "^extension=zip" "$PHP_INI"; then
    echo "   ✓ zip 扩展已启用"
    exit 0
elif grep -q "^;extension=zip" "$PHP_INI"; then
    echo "   ⚠ zip 扩展被注释，需要启用"
    NEED_ENABLE=true
else
    echo "   ✗ 未找到 zip 扩展配置"
    NEED_ADD=true
fi

if [ "$ZIP_EXT_EXISTS" = false ]; then
    echo "\n=========================================="
    echo "需要先安装 zip 扩展"
    echo "=========================================="
    echo ""
    echo "安装方法："
    echo "1. 使用 PECL 安装："
    echo "   sudo pecl install zip"
    echo ""
    echo "2. 或重新编译 PHP（包含 zip 扩展）"
    echo ""
    echo "3. 或使用系统包管理器安装"
    echo ""
    exit 1
fi

if [ "$NEED_ENABLE" = true ]; then
    echo "\n5. 启用 zip 扩展..."
    # 备份原文件
    cp "$PHP_INI" "$PHP_INI.backup.$(date +%Y%m%d_%H%M%S)"
    
    # 取消注释
    sed -i '' 's/^;extension=zip$/extension=zip/' "$PHP_INI"
    
    if grep -q "^extension=zip" "$PHP_INI"; then
        echo "   ✓ zip 扩展已启用"
    else
        echo "   ✗ 启用失败，请手动编辑 $PHP_INI"
        exit 1
    fi
fi

if [ "$NEED_ADD" = true ]; then
    echo "\n5. 添加 zip 扩展配置..."
    # 备份原文件
    cp "$PHP_INI" "$PHP_INI.backup.$(date +%Y%m%d_%H%M%S)"
    
    # 在扩展部分添加
    echo "extension=zip" >> "$PHP_INI"
    echo "   ✓ 已添加 zip 扩展配置"
fi

echo "\n=========================================="
echo "配置完成！"
echo "=========================================="
echo ""
echo "请重启 PHP-FPM 或 Web 服务器使配置生效"
echo ""
echo "验证安装："
echo "  php 检查PHP扩展.php"
echo ""

