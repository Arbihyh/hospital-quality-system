#!/bin/bash

# SVG 转 PNG 转换脚本

SVG_FILE="storage/app/charts/病案内涵质控趋势图.svg"
PNG_FILE="storage/app/charts/病案内涵质控趋势图.png"

echo "=========================================="
echo "SVG 转 PNG 转换工具"
echo "=========================================="
echo ""

# 检查 SVG 文件是否存在
if [ ! -f "$SVG_FILE" ]; then
    echo "错误: SVG 文件不存在: $SVG_FILE"
    exit 1
fi

echo "SVG 文件: $SVG_FILE"
echo "目标 PNG: $PNG_FILE"
echo ""

# 方法1: 尝试使用 ImageMagick
if command -v convert &> /dev/null; then
    echo "使用 ImageMagick 转换..."
    convert -background white -density 300 "$SVG_FILE" "$PNG_FILE"
    if [ -f "$PNG_FILE" ] && [ -s "$PNG_FILE" ]; then
        echo "✓ 转换成功: $PNG_FILE"
        echo "文件大小: $(ls -lh "$PNG_FILE" | awk '{print $5}')"
        exit 0
    fi
fi

# 方法2: 尝试使用 Inkscape
if command -v inkscape &> /dev/null; then
    echo "使用 Inkscape 转换..."
    inkscape --export-type=png --export-filename="$PNG_FILE" "$SVG_FILE"
    if [ -f "$PNG_FILE" ] && [ -s "$PNG_FILE" ]; then
        echo "✓ 转换成功: $PNG_FILE"
        echo "文件大小: $(ls -lh "$PNG_FILE" | awk '{print $5}')"
        exit 0
    fi
fi

# 方法3: 尝试使用 qlmanage (macOS)
if [[ "$OSTYPE" == "darwin"* ]]; then
    echo "尝试使用 macOS 工具..."
    # macOS 没有直接转换 SVG 的工具，提示用户
    echo "⚠ macOS 系统需要安装 ImageMagick 或使用浏览器截图"
fi

echo ""
echo "=========================================="
echo "转换失败"
echo "=========================================="
echo ""
echo "请使用以下方法之一："
echo ""
echo "1. 安装 ImageMagick:"
echo "   brew install imagemagick"
echo "   然后重新运行此脚本"
echo ""
echo "2. 使用浏览器截图:"
echo "   打开: storage/app/charts/查看图表.html"
echo "   截图保存为: $PNG_FILE"
echo ""
echo "3. 使用在线转换工具:"
echo "   访问: https://convertio.co/zh/svg-png/"
echo "   上传 SVG 文件并下载 PNG"
echo ""

