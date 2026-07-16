<!-- 缺陷分布 -->
<template>
  <div class="defect-content">
    <div class="chart-group">
      <div class="chart-card">
        <h3 class="chart-title">缺陷类型分布</h3>
        <div class="chart-box" ref="defectTypeChart"></div>
      </div>
      <div class="chart-card">
        <h3 class="chart-title">缺陷问题TOP10</h3>
        <div class="chart-box" ref="defectTopChart"></div>
      </div>
    </div>

    <div class="table-card">
      <h3 class="table-title">时效性问题分布</h3>
      <el-table
        :data="defectData.timeliness_rules"
        border
        stripe
        size="medium"
        style="width: 100%"
        :row-class-name="tableRowClassName"
        v-loading="tableLoading"
      >
        <el-table-column label="序号" align="center" width="80">
          <template slot-scope="scope">{{ scope.$index + 1 }}</template>
        </el-table-column>
        <el-table-column label="缺陷名称" align="left">
          <template slot-scope="scope">{{ scope.row.rule_name || '-' }}</template>
        </el-table-column>
        <el-table-column label="质控病例数（例）" align="center">
          <template slot-scope="scope">
            <span
              class="value-text-blue value-underline"
              @click="handleClick('cases-1', scope.row)"
            >{{ scope.row.quality_cases || 0 }}</span>
          </template>
        </el-table-column>
        <el-table-column label="缺陷数量（个）" align="center">
          <template slot-scope="scope">
            <span
              class="value-text-red value-underline"
              @click="handleClick('defect-1', scope.row)"
            >{{ scope.row.defect_cases || 0 }}</span>
          </template>
        </el-table-column>
        <!-- <el-table-column label="缺陷数量（个）" align="center">
          <template slot-scope="scope">
            {{ scope.row.defect_count || 0 }}
          </template>
        </el-table-column>-->
        <el-table-column label="缺陷占比" align="center">
          <template slot-scope="scope">
            <span :class="{ highlight: true }">{{ formatPercent(scope.row.ratio) }}</span>
          </template>
        </el-table-column>
      </el-table>
    </div>

    <div class="table-card">
      <h3 class="table-title">内涵质量问题分布</h3>
      <el-table
        :data="defectData.content_rules"
        border
        stripe
        size="medium"
        style="width: 100%"
        :row-class-name="tableRowClassName"
        v-loading="tableLoading"
      >
        <el-table-column label="序号" align="center" width="80">
          <template slot-scope="scope">{{ scope.$index + 1 }}</template>
        </el-table-column>
        <el-table-column label="缺陷名称" align="left">
          <template slot-scope="scope">{{ scope.row.rule_name || '-' }}</template>
        </el-table-column>
        <el-table-column label="质控病例数（例）" align="center">
          <template slot-scope="scope">
            <span
              class="value-text-blue value-underline"
              @click="handleClick('cases-2', scope.row)"
            >{{ scope.row.quality_cases || 0 }}</span>
          </template>
        </el-table-column>
        <el-table-column label="缺陷数量（个）" align="center">
          <template slot-scope="scope">
            <span
              class="value-text-red value-underline"
              @click="handleClick('defect-2', scope.row)"
            >{{ scope.row.defect_cases || 0 }}</span>
          </template>
        </el-table-column>
        <!-- <el-table-column label="缺陷数量（个）" align="center">
          <template slot-scope="scope">
            {{ scope.row.defect_count || 0 }}
          </template>
        </el-table-column>-->
        <el-table-column label="缺陷占比" align="center">
          <template slot-scope="scope">
            <span :class="{ highlight: true }">{{ formatPercent(scope.row.ratio) }}</span>
          </template>
        </el-table-column>
      </el-table>
    </div>

    <div class="table-card">
      <h3 class="table-title">病案首页问题分布</h3>
      <el-table
        :data="defectData.homepage_rules"
        border
        stripe
        size="medium"
        style="width: 100%"
        :row-class-name="tableRowClassName"
        v-loading="tableLoading"
      >
        <el-table-column label="序号" align="center" width="80">
          <template slot-scope="scope">{{ scope.$index + 1 }}</template>
        </el-table-column>
        <el-table-column label="缺陷名称" align="left">
          <template slot-scope="scope">{{ scope.row.rule_name || '-' }}</template>
        </el-table-column>
        <el-table-column label="质控病例数（例）" align="center">
          <template slot-scope="scope">
            <span
              class="value-text-blue value-underline"
              @click="handleClick('cases-3', scope.row)"
            >{{ scope.row.quality_cases || 0 }}</span>
          </template>
        </el-table-column>
        <el-table-column label="缺陷数量（个）" align="center">
          <template slot-scope="scope">
            <span
              class="value-text-red value-underline"
              @click="handleClick('defect-3', scope.row)"
            >{{ scope.row.defect_cases || 0 }}</span>
          </template>
        </el-table-column>
        <!-- <el-table-column label="缺陷数量（个）" align="center">
          <template slot-scope="scope">
            {{ scope.row.defect_count || 0 }}
          </template>
        </el-table-column>-->
        <el-table-column label="缺陷占比" align="center">
          <template slot-scope="scope">
            <span :class="{ highlight: true }">{{ formatPercent(scope.row.ratio) }}</span>
          </template>
        </el-table-column>
      </el-table>
    </div>
  </div>
</template>

<script>
import * as echarts from 'echarts';

export default {
  name: 'DefectDistribution',
  data() {
    return {
      chartInstances: {},
      chartData: {
        defectTypeData: [],
        defectTopData: [],
      },
      queryParams: {},
      defectData: {
        timeliness_rules: [],
        content_rules: [],
        homepage_rules: [],
        type_distribution: [],
        top10: [],
      },
      tableLoading: false,
      pieColorMap: {
        时效性: '#ef4444',
        完整性: '#f97316',
        逻辑性: '#eab308',
        内涵性: '#3b82f6',
        专病类: '#8b5cf6',
        专科类: '#14b8a6',
        检查类: '#64748b',
        其他: '#94a3b8',
      },
    };
  },
  mounted() {
    this.initCharts();
    window.addEventListener('resize', this.resizeCharts);
  },
  destroyed() {
    // 销毁ECharts实例，避免内存泄漏
    Object.values(this.chartInstances).forEach(instance => {
      instance.dispose();
    });
    window.removeEventListener('resize', this.resizeCharts);
  },
  methods: {
    /**
     * 初始化数据（外部调用，传入接口数据）
     * @param {Object} queryParams - 查询参数
     * @param {Object} data - 接口返回的完整数据
     */
    initData(queryParams = {}, data) {
      this.tableLoading = true;
      try {
        this.queryParams = { ...queryParams };
        this.defectData = { ...this.defectData, ...data };
        if (data.type_distribution) this.chartData.defectTypeData = data.type_distribution;
        if (data.top10) this.chartData.defectTopData = data.top10;
        this.initCharts();
      } catch (error) {
        console.error('数据初始化失败：', error);
      } finally {
        this.tableLoading = false;
      }
    },

    formatPercent(value) {
      if (!value && value !== 0) return '0.00%';
      const num = Number(value);
      if (isNaN(num)) return '0.00%';
      return String(value).includes('%') ? value : `${num.toFixed(2)}%`;
    },

    handleClick(tabKey, row) {
      console.log(tabKey, row);
      const { startTime, endTime } = this.calculateDateRange();
      const { dep_id } = this.queryParams;
      switch (tabKey) {
        case 'cases-1':
          this.$router.push({
            name: 'reportMedicalRecords',
            query: { startTime, endTime, dep_id, type: 'distribution-defect-control-case' },
          });
          break;
        case 'defect-1':
          this.$router.push({
            name: 'reportMedicalRecords',
            query: { startTime, endTime, dep_id, rule_type: '1', rule_id: [row.rule_id], type: 'distribution-defect-control' },
          });
          break;
        case 'cases-2':
        case 'defect-2':
          this.$router.push({
            name: 'reportMedicalRecords',
            query: { startTime, endTime, dep_id, rule_id: [row.rule_id], type: 'distribution-defect-control' },
          });
          break;
        case 'cases-3':
        case 'defect-3':
          this.$router.push({
            name: 'reportMedicalRecords',
            query: { startTime, endTime, rule_type: '2', dep_id, rule_id: [row.rule_id], type: 'distribution-defect-control' },
          });
          break;

        default:
          break;
      }
    },

    calculateDateRange() {
      let startTime = '';
      let endTime = '';
      const { type = 'year', year = new Date().getFullYear().toString(), quarter = 1, month = '01' } = this.queryParams;
      switch (type) {
        case 'year': {
          startTime = `${year}0101`;
          endTime = `${year}1231`;
          break;
        }
        case 'quarter': {
          const quarterStartMonthMap = {
            1: '01', // 一季度：1-3月
            2: '04', // 二季度：4-6月
            3: '07', // 三季度：7-9月
            4: '10', // 四季度：10-12月
          };
          const quarterEndMonthMap = {
            1: '03',
            2: '06',
            3: '09',
            4: '12',
          };
          const quarterEndDayMap = {
            1: '31', // 3月31日
            2: '30', // 6月30日
            3: '30', // 9月30日
            4: '31', // 12月31日
          };
          const startMonth = quarterStartMonthMap[quarter] || '01';
          const endMonth = quarterEndMonthMap[quarter] || '12';
          const endDay = quarterEndDayMap[quarter] || '31';

          startTime = `${year}${startMonth}01`;
          endTime = `${year}${endMonth}${endDay}`;
          break;
        }
        case 'month': {
          startTime = `${year}${month}01`;
          const lastDay = new Date(Number(year), Number(month), 0).getDate();
          const lastDayStr = lastDay.toString().padStart(2, '0');
          endTime = `${year}${month}${lastDayStr}`;
          break;
        }
      }
      return { startTime, endTime };
    },
    /**
     * 表格行样式类名
     */
    tableRowClassName() {
      return 'table-row-hover';
    },

    /**
     * 初始化所有图表
     */
    initCharts() {
      this.initDefectTypeChart();
      this.initDefectTopChart();
    },

    /**
     * 初始化缺陷类型饼图
     */
    initDefectTypeChart() {
      const el = this.$refs.defectTypeChart;
      if (!el) return;

      if (this.chartInstances.defectTypeChart) {
        this.chartInstances.defectTypeChart.dispose();
      }

      const chart = echarts.init(el);
      this.chartInstances.defectTypeChart = chart;

      // 无数据处理
      if (!this.chartData.defectTypeData || this.chartData.defectTypeData.length === 0) {
        this.renderNoData(chart);
        return;
      }

      const pieData = this.chartData.defectTypeData.map(item => ({
        name: item.type,
        value: item.count,
        itemStyle: { color: this.pieColorMap[item.type] || '#94a3b8' },
      }));

      chart.setOption({
        tooltip: {
          trigger: 'item',
          formatter: '{a} <br/>{b}: {c} 例 ({d}%)',
          backgroundColor: 'rgba(255, 255, 255, 0.9)',
          borderColor: '#e6e6e6',
          borderWidth: 1,
          textStyle: { color: '#333', fontSize: 12 },
          padding: [8, 12],
          borderRadius: 6,
        },
        legend: {
          orient: 'horizontal',
          top: 'top',
          data: pieData.map(item => item.name),
          textStyle: { fontSize: 12, color: '#666' },
          itemWidth: 12,
          itemHeight: 12,
          itemGap: 15,
        },
        series: [
          {
            name: '缺陷类型',
            type: 'pie',
            radius: ['30%', '60%'],
            center: ['50%', '60%'],
            data: pieData,
            label: {
              show: true,
              position: 'outside',
              fontSize: 18,
              color: '#333',
              formatter: function (params) {
                return `${params.name}: ${params.data.value}例`;
              },
              alignTo: 'labelLine',
              distanceToLabelLine: 5,
            },
            labelLine: {
              show: true,
              length: 20,
              length2: 15,
              lineStyle: {
                color: '#999',
                width: 1,
                type: 'solid',
              },
              smooth: 0.2,
              minTurnAngle: 45,
            },
            emphasis: {
              itemStyle: {
                shadowBlur: 10,
                shadowOffsetX: 0,
                shadowColor: 'rgba(0, 0, 0, 0.5)',
              },
            },
          },
        ],
      });
    },

    /**
     * 初始化缺陷TOP10柱状图
     */
    initDefectTopChart() {
      const el = this.$refs.defectTopChart;
      if (!el) return;

      if (this.chartInstances.defectTopChart) {
        this.chartInstances.defectTopChart.dispose();
      }

      const chart = echarts.init(el);
      this.chartInstances.defectTopChart = chart;

      // 无数据处理
      if (!this.chartData.defectTopData || this.chartData.defectTopData.length === 0) {
        this.renderNoData(chart);
        return;
      }
      const yAxisData = this.chartData.defectTopData.map(item => item.rule_name);
      const seriesData = this.chartData.defectTopData.map(item => item.defect_count);
      seriesData.reverse();
      yAxisData.reverse();
      chart.setOption({
        tooltip: {
          trigger: 'axis',
          axisPointer: { type: 'shadow' },
          formatter: params => {
            const data = params[0].dataIndex;
            const item = this.chartData.defectTopData[data];
            return `
              <div style="text-align: left">
                <p>${item.rule_name}</p>
                <p>缺陷病历数：${item.defect_cases} 例</p>
                <p>缺陷数量：${item.defect_count} 个</p>
                <p>缺陷占比：${this.formatPercent(item.ratio)}</p>
              </div>
            `;
          },
          backgroundColor: 'rgba(255, 255, 255, 0.9)',
          borderColor: '#e6e6e6',
          borderWidth: 1,
          textStyle: { color: '#333', fontSize: 12 },
          padding: 10,
          borderRadius: 6,
        },
        grid: {
          left: '8%',
          right: '4%',
          bottom: '3%',
          top: '5%',
          containLabel: true,
        },
        xAxis: {
          type: 'value',
          boundaryGap: [0, 0.01],
          axisLabel: { color: '#666', fontSize: 12 },
          axisLine: { lineStyle: { color: '#e8e8e8' } },
          splitLine: { lineStyle: { color: '#f5f5f5' } },
        },
        yAxis: {
          type: 'category',
          data: yAxisData,
          axisLabel: {
            color: '#666',
            fontSize: 11,
            formatter: value => (value.length > 15 ? `${value.slice(0, 15)}...` : value),
          },
          axisLine: { show: false },
          splitLine: { show: false },
        },
        series: [
          {
            name: '缺陷数量',
            type: 'bar',
            data: seriesData,
            barWidth: '60%', // 柱子宽度
            itemStyle: {
              color: new echarts.graphic.LinearGradient(0, 0, 1, 0, [
                { offset: 0, color: '#ef4444' },
                { offset: 1, color: '#f87171' },
              ]),
              borderRadius: [0, 4, 4, 0], // 右侧圆角
            },
            label: {
              show: true,
              position: 'right',
              color: '#666',
              fontSize: 11,
            },
          },
        ],
      });
    },

    resizeCharts() {
      Object.values(this.chartInstances).forEach(instance => {
        instance.resize();
      });
    },
    /**
     * 渲染无数据提示
     * @param {Object} chart - ECharts实例
     * @param {String} text - 提示文本，默认"暂无数据"
     */
    renderNoData(chart, text = '暂无数据') {
      chart.setOption({
        graphic: [
          {
            type: 'text',
            left: '50%',
            top: '50%',
            style: {
              text: text,
              fontSize: 16,
              textAlign: 'center',
              fill: '#999',
              fontWeight: 400,
            },
            transform: {
              translate: [-chart.getWidth() / 2, -chart.getHeight() / 2],
            },
          },
        ],
        tooltip: { show: false },
        legend: { show: false },
        series: [{ data: [] }],
      });
    },
  },
};
</script>

<style lang="scss" scoped>
.defect-content {
  width: 100%;
  padding: 0 10px;
  box-sizing: border-box;
}

.chart-group {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
  gap: 20px;
  margin-bottom: 20px;

  .chart-card {
    background: #fff;
    border-radius: 4px;
    padding: 20px;
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.08);
    border: 1px solid #e8e8e8;

    .chart-title {
      font-size: 16px;
      color: #333;
      margin-bottom: 16px;
      padding-left: 12px;
      border-left: 3px solid #185da6;
    }

    .chart-box {
      height: 300px;
      width: 100%;
    }
  }
}

.table-card {
  background: #fff;
  padding: 20px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.08);
  border: 1px solid #e8e8e8;
  margin-bottom: 20px;
  overflow-x: auto;

  .table-title {
    font-size: 16px;
    color: #333;
    margin-bottom: 16px;
    padding-left: 12px;
    border-left: 3px solid #185da6;
  }
}

.value-text-blue {
  color: #185da6;
  cursor: pointer;
}

.value-text-red {
  color: #ef4444;
  cursor: pointer;
}

.value-underline {
  text-decoration: underline;
  text-underline-offset: 4px;
  text-decoration-color: currentColor;
  text-decoration-thickness: 2px;
}

.highlight {
  color: #ef4444 !important;
  font-weight: 600;
}

::v-deep .table-row-hover:hover {
  background-color: #f8f9fa !important;
}

::v-deep .el-table__header-wrapper th {
  font-weight: 600 !important;
  color: #333 !important;
  background-color: #f5f7fa !important;
  border: none !important;
  height: 48px;
}

::v-deep .el-table__body-wrapper td {
  border-bottom: 1px solid #e8e8e8 !important;
  color: #333 !important;
  height: 48px;
}

::v-deep .el-loading-mask {
  background-color: rgba(255, 255, 255, 0.8) !important;
}

@media (max-width: 768px) {
  .chart-group {
    grid-template-columns: 1fr;
  }

  .chart-card .chart-box {
    height: 250px;
  }

  .table-card {
    padding: 10px;
  }
}
</style>