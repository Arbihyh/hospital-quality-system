# SVG 转 PNG 转换说明

由于系统没有安装 ImageMagick 或 GD 库，图表生成为 SVG 格式。需要转换为 PNG 才能插入 Word 文档。

## 方法1：使用 ImageMagick（推荐）

### macOS 安装：
```bash
brew install imagemagick
```

### 转换命令：
```bash
cd /Volumes/MACOS/work/zk/GY_homeQuality
convert -background white -density 300 storage/app/charts/病案内涵质控趋势图.svg storage/app/charts/病案内涵质控趋势图.png
```

## 方法2：使用浏览器截图

1. 在浏览器中打开 SVG 文件：
   ```bash
   open storage/app/charts/病案内涵质控趋势图.svg
   ```
   或者在浏览器地址栏输入：
   ```
   file:///Volumes/MACOS/work/zk/GY_homeQuality/storage/app/charts/病案内涵质控趋势图.svg
   ```

2. 使用浏览器截图功能（Chrome: Cmd+Shift+P -> Capture screenshot）
3. 保存为 PNG 格式

## 方法3：使用在线转换工具

1. 访问在线 SVG 转 PNG 工具（如 https://convertio.co/zh/svg-png/）
2. 上传 SVG 文件
3. 下载转换后的 PNG 文件
4. 保存到 `storage/app/charts/病案内涵质控趋势图.png`

## 方法4：使用 Python（如果已安装）

```python
from cairosvg import svg2png

svg2png(url='storage/app/charts/病案内涵质控趋势图.svg', 
        write_to='storage/app/charts/病案内涵质控趋势图.png',
        dpi=300)
```

安装依赖：
```bash
pip install cairosvg
```

## 转换后

转换完成后，重新运行测试脚本：
```bash
php test_word_report.php
```

图表会自动插入到 Word 文档中。

