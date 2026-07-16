<?php

namespace App\Services;

use PhpOffice\PhpWord\TemplateProcessor;
use PhpOffice\PhpWord\Exception\Exception as PhpWordException;
use PhpOffice\PhpWord\Element\Chart as ChartElement;
use PhpOffice\PhpWord\Style\Chart as ChartStyle;
use PhpOffice\PhpWord\Settings;

/**
 * Word报告生成服务类
 * 用于填充Word模板并生成报告
 */
class WordReportService
{
    /**
     * 模板处理器实例
     * @var TemplateProcessor|null
     */
    private $templateProcessor = null;

    /**
     * 模板文件路径
     * @var string
     */
    private $templatePath = '';

    /**
     * 图表系列间距（overlap 值）
     * @var int|null
     */
    private $chartOverlap = null;

    /**
     * 图表柱状图宽度（gapWidth），将在 saveReport 时应用到图表 XML
     * @var int|null
     */
    private $chartGapWidth = null;

    /**
     * 是否添加趋势线，将在 saveReport 时应用到图表 XML
     * @var bool|null
     */
    private $chartAddTrendline = null;

    /**
     * 图表是否使用百分号标签映射
     * @var array
     */
    private $chartLabelFormatMap = [];

    /**
     * 构造函数
     * @param string $templatePath 模板文件路径
     * @throws \Exception
     */
    public function __construct($templatePath = '')
    {
        if (!empty($templatePath)) {
            $this->loadTemplate($templatePath);
        }
    }

    /**
     * 加载Word模板
     * @param string $templatePath 模板文件路径
     * @return $this
     * @throws \Exception
     */
    public function loadTemplate($templatePath)
    {
        // 检查 TemplateProcessor 类是否存在
        if (!class_exists('PhpOffice\PhpWord\TemplateProcessor')) {
            $errorMsg = "PhpWord TemplateProcessor 类不可用。";
            $errorMsg .= " 请确保：\n";
            $errorMsg .= "1. 已运行 composer install 安装依赖\n";
            $errorMsg .= "2. vendor 目录已正确上传到服务器\n";
            $errorMsg .= "3. PHP 扩展已安装：zip, xml, zlib\n";
            $errorMsg .= "4. autoload 文件已正确加载\n";
            $errorMsg .= "\n解决方法：\n";
            $errorMsg .= "在项目根目录运行：composer install --no-dev --optimize-autoloader";

            $this->logError('TemplateProcessor 类不可用', [
                'template_path' => $templatePath,
                'class_exists' => class_exists('PhpOffice\PhpWord\TemplateProcessor'),
                'vendor_exists' => file_exists(base_path('vendor/autoload.php')),
                'php_version' => PHP_VERSION,
                'extensions' => [
                    'zip' => extension_loaded('zip'),
                    'xml' => extension_loaded('xml'),
                    'zlib' => extension_loaded('zlib'),
                ]
            ]);

            throw new \Exception($errorMsg);
        }

        if (!file_exists($templatePath)) {
            throw new \Exception("模板文件不存在: {$templatePath}");
        }

        try {
            $this->templatePath = $templatePath;
            $this->templateProcessor = new TemplateProcessor($templatePath);
            return $this;
        } catch (PhpWordException $e) {
            $this->logError('加载Word模板失败', [
                'template_path' => $templatePath,
                'error' => $e->getMessage()
            ]);
            throw new \Exception("加载模板失败: " . $e->getMessage());
        } catch (\Exception $e) {
            $this->logError('加载Word模板失败（未知错误）', [
                'template_path' => $templatePath,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new \Exception("加载模板失败: " . $e->getMessage());
        }
    }

    /**
     * 替换模板变量
     * 支持单个变量替换和批量替换
     * @param array|string $key 变量名或变量数组
     * @param mixed $value 变量值（当$key为字符串时使用）
     * @return $this
     * @throws \Exception
     */
    public function replaceVariables($key, $value = null)
    {
        if ($this->templateProcessor === null) {
            throw new \Exception("请先加载模板文件");
        }

        try {
            if (is_array($key)) {
                // 批量替换
                foreach ($key as $varName => $varValue) {
                    $this->replaceVariable($varName, $varValue);
                }
            } else {
                // 单个替换
                $this->replaceVariable($key, $value);
            }
            return $this;
        } catch (\Exception $e) {
            $this->logError('替换模板变量失败', [
                'key' => is_array($key) ? 'array' : $key,
                'error' => $e->getMessage()
            ]);
            throw new \Exception("替换变量失败: " . $e->getMessage());
        }
    }

    /**
     * 替换单个变量
     * @param string $key 变量名（不需要花括号）
     * @param mixed $value 变量值
     * @return void
     */
    private function replaceVariable($key, $value)
    {
        // 移除可能存在的花括号
        $key = trim($key, '{}');

        // 将值转换为字符串
        $value = $this->formatValue($value);

        // 替换变量（PhpWord会自动处理花括号）
        $this->templateProcessor->setValue($key, $value);
    }

    /**
     * 格式化值
     * @param mixed $value 原始值
     * @return string 格式化后的字符串
     */
    private function formatValue($value)
    {
        if (is_null($value)) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '是' : '否';
        }

        if (is_array($value)) {
            // 数组按行拼接，便于在 Word 中显示为多行文本
            return implode("\n", $value);
        }

        return (string)$value;
    }

    /**
     * 替换表格行数据
     * @param string $tableName 表格变量名
     * @param array $rows 行数据数组
     * @return $this
     * @throws \Exception
     */
    public function replaceTableRows($tableName, array $rows)
    {
        if ($this->templateProcessor === null) {
            throw new \Exception("请先加载模板文件");
        }

        try {
            // 移除可能存在的花括号
            $tableName = trim($tableName, '{}');

            // 克隆行并填充数据
            $this->templateProcessor->cloneRow($tableName, count($rows));

            foreach ($rows as $index => $row) {
                $rowNumber = $index + 1;
                foreach ($row as $key => $value) {
                    $key = trim($key, '{}');
                    $this->templateProcessor->setValue("{$key}#{$rowNumber}", $this->formatValue($value));
                }
            }

            return $this;
        } catch (\Exception $e) {
            $this->logError('替换表格数据失败', [
                'table_name' => $tableName,
                'error' => $e->getMessage()
            ]);
            throw new \Exception("替换表格数据失败: " . $e->getMessage());
        }
    }

    /**
     * 替换图表占位符（使用 PhpWord 原生图表功能）
     * @param string $placeholder 图表占位符名称（如 'chartImage'，对应模板中的 ${chartImage}）
     * @param array $chartData 图表数据，格式：['月份' => ['total' => 总数, 'defect' => 缺陷数], ...]
     * @param array $options 可选参数：width（宽度，单位 EMU，默认 10000000）、height（高度，默认 6000000）、title（标题）
     * @return $this
     * @throws \Exception
     */
    public function replaceChart($placeholder, array $chartData, array $options = [])
    {
        if ($this->templateProcessor === null) {
            throw new \Exception("请先加载模板文件");
        }

        try {
            // 移除可能存在的花括号
            $varName = trim($placeholder, '${}');

            // 准备数据
            $months = array_keys($chartData);
            $totals = array_column($chartData, 'total');
            $defects = array_column($chartData, 'defect');

            // 创建图表样式
            // EMU 单位换算：1 cm = 360000 EMU, 1 inch = 914400 EMU
            // 默认尺寸：与模板中实例图表一致（cx="5256530" 约14.6cm, cy="2988310" 约8.3cm）
            $defaultWidth = 5256530;   // 约 14.6cm - 与模板实例图表一致
            $defaultHeight = 2988310;   // 约 8.3cm - 与模板实例图表一致

            $chartWidth = $options['width'] ?? $defaultWidth;
            $chartHeight = $options['height'] ?? $defaultHeight;

            $chartStyle = new ChartStyle();
            $chartStyle->setWidth($chartWidth);
            $chartStyle->setHeight($chartHeight);
            $chartStyle->setTitle($options['title'] ?? '病案内涵质控趋势图');
            $chartStyle->setShowLegend(true);
            $chartStyle->setLegendPosition('r'); // 右侧显示图例

            // 记录设置的尺寸（用于调试）
            $this->logInfo('设置图表尺寸', [
                'width_emu' => $chartWidth,
                'height_emu' => $chartHeight,
                'width_cm' => round($chartWidth / 360000, 2),
                'height_cm' => round($chartHeight / 360000, 2)
            ]);

            // 创建组合图表：使用 column 类型（柱状图）
            // 注意：PhpWord 不支持真正的组合图表（柱状图+折线图），但可以使用 column 类型显示多个系列
            // ChartElement 构造函数：如果 style 是对象，setNewStyle 会忽略它并创建新对象
            // 所以先创建图表，然后手动设置 style
            $chart = new ChartElement('column', $months, $totals, null, '病历总数');

            // 手动设置图表样式（确保尺寸正确）
            $actualStyle = $chart->getStyle();
            $actualStyle->setWidth($chartWidth);
            $actualStyle->setHeight($chartHeight);
            $actualStyle->setTitle($options['title'] ?? '病案内涵质控趋势图');
            $actualStyle->setShowLegend(true);
            $actualStyle->setLegendPosition('r'); // 右侧显示图例

            // 设置图表颜色
            // 注意：对于 column 类型（clustered），Office 会自动为每个系列分配不同颜色
            // 不设置 colors，让 Office 使用默认主题颜色，确保每个系列有不同颜色
            // 如果需要特定颜色，可以在 Word 中手动调整
            // 或者设置颜色数组，但 PhpWord 的颜色设置是应用到数据点的，不是系列的
            // 暂时不设置 colors，让 Office 自动分配，确保两个系列颜色不同

            // 设置数据标签选项：显示数值在柱状图上方
            $actualStyle->setDataLabelOptions([
                'showVal' => true,        // 显示数值
                'showCatName' => false,   // 不显示类别名称
                'showLegendKey' => false, // 不显示图例键
                'showSerName' => false,   // 不显示系列名称
                'showPercent' => false,   // 不显示百分比
                'showLeaderLines' => false, // 不显示引导线
            ]);

            // 根据参数设置是否显示 Y 轴网格线（横线）
            $showGridY = $options['showGridY'] ?? false;
            $actualStyle->setShowGridY($showGridY);
            $actualStyle->setShowAxisLabels(true); // 显示坐标轴标签

            // 设置图表边距（通过调整图表尺寸，在外部留出边距）
            // 注意：PhpWord 的 ChartStyle 不直接支持边距设置
            // 但可以通过稍微减小图表尺寸来实现边距效果
            // 或者在实际应用中，可以在 Word 模板中为图表占位符周围添加段落间距

            // 验证尺寸设置
            $actualWidth = $actualStyle->getWidth();
            $actualHeight = $actualStyle->getHeight();
            $this->logInfo('图表尺寸验证', [
                '设置宽度' => $chartWidth,
                '实际宽度' => $actualWidth,
                '设置高度' => $chartHeight,
                '实际高度' => $actualHeight,
                '宽度匹配' => $actualWidth == $chartWidth,
                '高度匹配' => $actualHeight == $chartHeight
            ]);

            // 添加第二个系列（缺陷病历）
            $chart->addSeries($months, $defects, '缺陷病历');

            // 存储图表设置（将在 saveReport 时应用）
            // overlap 值范围：-100 到 100，负值表示有间距
            // 默认 -40，表示两个系列之间有 40% 的间距
            $this->chartOverlap = $options['overlap'] ?? -40;
            // gapWidth 值范围：0-500，默认约 150-250，值越小柱子越窄
            $this->chartGapWidth = $options['gapWidth'] ?? 30;
            // 是否添加趋势线
            $this->chartAddTrendline = $options['addTrendline'] ?? true;
            $this->chartLabelFormatMap[] = !empty($options['labelSuffixPercent']);

            // 当前生成的是柱状图，包含两个系列（病历总数、缺陷病历）
            // 如需实现组合图表（柱状图+折线图）效果，请在 Word 中：
            // 1. 选中图表
            // 2. 右键点击"缺陷病历"系列 → "更改系列图表类型"
            // 3. 选择"折线图"，并设置为虚线样式
            // 4. 这样"病历总数"保持柱状图，"缺陷病历"变为折线图

            // 替换图表占位符
            // setChart 内部会调用 ensureMacroCompleted，所以可以直接传变量名
            // 但为了确保格式正确，我们还是使用完整格式
            $placeholderFull = '${' . $varName . '}';
            $this->templateProcessor->setChart($placeholderFull, $chart);

            $this->logInfo('图表替换成功（使用 PhpWord 原生图表功能）', [
                'placeholder' => $placeholder,
                'chart_type' => '柱状图（包含病历总数和缺陷病历两个系列）',
                'chart_width_cm' => round($chartWidth / 360000, 2),
                'chart_height_cm' => round($chartHeight / 360000, 2),
                'chart_colors_auto' => true,
                'show_data_labels' => true,
                'show_grid_y' => $showGridY,
                'show_axis_labels' => true,
                'overlap' => $this->chartOverlap,
            ]);

            return $this;
        } catch (\Exception $e) {
            $this->logError('替换图表失败', [
                'placeholder' => $placeholder,
                'error' => $e->getMessage()
            ]);
            throw new \Exception("替换图表失败: " . $e->getMessage());
        }
    }

    /**
     * 替换图表占位符（三个系列：甲、乙、丙级病案）
     * @param string $placeholder 图表占位符名称（如 'jjtb'）
     * @param array $chartData 图表数据，格式：['月份' => ['jia' => 甲级数, 'yi' => 乙级数, 'bing' => 丙级数], ...]
     * @param array $options 可选参数：width、height、title、overlap、gapWidth、addTrendline、showGridY
     * @return $this
     * @throws \Exception
     */
    public function replaceChartThreeSeries($placeholder, array $chartData, array $options = [])
    {
        if ($this->templateProcessor === null) {
            throw new \Exception("请先加载模板文件");
        }

        try {
            // 移除可能存在的花括号
            $varName = trim($placeholder, '${}');

            // 准备数据
            $months = array_keys($chartData);
            $jiaData = array_column($chartData, 'jia');
            $yiData = array_column($chartData, 'yi');
            $bingData = array_column($chartData, 'bing');

            // 图表尺寸
            $defaultWidth = 5256530;   // 约 14.6cm
            $defaultHeight = 2988310;   // 约 8.3cm

            $chartWidth = $options['width'] ?? $defaultWidth;
            $chartHeight = $options['height'] ?? $defaultHeight;

            $this->logInfo('设置图表尺寸', [
                'width_emu' => $chartWidth,
                'height_emu' => $chartHeight,
                'width_cm' => round($chartWidth / 360000, 2),
                'height_cm' => round($chartHeight / 360000, 2)
            ]);

            // 创建图表（柱状图）
            $chart = new ChartElement('column', $months, $jiaData, null, '甲');

            // 设置图表样式
            $actualStyle = $chart->getStyle();
            $actualStyle->setWidth($chartWidth);
            $actualStyle->setHeight($chartHeight);
            $actualStyle->setTitle($options['title'] ?? '');
            $actualStyle->setShowLegend(true);
            $actualStyle->setLegendPosition('r'); // 右侧显示图例

            // 设置数据标签选项
            $actualStyle->setDataLabelOptions([
                'showVal' => true,
                'showCatName' => false,
                'showLegendKey' => false,
                'showSerName' => false,
                'showPercent' => false,
                'showLeaderLines' => false,
            ]);

            // 设置网格线
            $showGridY = $options['showGridY'] ?? false;
            $actualStyle->setShowGridY($showGridY);
            $actualStyle->setShowAxisLabels(true);

            // 验证尺寸
            $actualWidth = $actualStyle->getWidth();
            $actualHeight = $actualStyle->getHeight();
            $this->logInfo('图表尺寸验证', [
                '设置宽度' => $chartWidth,
                '实际宽度' => $actualWidth,
                '设置高度' => $chartHeight,
                '实际高度' => $actualHeight,
                '宽度匹配' => $actualWidth == $chartWidth,
                '高度匹配' => $actualHeight == $chartHeight
            ]);

            // 添加第二个系列（乙级）
            $chart->addSeries($months, $yiData, '乙');

            // 添加第三个系列（丙级）
            $chart->addSeries($months, $bingData, '丙');

            // 存储图表设置
            $this->chartOverlap = $options['overlap'] ?? -40;
            $this->chartGapWidth = $options['gapWidth'] ?? 30;
            $this->chartAddTrendline = $options['addTrendline'] ?? true;
            $this->chartLabelFormatMap[] = !empty($options['labelSuffixPercent']);

            // 替换图表占位符
            $placeholderFull = '${' . $varName . '}';
            $this->templateProcessor->setChart($placeholderFull, $chart);

            $this->logInfo('图表替换成功（三系列图表）', [
                'placeholder' => $placeholder,
                'chart_type' => '柱状图（包含甲、乙、丙三个系列）',
                'chart_width_cm' => round($chartWidth / 360000, 2),
                'chart_height_cm' => round($chartHeight / 360000, 2),
                'show_data_labels' => true,
                'show_grid_y' => $showGridY,
                'overlap' => $this->chartOverlap,
            ]);

            return $this;
        } catch (\Exception $e) {
            $this->logError('替换三系列图表失败', [
                'placeholder' => $placeholder,
                'error' => $e->getMessage()
            ]);
            throw new \Exception("替换三系列图表失败: " . $e->getMessage());
        }
    }

    /**
     * 替换图表占位符（四个系列：时效性指标）
     * @param string $placeholder 图表占位符名称（如 'sxzbtb'）
     * @param array $chartData 图表数据，格式：['月份' => ['ryj' => 入院记录, 'ssj' => 手术记录, 'cyj' => 出院记录, 'basy' => 病案首页], ...]
     * @param array $options 可选参数：width、height、title、overlap、gapWidth、addTrendline、showGridY
     * @return $this
     * @throws \Exception
     */
    public function replaceChartFourSeries($placeholder, array $chartData, array $options = [])
    {
        if ($this->templateProcessor === null) {
            throw new \Exception("请先加载模板文件");
        }

        try {
            // 移除可能存在的花括号
            $varName = trim($placeholder, '${}');

            // 准备数据
            $months = array_keys($chartData);
            $ryjData = array_column($chartData, 'ryj');
            $ssjData = array_column($chartData, 'ssj');
            $cyjData = array_column($chartData, 'cyj');
            $basyData = array_column($chartData, 'basy');

            // 图表尺寸
            $defaultWidth = 5256530;   // 约 14.6cm
            $defaultHeight = 2988310;   // 约 8.3cm

            $chartWidth = $options['width'] ?? $defaultWidth;
            $chartHeight = $options['height'] ?? $defaultHeight;

            $this->logInfo('设置图表尺寸', [
                'width_emu' => $chartWidth,
                'height_emu' => $chartHeight,
                'width_cm' => round($chartWidth / 360000, 2),
                'height_cm' => round($chartHeight / 360000, 2)
            ]);

            // 创建图表（柱状图）- 第一个系列：入院记录24小时内完成率
            $chart = new ChartElement('column', $months, $ryjData, null, '入院记录24小时内完成率');

            // 设置图表样式
            $actualStyle = $chart->getStyle();
            $actualStyle->setWidth($chartWidth);
            $actualStyle->setHeight($chartHeight);
            $actualStyle->setTitle($options['title'] ?? '');
            $actualStyle->setShowLegend(true);
            $actualStyle->setLegendPosition('r'); // 右侧显示图例

            // 设置数据标签选项
            $actualStyle->setDataLabelOptions([
                'showVal' => true,
                'showCatName' => false,
                'showLegendKey' => false,
                'showSerName' => false,
                'showPercent' => false,
                'showLeaderLines' => false,
            ]);

            // 设置网格线
            $showGridY = $options['showGridY'] ?? false;
            $actualStyle->setShowGridY($showGridY);
            $actualStyle->setShowAxisLabels(true);

            // 验证尺寸
            $actualWidth = $actualStyle->getWidth();
            $actualHeight = $actualStyle->getHeight();
            $this->logInfo('图表尺寸验证', [
                '设置宽度' => $chartWidth,
                '实际宽度' => $actualWidth,
                '设置高度' => $chartHeight,
                '实际高度' => $actualHeight,
                '宽度匹配' => $actualWidth == $chartWidth,
                '高度匹配' => $actualHeight == $chartHeight
            ]);

            // 添加第二个系列（手术记录）
            $chart->addSeries($months, $ssjData, '手术记录24小时内完成率');

            // 添加第三个系列（出院记录）
            $chart->addSeries($months, $cyjData, '出院记录24小时内完成率');

            // 添加第四个系列（病案首页）
            $chart->addSeries($months, $basyData, '病案首页24小时内完成率');

            // 存储图表设置
            $this->chartOverlap = $options['overlap'] ?? -40;
            $this->chartGapWidth = $options['gapWidth'] ?? 30;
            $this->chartAddTrendline = $options['addTrendline'] ?? true;
            $this->chartLabelFormatMap[] = !empty($options['labelSuffixPercent']);

            // 替换图表占位符
            $placeholderFull = '${' . $varName . '}';
            $this->templateProcessor->setChart($placeholderFull, $chart);

            $this->logInfo('图表替换成功（四系列图表）', [
                'placeholder' => $placeholder,
                'chart_type' => '柱状图（包含入院、手术、出院、病案首页四个系列）',
                'chart_width_cm' => round($chartWidth / 360000, 2),
                'chart_height_cm' => round($chartHeight / 360000, 2),
                'show_data_labels' => true,
                'show_grid_y' => $showGridY,
                'overlap' => $this->chartOverlap,
            ]);

            return $this;
        } catch (\Exception $e) {
            $this->logError('替换四系列图表失败', [
                'placeholder' => $placeholder,
                'error' => $e->getMessage()
            ]);
            throw new \Exception("替换四系列图表失败: " . $e->getMessage());
        }
    }

    /**
     * 替换图表占位符（按指标分组的对比图表）
     * 适用于：多个指标在两个时期的对比，如时效性指标
     * @param string $placeholder 图表占位符名称
     * @param array $chartData 图表数据，格式：['指标名' => ['period1' => 值1, 'period2' => 值2], ...]
     * @param array $options 可选参数：width、height、title、overlap、gapWidth、addTrendline、showGridY、period1Name、period2Name
     * @return $this
     * @throws \Exception
     */
    public function replaceChartByIndicator($placeholder, array $chartData, array $options = [])
    {
        if ($this->templateProcessor === null) {
            throw new \Exception("请先加载模板文件");
        }

        try {
            // 移除可能存在的花括号
            $varName = trim($placeholder, '${}');

            // 准备数据
            $indicators = array_keys($chartData);  // 指标名称（X轴）
            $period1Data = array_column($chartData, 'period1');  // 第一个时期的数据
            $period2Data = array_column($chartData, 'period2');  // 第二个时期的数据

            // 时期名称
            $period1Name = $options['period1Name'] ?? '2025年10月达标率';
            $period2Name = $options['period2Name'] ?? '2025年11月达标率';

            // 图表尺寸
            $defaultWidth = 5256530;   // 约 14.6cm
            $defaultHeight = 2988310;   // 约 8.3cm

            $chartWidth = $options['width'] ?? $defaultWidth;
            $chartHeight = $options['height'] ?? $defaultHeight;

            $this->logInfo('设置图表尺寸', [
                'width_emu' => $chartWidth,
                'height_emu' => $chartHeight,
                'width_cm' => round($chartWidth / 360000, 2),
                'height_cm' => round($chartHeight / 360000, 2)
            ]);

            // 创建图表（柱状图）- 第一个系列：第一个时期
            $chart = new ChartElement('column', $indicators, $period1Data, null, $period1Name);

            // 设置图表样式
            $actualStyle = $chart->getStyle();
            $actualStyle->setWidth($chartWidth);
            $actualStyle->setHeight($chartHeight);
            $actualStyle->setTitle($options['title'] ?? '');
            $actualStyle->setShowLegend(true);
            $actualStyle->setLegendPosition('r'); // 右侧显示图例

            // 设置数据标签选项
            $actualStyle->setDataLabelOptions([
                'showVal' => true,
                'showCatName' => false,
                'showLegendKey' => false,
                'showSerName' => false,
                'showPercent' => false,
                'showLeaderLines' => false,
            ]);

            // 设置网格线
            $showGridY = $options['showGridY'] ?? false;
            $actualStyle->setShowGridY($showGridY);
            $actualStyle->setShowAxisLabels(true);

            // 验证尺寸
            $actualWidth = $actualStyle->getWidth();
            $actualHeight = $actualStyle->getHeight();
            $this->logInfo('图表尺寸验证', [
                '设置宽度' => $chartWidth,
                '实际宽度' => $actualWidth,
                '设置高度' => $chartHeight,
                '实际高度' => $actualHeight,
                '宽度匹配' => $actualWidth == $chartWidth,
                '高度匹配' => $actualHeight == $chartHeight
            ]);

            // 添加第二个系列（第二个时期）
            $chart->addSeries($indicators, $period2Data, $period2Name);

            // 存储图表设置
            $this->chartOverlap = $options['overlap'] ?? -40;
            $this->chartGapWidth = $options['gapWidth'] ?? 30;
            $this->chartAddTrendline = $options['addTrendline'] ?? true;
            $this->chartLabelFormatMap[] = !empty($options['labelSuffixPercent']);

            // 替换图表占位符
            $placeholderFull = '${' . $varName . '}';
            $this->templateProcessor->setChart($placeholderFull, $chart);

            $this->logInfo('图表替换成功（按指标分组）', [
                'placeholder' => $placeholder,
                'chart_type' => '柱状图（按指标分组，两个时期对比）',
                'indicators_count' => count($indicators),
                'chart_width_cm' => round($chartWidth / 360000, 2),
                'chart_height_cm' => round($chartHeight / 360000, 2),
                'show_data_labels' => true,
                'show_grid_y' => $showGridY,
                'overlap' => $this->chartOverlap,
            ]);

            return $this;
        } catch (\Exception $e) {
            $this->logError('替换按指标分组图表失败', [
                'placeholder' => $placeholder,
                'error' => $e->getMessage()
            ]);
            throw new \Exception("替换按指标分组图表失败: " . $e->getMessage());
        }
    }

    /**
     * 替换图表占位符（单系列柱状图）
     * 适用于：单一维度的数据展示，如各科室的问题数量
     * @param string $placeholder 图表占位符名称
     * @param array $chartData 图表数据，格式：['类别名' => 数值, ...]
     * @param array $options 可选参数：width、height、title、overlap、gapWidth、addTrendline、showGridY、seriesName
     * @return $this
     * @throws \Exception
     */
    public function replaceChartSingleSeries($placeholder, array $chartData, array $options = [])
    {
        if ($this->templateProcessor === null) {
            throw new \Exception("请先加载模板文件");
        }

        try {
            // 移除可能存在的花括号
            $varName = trim($placeholder, '${}');

            // 准备数据
            $categories = array_keys($chartData);  // 类别名称（X轴）
            $values = array_values($chartData);     // 数值（Y轴）

            // 系列名称
            $seriesName = $options['seriesName'] ?? '数据';

            // 图表尺寸
            $defaultWidth = 5256530;   // 约 14.6cm
            $defaultHeight = 2988310;   // 约 8.3cm

            $chartWidth = $options['width'] ?? $defaultWidth;
            $chartHeight = $options['height'] ?? $defaultHeight;

            $this->logInfo('设置图表尺寸', [
                'width_emu' => $chartWidth,
                'height_emu' => $chartHeight,
                'width_cm' => round($chartWidth / 360000, 2),
                'height_cm' => round($chartHeight / 360000, 2)
            ]);

            // 创建图表（柱状图）- 单系列
            $chart = new ChartElement('column', $categories, $values, null, $seriesName);

            // 设置图表样式
            $actualStyle = $chart->getStyle();
            $actualStyle->setWidth($chartWidth);
            $actualStyle->setHeight($chartHeight);
            $actualStyle->setTitle($options['title'] ?? '');
            $actualStyle->setShowLegend(true);
            $actualStyle->setLegendPosition('r'); // 右侧显示图例

            // 设置数据标签选项
            $actualStyle->setDataLabelOptions([
                'showVal' => true,
                'showCatName' => false,
                'showLegendKey' => false,
                'showSerName' => false,
                'showPercent' => false,
                'showLeaderLines' => false,
            ]);

            // 设置网格线
            $showGridY = $options['showGridY'] ?? false;
            $actualStyle->setShowGridY($showGridY);
            $actualStyle->setShowAxisLabels(true);

            // 验证尺寸
            $actualWidth = $actualStyle->getWidth();
            $actualHeight = $actualStyle->getHeight();
            $this->logInfo('图表尺寸验证', [
                '设置宽度' => $chartWidth,
                '实际宽度' => $actualWidth,
                '设置高度' => $chartHeight,
                '实际高度' => $actualHeight,
                '宽度匹配' => $actualWidth == $chartWidth,
                '高度匹配' => $actualHeight == $chartHeight
            ]);

            // 存储图表设置
            $this->chartOverlap = $options['overlap'] ?? -40;
            $this->chartGapWidth = $options['gapWidth'] ?? 30;
            $this->chartAddTrendline = $options['addTrendline'] ?? false;
            $this->chartLabelFormatMap[] = !empty($options['labelSuffixPercent']);

            // 替换图表占位符
            $placeholderFull = '${' . $varName . '}';
            $this->templateProcessor->setChart($placeholderFull, $chart);

            $this->logInfo('图表替换成功（单系列柱状图）', [
                'placeholder' => $placeholder,
                'chart_type' => '柱状图（单系列）',
                'categories_count' => count($categories),
                'chart_width_cm' => round($chartWidth / 360000, 2),
                'chart_height_cm' => round($chartHeight / 360000, 2),
                'show_data_labels' => true,
                'show_grid_y' => $showGridY,
                'series_name' => $seriesName,
            ]);

            return $this;
        } catch (\Exception $e) {
            $this->logError('替换单系列图表失败', [
                'placeholder' => $placeholder,
                'error' => $e->getMessage()
            ]);
            throw new \Exception("替换单系列图表失败: " . $e->getMessage());
        }
    }

    /**
     * 替换图片占位符
     * @param string $placeholder 图片占位符名称（如 'chartImage'，对应模板中的 ${chartImage} 或 ${chartImage:width:height}）
     * @param string $imagePath 图片文件路径
     * @param array $options 可选参数：width（宽度，如 '500px' 或 '15cm'）、height（高度）
     * @return $this
     * @throws \Exception
     */
    public function replaceImage($placeholder, $imagePath, array $options = [])
    {
        if ($this->templateProcessor === null) {
            throw new \Exception("请先加载模板文件");
        }

        if (!file_exists($imagePath)) {
            throw new \Exception("图片文件不存在: {$imagePath}");
        }

        try {
            // 移除可能存在的花括号，只保留变量名
            $varName = trim($placeholder, '${}');

            // 构建图片替换参数
            $imageData = ['path' => $imagePath];

            // 如果指定了尺寸，添加到 imageData 中
            if (isset($options['width'])) {
                $imageData['width'] = $options['width'];
            }
            if (isset($options['height'])) {
                $imageData['height'] = $options['height'];
            }

            // setImageValue 的第一个参数可以是变量名（不带 ${}），也可以是完整占位符
            // 它会自动查找匹配的变量（包括带参数的变体，如 ${varName:width:height}）
            $this->templateProcessor->setImageValue($varName, $imageData);

            return $this;
        } catch (\Exception $e) {
            $this->logError('替换图片失败', [
                'placeholder' => $placeholder,
                'image_path' => $imagePath,
                'error' => $e->getMessage()
            ]);
            throw new \Exception("替换图片失败: " . $e->getMessage());
        }
    }

    /**
     * 生成图表图片（柱状图+折线图）
     * @param array $chartData 图表数据，格式：['月份' => ['total' => 总数, 'defect' => 缺陷数], ...]
     * @param string $outputPath 输出图片路径
     * @return string 生成的图片路径
     * @throws \Exception
     */
    public function generateChartImage(array $chartData, $outputPath = '')
    {
        // 如果没有指定输出路径，使用默认路径
        if (empty($outputPath)) {
            $storagePath = $this->getStoragePath();
            $outputPath = $storagePath . '/charts/病案内涵质控趋势图.png';
        }

        // 确保目录存在
        $chartDir = dirname($outputPath);
        if (!is_dir($chartDir)) {
            mkdir($chartDir, 0755, true);
        }

        // 生成 SVG 图表
        $months = array_keys($chartData);
        $totals = array_column($chartData, 'total');
        $defects = array_column($chartData, 'defect');

        $width = 800;
        $height = 500;
        $marginLeft = 80;
        $marginBottom = 60;
        $marginTop = 60;
        $marginRight = 100;
        $chartWidth = $width - $marginLeft - $marginRight;
        $chartHeight = $height - $marginTop - $marginBottom;

        $maxValue = max(max($totals), max($defects)) * 1.1;

        // 创建 SVG
        $svg = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $svg .= '<svg width="' . $width . '" height="' . $height . '" xmlns="http://www.w3.org/2000/svg">' . "\n";
        $svg .= '<rect width="' . $width . '" height="' . $height . '" fill="#FFFFFF"/>' . "\n";
        $svg .= '<text x="' . ($width / 2) . '" y="30" text-anchor="middle" font-size="16" font-weight="bold" fill="#000000">病案内涵质控趋势图</text>' . "\n";

        // 坐标轴
        $svg .= '<line x1="' . $marginLeft . '" y1="' . $marginTop . '" x2="' . $marginLeft . '" y2="' . ($height - $marginBottom) . '" stroke="#000000" stroke-width="2"/>' . "\n";
        $svg .= '<line x1="' . $marginLeft . '" y1="' . ($height - $marginBottom) . '" x2="' . ($width - $marginRight) . '" y2="' . ($height - $marginBottom) . '" stroke="#000000" stroke-width="2"/>' . "\n";

        // 绘制柱状图和折线图
        $barWidth = ($chartWidth / count($months)) * 0.3;
        $barSpacing = ($chartWidth / count($months)) * 0.7;
        $x = $marginLeft + $barSpacing / 2;

        $linePoints = [];

        foreach ($months as $index => $month) {
            $total = $totals[$index];
            $defect = $defects[$index];

            $barHeight1 = ($total / $maxValue) * $chartHeight;
            $barHeight2 = ($defect / $maxValue) * $chartHeight;

            // 病历总数柱状图（蓝色）
            $svg .= '<rect x="' . $x . '" y="' . ($height - $marginBottom - $barHeight1) . '" width="' . $barWidth . '" height="' . $barHeight1 . '" fill="#4F81BD" opacity="0.8"/>' . "\n";

            // 缺陷病历柱状图（橙色）
            $svg .= '<rect x="' . ($x + $barWidth + 5) . '" y="' . ($height - $marginBottom - $barHeight2) . '" width="' . $barWidth . '" height="' . $barHeight2 . '" fill="#FFC000" opacity="0.8"/>' . "\n";

            // 数值标签
            $svg .= '<text x="' . ($x + $barWidth / 2) . '" y="' . ($height - $marginBottom - $barHeight1 - 5) . '" text-anchor="middle" font-size="10" fill="#000000">' . number_format($total) . '</text>' . "\n";
            $svg .= '<text x="' . ($x + $barWidth * 1.5 + 5) . '" y="' . ($height - $marginBottom - $barHeight2 - 5) . '" text-anchor="middle" font-size="10" fill="#000000">' . number_format($defect) . '</text>' . "\n";

            // 月份标签
            $svg .= '<text x="' . ($x + $barWidth + 2.5) . '" y="' . ($height - $marginBottom + 20) . '" text-anchor="middle" font-size="11" fill="#000000">' . htmlspecialchars($month) . '</text>' . "\n";

            // 折线图点
            $linePoints[] = [
                'x' => $x + $barWidth * 1.5 + 5,
                'y' => $height - $marginBottom - $barHeight2
            ];

            $x += $barSpacing + $barWidth * 2 + 5;
        }

        // 绘制折线图
        if (count($linePoints) > 1) {
            $path = 'M ' . $linePoints[0]['x'] . ' ' . $linePoints[0]['y'];
            for ($i = 1; $i < count($linePoints); $i++) {
                $path .= ' L ' . $linePoints[$i]['x'] . ' ' . $linePoints[$i]['y'];
            }
            $svg .= '<path d="' . $path . '" stroke="#FFC000" stroke-width="2" fill="none" stroke-dasharray="5,5" opacity="0.8"/>' . "\n";
            foreach ($linePoints as $point) {
                $svg .= '<circle cx="' . $point['x'] . '" cy="' . $point['y'] . '" r="4" fill="#FFC000"/>' . "\n";
            }
        }

        // 图例
        $legendY = $marginTop + 20;
        $svg .= '<rect x="' . ($width - 180) . '" y="' . $legendY . '" width="15" height="15" fill="#4F81BD" opacity="0.8"/>' . "\n";
        $svg .= '<text x="' . ($width - 160) . '" y="' . ($legendY + 12) . '" font-size="11" fill="#000000">病历总数</text>' . "\n";
        $svg .= '<rect x="' . ($width - 180) . '" y="' . ($legendY + 20) . '" width="15" height="15" fill="#FFC000" opacity="0.8"/>' . "\n";
        $svg .= '<text x="' . ($width - 160) . '" y="' . ($legendY + 32) . '" font-size="11" fill="#000000">缺陷病历</text>' . "\n";
        $svg .= '<line x1="' . ($width - 180) . '" y1="' . ($legendY + 45) . '" x2="' . ($width - 165) . '" y2="' . ($legendY + 45) . '" stroke="#FFC000" stroke-width="2" stroke-dasharray="5,5" opacity="0.8"/>' . "\n";
        $svg .= '<text x="' . ($width - 160) . '" y="' . ($legendY + 48) . '" font-size="11" fill="#000000">线性(缺陷病历)</text>' . "\n";

        $svg .= '</svg>';

        // 保存 SVG
        $svgPath = str_replace('.png', '.svg', $outputPath);
        file_put_contents($svgPath, $svg);

        // 尝试转换为 PNG
        $converted = false;
        if (function_exists('exec')) {
            // 尝试使用 ImageMagick
            $cmd = "convert -background white -density 300 \"$svgPath\" \"$outputPath\" 2>&1";
            exec($cmd, $output, $returnCode);
            if ($returnCode === 0 && file_exists($outputPath) && filesize($outputPath) > 1000) {
                $converted = true;
                unlink($svgPath);
            }
        }

        if (!$converted) {
            // 如果无法转换，返回 SVG 路径
            $this->logInfo('图表已生成 SVG 格式', ['svg_path' => $svgPath]);
            return $svgPath;
        }

        return $outputPath;
    }

    /**
     * 保存生成的报告
     * @param string $outputPath 输出文件路径
     * @param int|null $chartOverlap 图表系列间距（-100 到 100，负值表示有间距，null 表示使用类属性中的值）
     * @return string 保存的文件路径
     * @throws \Exception
     */
    public function saveReport($outputPath, $chartOverlap = null)
    {
        if ($this->templateProcessor === null) {
            throw new \Exception("请先加载模板文件");
        }

        try {
            // 确保输出目录存在且有写入权限
            $outputDir = dirname($outputPath);
            if (!is_dir($outputDir)) {
                if (!mkdir($outputDir, 0777, true)) {
                    throw new \Exception("无法创建目录: {$outputDir}");
                }
            }

            // 检查目录是否可写
            if (!is_writable($outputDir)) {
                // 尝试修改权限
                @chmod($outputDir, 0777);
                if (!is_writable($outputDir)) {
                    throw new \Exception("目录不可写: {$outputDir}，请检查权限");
                }
            }

            // 如果目标文件已存在，先删除（避免权限问题）
            if (file_exists($outputPath)) {
                @unlink($outputPath);
            }

            // 检查 PHP 临时目录权限（PhpWord 可能会使用临时目录）
            $systemTempDir = sys_get_temp_dir();
            $this->logInfo('检查临时目录', [
                'system_temp_dir' => $systemTempDir,
                'system_temp_dir_writable' => is_writable($systemTempDir),
                'output_dir' => $outputDir,
                'output_dir_writable' => is_writable($outputDir)
            ]);

            // 设置 PhpWord 的临时目录为输出目录（避免权限问题）
            // PhpWord 在保存时会使用临时目录，如果系统临时目录不可写，使用输出目录
            if (!is_writable($systemTempDir)) {
                // 设置 PhpWord 的临时目录
                Settings::setTempDir($outputDir);
                $this->logInfo('设置 PhpWord 临时目录为输出目录', [
                    'temp_dir' => $outputDir
                ]);
            } else {
                // 即使系统临时目录可写，也使用输出目录作为临时目录（更安全）
                Settings::setTempDir($outputDir);
            }

            $this->templateProcessor->saveAs($outputPath);

            // 修改图表 XML（包括间距、网格线、趋势线等）
            // 优先使用传入的参数，如果没有则使用类属性中存储的值
            $finalOverlap = $chartOverlap !== null ? $chartOverlap : $this->chartOverlap;
            $finalGapWidth = $this->chartGapWidth;
            $finalAddTrendline = $this->chartAddTrendline;

            // 始终调用此方法来设置图表样式（包括网格线）
            $this->setChartOverlapAndTrendline($outputPath, $finalOverlap, $finalGapWidth, $finalAddTrendline, $this->chartLabelFormatMap);

            $this->logInfo('Word报告生成成功', [
                'output_path' => $outputPath,
                'chart_overlap' => $chartOverlap
            ]);

            return $outputPath;
        } catch (\Exception $e) {
            $this->logError('保存Word报告失败', [
                'output_path' => $outputPath,
                'error' => $e->getMessage()
            ]);
            throw new \Exception("保存报告失败: " . $e->getMessage());
        }
    }

    /**
     * 设置图表系列间距、柱状图宽度、趋势线和标签格式（通过修改 docx 文件中的图表 XML）
     * @param string $docxPath docx 文件路径
     * @param int|null $overlap 间距值（-100 到 100，负值表示有间距）
     * @param int|null $gapWidth 柱状图宽度（0-500，值越小柱子越窄）
     * @param bool|null $addTrendline 是否添加趋势线
     * @param array $chartLabelFormatMap 图表标签格式映射，true 表示需要追加 % 显示
     * @return void
     * @throws \Exception
     */
    private function setChartOverlapAndTrendline($docxPath, $overlap = null, $gapWidth = null, $addTrendline = null, array $chartLabelFormatMap = [])
    {
        // 限制 overlap 值在有效范围内
        if ($overlap !== null) {
            $overlap = max(-100, min(100, (int)$overlap));
        }
        // 限制 gapWidth 值在有效范围内
        if ($gapWidth !== null) {
            $gapWidth = max(0, min(500, (int)$gapWidth));
        }

        try {
            $zip = new \ZipArchive();
            if ($zip->open($docxPath) !== true) {
                throw new \Exception("无法打开 docx 文件");
            }

            // 查找所有图表文件
            $chartFiles = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $filename = $zip->getNameIndex($i);
                if (preg_match('/^word\/charts\/chart\d+\.xml$/', $filename)) {
                    $chartFiles[] = $filename;
                }
            }

            // 如果没有找到任何图表，直接返回
            if (empty($chartFiles)) {
                $zip->close();
                $this->logInfo('未找到图表文件，跳过间距设置');
                return;
            }

            $modifiedCount = 0;

            // 处理所有图表文件
            foreach ($chartFiles as $chartIndex => $chartFile) {
                $chartXml = $zip->getFromName($chartFile);
                $needPercentLabel = !empty($chartLabelFormatMap[$chartIndex]);

                if ($chartXml !== false) {
                    // 检查是否是柱状图（column chart）
                    if (strpos($chartXml, '<c:barChart') !== false || strpos($chartXml, '<c:bar3DChart') !== false) {
                        $modified = false;

                        // 1. 设置 overlap 和 gapWidth
                        // 去除原有标签，保证不出错
                        $chartXml = preg_replace('/<c:gapWidth[^>]*\/>/', '', $chartXml);
                        $chartXml = preg_replace('/<c:overlap[^>]*\/>/', '', $chartXml);

                        $insertXml = "";
                        if ($gapWidth !== null) {
                            $insertXml .= "\n            <c:gapWidth val=\"{$gapWidth}\"/>";
                        }
                        if ($overlap !== null) {
                            $insertXml .= "\n            <c:overlap val=\"{$overlap}\"/>";
                        }

                        if ($insertXml !== "") {
                            // 保证 gapWidth 和 overlap 始终在 axId 之前（在 ser/dLbls/trendline 之后）
                            if (preg_match('/(<\/c:ser>\s*<c:axId)/', $chartXml, $matches, PREG_OFFSET_CAPTURE)) {
                                $pos = $matches[0][1] + strlen('</c:ser>');
                                $chartXml = substr_replace(
                                    $chartXml,
                                    $insertXml,
                                    $pos,
                                    0
                                );
                                $modified = true;
                            } elseif (preg_match('/(<\/c:ser>\s*<\/c:barChart>)/', $chartXml, $matches, PREG_OFFSET_CAPTURE)) {
                                $pos = $matches[0][1];
                                $chartXml = substr_replace(
                                    $chartXml,
                                    $insertXml,
                                    $pos,
                                    0
                                );
                                $modified = true;
                            }
                        }

                        // 2. 设置图例 overlay（确保图例不覆盖在图表上，占用独立空间）
                        // 模板中图例设置为 overlay="0"，表示图例占用独立空间，不覆盖图表
                        // 这样可以确保Word自动调整绘图区域大小，为图例留出空间
                        if (preg_match('/<c:legend>/', $chartXml)) {
                            // 先移除可能存在的 overlay 设置
                            $chartXml = preg_replace('/<c:overlay\s+val="[^"]*"\/>/', '', $chartXml);

                            // 在 legendPos 之后添加 overlay="0"
                            if (preg_match('/(<c:legend>.*?<c:legendPos[^>]*val="[^"]*"[^>]*\/>)/s', $chartXml, $matches, PREG_OFFSET_CAPTURE)) {
                                $pos = $matches[0][1] + strlen($matches[0][0]);
                                $chartXml = substr_replace(
                                    $chartXml,
                                    "\n            <c:overlay val=\"0\"/>",
                                    $pos,
                                    0
                                );
                                $modified = true;
                                $this->logInfo('设置图例overlay', ['chart_file' => $chartFile, 'overlay' => '0']);
                            }
                        }

                        // 2.5. 设置网格线样式（浅色、细线）
                        // 修改 Y 轴（valAx）的主网格线样式
                        // 查找 valAx 中的 majorGridlines
                        if (preg_match('/<c:valAx>/', $chartXml)) {
                            // 先移除可能存在的 majorGridlines（包括其所有子元素）
                            $chartXml = preg_replace('/<c:majorGridlines>.*?<\/c:majorGridlines>/s', '', $chartXml);
                            $chartXml = preg_replace('/<c:majorGridlines\/>/', '', $chartXml);

                            // 在 axPos 之后添加新的 majorGridlines（浅灰色、细线）
                            // 使用 d9d9d9（浅灰色）作为网格线颜色，线宽设置为 9525 EMU（约 0.25pt，很细）
                            if (preg_match('/(<c:valAx>.*?<c:axPos[^>]*\/>)/s', $chartXml, $matches, PREG_OFFSET_CAPTURE)) {
                                $pos = $matches[1][1] + strlen($matches[1][0]);
                                $gridlinesXml = <<<XML

                <c:majorGridlines>
                    <c:spPr>
                        <a:ln w="9525">
                            <a:solidFill>
                                <a:srgbClr val="D9D9D9"/>
                            </a:solidFill>
                        </a:ln>
                    </c:spPr>
                </c:majorGridlines>
XML;
                                $chartXml = substr_replace($chartXml, $gridlinesXml, $pos, 0);
                                $modified = true;
                                $this->logInfo('设置网格线样式', [
                                    'chart_file' => $chartFile,
                                    'color' => 'D9D9D9',
                                    'width' => '9525 EMU'
                                ]);
                            }
                        }

                        // 2.5. 百分比图表标签追加 % 显示
                        if ($needPercentLabel) {
                            $originalChartXml = $chartXml;
                            $replaceCount = 0;
                            $newNumFmtXml = '<c:numFmt formatCode="0.00&quot;%&quot;" sourceLinked="0"/>';
                            $chartXml = preg_replace_callback(
                                '/<c:dLbls>(.*?)<\/c:dLbls>/s',
                                function ($matches) use (&$replaceCount, $newNumFmtXml) {
                                    $dLblsXml = $matches[1];

                                    if (strpos($dLblsXml, '<c:numFmt') !== false) {
                                        $updatedXml = preg_replace(
                                            '/<c:numFmt[^>]*\/>/',
                                            $newNumFmtXml,
                                            $dLblsXml,
                                            1,
                                            $innerReplaceCount
                                        );
                                        if ($innerReplaceCount > 0) {
                                            $replaceCount++;
                                            return '<c:dLbls>' . $updatedXml . '</c:dLbls>';
                                        }
                                    }

                                    $replaceCount++;
                                    return '<c:dLbls>' . $newNumFmtXml . $dLblsXml . '</c:dLbls>';
                                },
                                $chartXml
                            );

                            if ($replaceCount > 0 && $chartXml !== $originalChartXml) {
                                $modified = true;
                                $this->logInfo('设置图表百分比标签格式', [
                                    'chart_file' => $chartFile,
                                    'format_code' => '0.00&quot;%&quot;'
                                ]);
                            }
                        }

                        // 3. 添加趋势线
                        // 对于多系列图表，添加到第二个系列（idx="1"）
                        // 对于单系列图表，添加到第一个系列（idx="0"）
                        if ($addTrendline === true) {
                            // 趋势线应该插入在 </c:dLbls> 之后，<c:cat> 之前（与实例图表一致）
                            // 确保趋势线连接到柱状图的顶部中心位置
                            // 如果图表已经有趋势线，先移除旧的，然后添加新的（确保所有图表都有）
                            $chartXml = preg_replace('/<c:trendline>.*?<\/c:trendline>/s', '', $chartXml);

                            $trendlineXml = "\n<c:trendline><c:spPr><a:ln w=\"12700\" cap=\"rnd\"><a:solidFill><a:schemeClr val=\"accent2\"/></a:solidFill><a:prstDash val=\"sysDot\"/></a:ln><a:effectLst/></c:spPr><c:trendlineType val=\"linear\"/><c:forward val=\"0\"/><c:backward val=\"0\"/><c:dispRSqr val=\"0\"/><c:dispEq val=\"0\"/></c:trendline>";

                            // 优先尝试第二个系列（idx="1"）- 用于多系列图表
                            // 查找第二个系列的 </c:dLbls> 后跟 <c:cat> 或 <c:val> 的位置
                            if (preg_match('/(<c:ser>.*?<c:idx\s+val="1".*?<\/c:dLbls>)(\s*<c:cat>)/s', $chartXml, $matches, PREG_OFFSET_CAPTURE)) {
                                $pos = $matches[1][1] + strlen($matches[1][0]);
                                // 插入趋势线 XML（在 </c:dLbls> 之后，<c:cat> 之前）
                                // 使用较细的线条（12700 EMU，约 0.35pt）避免遮挡数据标签
                                // forward 和 backward 设置为 0，让趋势线精确连接数据点顶部
                                $trendlineXml = "\n<c:trendline><c:spPr><a:ln w=\"12700\" cap=\"rnd\"><a:solidFill><a:schemeClr val=\"accent2\"/></a:solidFill><a:prstDash val=\"sysDot\"/></a:ln><a:effectLst/></c:spPr><c:trendlineType val=\"linear\"/><c:forward val=\"0\"/><c:backward val=\"0\"/><c:dispRSqr val=\"0\"/><c:dispEq val=\"0\"/></c:trendline>";
                                $chartXml = substr_replace($chartXml, $trendlineXml, $pos, 0);
                                $modified = true;
                            } elseif (preg_match('/(<c:ser>.*?<c:idx\s+val="1".*?<\/c:dLbls>)(\s*<c:val>)/s', $chartXml, $matches, PREG_OFFSET_CAPTURE)) {
                                // 如果结构是 dLbls 后跟 val，在 dLbls 之后插入
                                $pos = $matches[1][1] + strlen($matches[1][0]);
                                $trendlineXml = "\n<c:trendline><c:spPr><a:ln w=\"12700\" cap=\"rnd\"><a:solidFill><a:schemeClr val=\"accent2\"/></a:solidFill><a:prstDash val=\"sysDot\"/></a:ln><a:effectLst/></c:spPr><c:trendlineType val=\"linear\"/><c:forward val=\"0\"/><c:backward val=\"0\"/><c:dispRSqr val=\"0\"/><c:dispEq val=\"0\"/></c:trendline>";
                                $chartXml = substr_replace($chartXml, $trendlineXml, $pos, 0);
                                $modified = true;
                            } elseif (preg_match('/(<c:ser>.*?<c:idx\s+val="1".*?<\/c:dLbls>)/s', $chartXml, $matches, PREG_OFFSET_CAPTURE)) {
                                // 如果没有 cat 或 val 标签（某些图表结构），在 dLbls 之后插入
                                $pos = $matches[0][1] + strlen($matches[0][0]);
                                $trendlineXml = "\n<c:trendline><c:spPr><a:ln w=\"12700\" cap=\"rnd\"><a:solidFill><a:schemeClr val=\"accent2\"/></a:solidFill><a:prstDash val=\"sysDot\"/></a:ln><a:effectLst/></c:spPr><c:trendlineType val=\"linear\"/><c:forward val=\"0\"/><c:backward val=\"0\"/><c:dispRSqr val=\"0\"/><c:dispEq val=\"0\"/></c:trendline>";
                                $chartXml = substr_replace($chartXml, $trendlineXml, $pos, 0);
                                $modified = true;
                            } else {
                                // 如果没有找到第二个系列（idx="1"），说明是单系列图表
                                // 尝试为第一个系列（idx="0"）添加趋势线
                                if (preg_match('/(<c:ser>.*?<c:idx\s+val="0".*?<\/c:dLbls>)(\s*<c:cat>)/s', $chartXml, $matches, PREG_OFFSET_CAPTURE)) {
                                    $pos = $matches[1][1] + strlen($matches[1][0]);
                                    $chartXml = substr_replace($chartXml, $trendlineXml, $pos, 0);
                                    $modified = true;
                                } elseif (preg_match('/(<c:ser>.*?<c:idx\s+val="0".*?<\/c:dLbls>)(\s*<c:val>)/s', $chartXml, $matches, PREG_OFFSET_CAPTURE)) {
                                    $pos = $matches[1][1] + strlen($matches[1][0]);
                                    $chartXml = substr_replace($chartXml, $trendlineXml, $pos, 0);
                                    $modified = true;
                                } elseif (preg_match('/(<c:ser>.*?<c:idx\s+val="0".*?<\/c:dLbls>)/s', $chartXml, $matches, PREG_OFFSET_CAPTURE)) {
                                    $pos = $matches[0][1] + strlen($matches[0][0]);
                                    $chartXml = substr_replace($chartXml, $trendlineXml, $pos, 0);
                                    $modified = true;
                                }
                            }
                        }

                        if ($modified) {
                            // 写回修改后的 XML
                            $zip->deleteName($chartFile);
                            $zip->addFromString($chartFile, $chartXml);
                            $modifiedCount++;

                            $this->logInfo('图表设置成功', [
                                'chart_file' => $chartFile,
                                'overlap' => $overlap,
                                'gapWidth' => $gapWidth,
                                'trendline_added' => $addTrendline === true && preg_match('/<c:trendline>/', $chartXml)
                            ]);
                        }
                    }
                }
            }

            $zip->close();

            if ($modifiedCount == 0) {
                $this->logInfo('未找到需要修改的柱状图');
            }
        } catch (\Exception $e) {
            $this->logError('设置图表间距和趋势线失败', [
                'docx_path' => $docxPath,
                'overlap' => $overlap,
                'gapWidth' => $gapWidth,
                'addTrendline' => $addTrendline,
                'error' => $e->getMessage()
            ]);
            // 不抛出异常，因为这不是致命错误
        }
    }

    /**
     * 生成报告（完整流程）
     * @param string $templatePath 模板文件路径
     * @param array $data 要填充的数据
     * @param string $outputPath 输出文件路径
     * @return string 生成的文件路径
     * @throws \Exception
     */
    public function generateReport($templatePath, array $data, $outputPath)
    {
        $this->loadTemplate($templatePath);
        $this->replaceVariables($data);
        return $this->saveReport($outputPath);
    }

    /**
     * 生成病历质控分析报告
     * @param array $statisticsData 统计数据（来自qualityStatistics方法）
     * @param string $startTime 开始时间
     * @param string $endTime 结束时间
     * @param string $outputPath 输出文件路径（可选，默认使用storage目录）
     * @return string 生成的文件路径
     * @throws \Exception
     */
    public function generateQualityReport(array $statisticsData, $startTime = '', $endTime = '', $outputPath = '')
    {
        // 获取项目根目录（兼容非 Laravel 环境）
        $basePath = $this->getBasePath();

        // 模板文件路径（优先使用项目根目录）
        $templatePath = $basePath . '/病历质控分析报告.docx';

        // 如果模板文件不存在，尝试其他路径
        if (!file_exists($templatePath)) {
            $storagePath = $this->getStoragePath();
            $templatePath = $storagePath . '/templates/病历质控分析报告.docx';
        }

        if (!file_exists($templatePath)) {
            throw new \Exception("找不到模板文件，请确认模板文件路径。当前查找路径: " . $basePath . '/病历质控分析报告.docx');
        }

        // 准备数据映射
        $data = [
            // 时间范围
            'startTime' => $startTime ? date('Y年m月d日', strtotime($startTime)) : '',
            'endTime' => $endTime ? date('Y年m月d日', strtotime($endTime)) : '',
            'reportDate' => date('Y年m月d日'),

            // 基础统计
            'blCount' => $statisticsData['blCount'] ?? 0,

            'youRatio' => $statisticsData['you_ratio'] ?? '0.00',

        ];

        // 科室数相关（用于说明文字）
        $data['kssl']     = $statisticsData['kssl'] ?? 0;      // 科室总数
        $data['jjkssl']   = $statisticsData['jjkssl'] ?? 0;    // 甲级病案率达标科室数
        $data['jjkssl50'] = $statisticsData['jjkssl50'] ?? 0;  // 甲级病案率 <50% 科室数
        $data['hgkssl']   = $statisticsData['hgkssl'] ?? 0;    // 质控合格率 100% 科室数
        $data['hgkssl50'] = $statisticsData['hgkssl50'] ?? 0;  // 质控合格率 <50% 科室数

        // 重点问题名称（支持字符串或数组，多条时按行显示）
        if (isset($statisticsData['zdWtmc'])) {
            $data['zdWtmc'] = $statisticsData['zdWtmc'];
        } elseif (isset($statisticsData['zdWtmc_list'])) {
            $data['zdWtmc'] = $statisticsData['zdWtmc_list'];
        }

        // 加载模板并替换变量
        $this->loadTemplate($templatePath);
        $this->replaceVariables($data);

        // 图表替换（优先使用 PhpWord 原生图表功能）
        if (isset($statisticsData['chartData']) && is_array($statisticsData['chartData']) && !empty($statisticsData['chartData'])) {
            try {
                $chartOptions = $statisticsData['chartOptions'] ?? [
                    'width' => 5040000,   // 约 14cm (14 * 360000 EMU) - 适合 A4 页面宽度
                    'height' => 2880000,  // 约 8cm (8 * 360000 EMU) - 适中高度
                    'title' => '病案内涵质控趋势图'
                ];
                $this->replaceChart('chartImage', $statisticsData['chartData'], $chartOptions);
            } catch (\Exception $e) {
                // 图表替换失败不影响报告生成，只记录日志
                $this->logError('图表替换失败', [
                    'error' => $e->getMessage()
                ]);

                // 如果图表替换失败，尝试使用图片方式（备用方案）
                if (isset($statisticsData['chartImagePath']) && !empty($statisticsData['chartImagePath'])) {
                    $chartImagePath = $statisticsData['chartImagePath'];
                    $imageOptions = $statisticsData['chartImageOptions'] ?? ['width' => '15cm', 'height' => '10cm'];

                    if (file_exists($chartImagePath)) {
                        try {
                            $this->replaceImage('chartImage', $chartImagePath, $imageOptions);
                        } catch (\Exception $imgE) {
                            $this->logError('图表图片替换失败', [
                                'chart_image_path' => $chartImagePath,
                                'error' => $imgE->getMessage()
                            ]);
                        }
                    }
                }
            }
        } elseif (isset($statisticsData['chartImagePath']) && !empty($statisticsData['chartImagePath'])) {
            // 如果没有图表数据，但有图片路径，使用图片方式
            $chartImagePath = $statisticsData['chartImagePath'];
            $chartOptions = $statisticsData['chartOptions'] ?? ['width' => '15cm', 'height' => '10cm'];

            if (file_exists($chartImagePath)) {
                try {
                    $this->replaceImage('chartImage', $chartImagePath, $chartOptions);
                } catch (\Exception $e) {
                    $this->logError('图表图片替换失败', [
                        'chart_image_path' => $chartImagePath,
                        'error' => $e->getMessage()
                    ]);
                }
            } else {
                $this->logError('图表图片文件不存在', [
                    'chart_image_path' => $chartImagePath
                ]);
            }
        }

        // 如果没有指定输出路径，使用默认路径
        if (empty($outputPath)) {
            $filename = '病历质控分析报告_' . date('YmdHis') . '.docx';
            $storagePath = $this->getStoragePath();
            $outputPath = $storagePath . '/reports/' . $filename;
        }

        // 保存报告
        return $this->saveReport($outputPath);
    }

    /**
     * 获取模板中所有变量名
     * @return array 变量名数组
     * @throws \Exception
     */
    public function getTemplateVariables()
    {
        if ($this->templateProcessor === null) {
            throw new \Exception("请先加载模板文件");
        }

        try {
            // 通过读取模板文件内容来解析变量
            $templatePath = $this->templatePath;
            if (empty($templatePath)) {
                throw new \Exception("无法获取模板路径");
            }

            // 解压 docx 文件并读取 document.xml
            $zip = new \ZipArchive();
            if ($zip->open($templatePath) === true) {
                $xml = $zip->getFromName('word/document.xml');
                $zip->close();

                if ($xml === false) {
                    throw new \Exception("无法读取模板内容");
                }

                // 匹配 ${变量名} 格式
                $variables = [];
                preg_match_all('/\$\{([^}]+)\}/', $xml, $matches);
                if (!empty($matches[1])) {
                    $variables = array_unique($matches[1]);
                }

                return $variables;
            } else {
                throw new \Exception("无法打开模板文件");
            }
        } catch (\Exception $e) {
            $this->logError('获取模板变量失败', [
                'error' => $e->getMessage()
            ]);
            throw new \Exception("获取模板变量失败: " . $e->getMessage());
        }
    }

    /**
     * 记录错误日志（兼容非 Laravel 环境）
     * @param string $message 日志消息
     * @param array $context 上下文信息
     * @return void
     */
    private function logError($message, array $context = [])
    {
        try {
            if (
                class_exists('Illuminate\Support\Facades\Log') &&
                class_exists('Illuminate\Foundation\Application') &&
                app()->bound('log')
            ) {
                \Illuminate\Support\Facades\Log::error($message, $context);
            } else {
                error_log($message . ': ' . json_encode($context, JSON_UNESCAPED_UNICODE));
            }
        } catch (\Exception $e) {
            // 如果 Laravel 未初始化，使用 error_log
            error_log($message . ': ' . json_encode($context, JSON_UNESCAPED_UNICODE));
        }
    }

    /**
     * 记录信息日志（兼容非 Laravel 环境）
     * @param string $message 日志消息
     * @param array $context 上下文信息
     * @return void
     */
    private function logInfo($message, array $context = [])
    {
        try {
            if (
                class_exists('Illuminate\Support\Facades\Log') &&
                class_exists('Illuminate\Foundation\Application') &&
                function_exists('app') && app()->bound('log')
            ) {
                \Illuminate\Support\Facades\Log::info($message, $context);
            } else {
                error_log($message . ': ' . json_encode($context, JSON_UNESCAPED_UNICODE));
            }
        } catch (\Exception $e) {
            // 如果 Laravel 未初始化，使用 error_log
            error_log($message . ': ' . json_encode($context, JSON_UNESCAPED_UNICODE));
        }
    }

    /**
     * 获取项目根目录（兼容非 Laravel 环境）
     * @return string
     */
    private function getBasePath()
    {
        // 从当前文件位置推断项目根目录
        $reflection = new \ReflectionClass($this);
        $filePath = $reflection->getFileName();
        // app/Services/WordReportService.php -> 项目根目录
        return dirname(dirname(dirname($filePath)));
    }

    /**
     * 获取 storage 目录路径（兼容非 Laravel 环境）
     * @return string
     */
    private function getStoragePath()
    {
        // 使用项目根目录下的 storage/app
        $basePath = $this->getBasePath();
        return $basePath . '/storage/app';
    }
}
