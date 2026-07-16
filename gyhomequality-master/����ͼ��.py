#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
生成病案内涵质控趋势图（柱状图+折线图）
"""

import matplotlib
matplotlib.use('Agg')  # 使用非交互式后端
import matplotlib.pyplot as plt
import numpy as np
from matplotlib import font_manager
import os

# 设置中文字体（如果系统有中文字体）
try:
    # macOS 系统字体
    plt.rcParams['font.sans-serif'] = ['Arial Unicode MS', 'SimHei', 'Microsoft YaHei', 'DejaVu Sans']
    plt.rcParams['axes.unicode_minus'] = False
except:
    pass

def generate_chart(data, output_path):
    """
    生成组合图表（柱状图+折线图）
    
    Args:
        data: 字典格式，如 {'2025年11月': {'total': 123456, 'defect': 12345}, ...}
        output_path: 输出图片路径
    """
    # 准备数据
    months = list(data.keys())
    totals = [data[m]['total'] for m in months]
    defects = [data[m]['defect'] for m in months]
    
    # 创建图表
    fig, ax1 = plt.subplots(figsize=(10, 6))
    
    # X 轴位置
    x = np.arange(len(months))
    width = 0.35  # 柱状图宽度
    
    # 绘制柱状图（病历总数 - 蓝色）
    bars1 = ax1.bar(x - width/2, totals, width, label='病历总数', color='#4F81BD', alpha=0.8)
    
    # 绘制柱状图（缺陷病历 - 橙色）
    bars2 = ax1.bar(x + width/2, defects, width, label='缺陷病历', color='#FFC000', alpha=0.8)
    
    # 设置 Y 轴标签和范围
    ax1.set_xlabel('月份', fontsize=12)
    ax1.set_ylabel('数量', fontsize=12, color='black')
    ax1.set_xticks(x)
    ax1.set_xticklabels(months, fontsize=10)
    ax1.tick_params(axis='y', labelcolor='black')
    ax1.grid(True, alpha=0.3, linestyle='--')
    ax1.legend(loc='upper left', fontsize=10)
    
    # 创建第二个 Y 轴用于折线图
    ax2 = ax1.twinx()
    
    # 绘制折线图（缺陷病历趋势 - 橙色虚线）
    line = ax2.plot(x, defects, 'o--', color='#FFC000', linewidth=2, 
                    markersize=8, label='线性(缺陷病历)', alpha=0.8)
    ax2.set_ylabel('缺陷病历数量', fontsize=12, color='#FFC000')
    ax2.tick_params(axis='y', labelcolor='#FFC000')
    ax2.legend(loc='upper right', fontsize=10)
    
    # 在柱状图上添加数值标签
    for i, (bar1, bar2) in enumerate(zip(bars1, bars2)):
        height1 = bar1.get_height()
        height2 = bar2.get_height()
        ax1.text(bar1.get_x() + bar1.get_width()/2., height1,
                f'{int(height1):,}', ha='center', va='bottom', fontsize=9)
        ax1.text(bar2.get_x() + bar2.get_width()/2., height2,
                f'{int(height2):,}', ha='center', va='bottom', fontsize=9)
    
    # 设置标题
    plt.title('病案内涵质控趋势图', fontsize=14, fontweight='bold', pad=20)
    
    # 调整布局
    plt.tight_layout()
    
    # 保存图片
    plt.savefig(output_path, dpi=300, bbox_inches='tight', format='png')
    plt.close()
    
    print(f"图表已生成: {output_path}")
    print(f"图片尺寸: {os.path.getsize(output_path) / 1024:.2f} KB")

if __name__ == '__main__':
    # 测试数据
    chart_data = {
        '2025年11月': {
            'total': 123456,    # 病历总数
            'defect': 12345,   # 缺陷病历
        },
        '2025年12月': {
            'total': 123456,    # 病历总数
            'defect': 22345,    # 缺陷病历
        },
    }
    
    # 输出路径
    base_dir = os.path.dirname(os.path.abspath(__file__))
    output_path = os.path.join(base_dir, 'storage', 'app', 'charts', '病案内涵质控趋势图.png')
    
    # 确保目录存在
    os.makedirs(os.path.dirname(output_path), exist_ok=True)
    
    # 生成图表
    generate_chart(chart_data, output_path)

