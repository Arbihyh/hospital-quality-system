<!-- 指标对比 -->
<template>
  <div class="index-compare-content">
    <!-- <div class="filter-bar">
      <el-form :inline="true" :model="filterForm" class="filter-form">
        <el-form-item label="对比科室：">
          <el-select v-model="filterForm.deptList" multiple placeholder="请选择对比科室" @change="handleFilterChange">
            <el-option label="内科" value="internal"></el-option>
            <el-option label="外科" value="surgery"></el-option>
            <el-option label="妇产科" value="obstetrics"></el-option>
            <el-option label="儿科" value="pediatrics"></el-option>
            <el-option label="急诊科" value="emergency"></el-option>
          </el-select>
        </el-form-item>
        <el-form-item label="指标类型：">
          <el-select v-model="filterForm.indexType" placeholder="请选择指标类型" clearable @change="handleFilterChange">
            <el-option label="全部指标" value=""></el-option>
            <el-option label="质控核心指标" value="core"></el-option>
            <el-option label="整改完成指标" value="rectify"></el-option>
            <el-option label="病历等级指标" value="recordLevel"></el-option>
          </el-select>
        </el-form-item>
        <el-form-item label="对比周期：">
          <el-select v-model="filterForm.comparePeriod" placeholder="请选择对比周期" clearable @change="handleFilterChange">
            <el-option label="近6个月" value="6months"></el-option>
            <el-option label="近12个月" value="12months"></el-option>
            <el-option label="近3个季度" value="3quarters"></el-option>
          </el-select>
        </el-form-item>
      </el-form>
    </div> -->

    <div class="chart-group">
      <div class="chart-card">
        <h3 class="chart-title">科室指标雷达图</h3>
        <div class="chart-box" ref="deptIndexRadarChart"></div>
      </div>
      <div class="chart-card">
        <h3 class="chart-title">月度趋势对比</h3>
        <div class="chart-box" ref="monthlyTrendCompareChart"></div>
      </div>
    </div>
  </div>
</template>

<script>
import * as echarts from 'echarts';

const indexCompareMockData = {
  originalData: {
    chartData: {
      deptIndexRadar: {
        indicator: [
          { name: '甲级病历率', max: 100 },
          { name: '缺陷整改率', max: 100 },
          { name: '首次病程完成率', max: 100 },
          { name: '出院记录完成率', max: 100 },
          { name: '手术记录规范率', max: 100 },
          { name: '医嘱与病程一致性', max: 100 },
        ],
        seriesData: [
          { name: '内科', data: [89.6, 88.1, 95.2, 96.8, 92.3, 90.5], itemStyle: { color: '#3b82f6' } },
          { name: '外科', data: [88.8, 92.8, 94.5, 95.9, 96.7, 89.2], itemStyle: { color: '#22c55e' } },
          { name: '妇产科', data: [92.5, 94.4, 97.2, 98.1, 95.3, 93.8], itemStyle: { color: '#eab308' } },
        ],
      },
      monthlyTrendCompare: {
        xAxis: ['1月', '2月', '3月', '4月', '5月', '6月'],
        seriesData: [
          { name: '内科', data: [85.2, 87.6, 89.1, 88.7, 90.2, 89.6], lineStyle: { color: '#3b82f6' } },
          { name: '外科', data: [83.5, 86.8, 88.9, 90.3, 91.7, 88.8], lineStyle: { color: '#22c55e' } },
          { name: '妇产科', data: [88.9, 90.2, 91.5, 92.1, 93.4, 92.5], lineStyle: { color: '#eab308' } },
        ],
      },
    },
  },
  filteredData: {},
};

Object.assign(indexCompareMockData.filteredData, indexCompareMockData.originalData);

export default {
  name: 'IndexCompare',
  props: {
    originalIndexData: {
      type: Object,
      default: () => indexCompareMockData.originalData,
    },
  },
  data() {
    return {
      chartInstances: {},
      filterForm: {
        deptList: ['internal', 'surgery', 'obstetrics'],
        indexType: '',
        comparePeriod: '6months',
      },
      chartData: {},
    };
  },
  watch: {
    originalIndexData: {
      deep: true,
      handler() {
        this.initData();
        this.initCharts();
      },
    },
    chartData: {
      deep: true,
      handler() {
        this.initCharts();
      },
    },
  },
  mounted() {
    this.initData();
    this.initCharts();
    window.addEventListener('resize', this.resizeCharts);
  },
  destroyed() {
    Object.values(this.chartInstances).forEach(instance => {
      instance.dispose();
    });
    window.removeEventListener('resize', this.resizeCharts);
  },
  methods: {
    initData() {
      this.chartData = indexCompareMockData.filteredData.chartData || this.originalIndexData.chartData;
    },

    handleFilterChange() {
      const filteredData = JSON.parse(JSON.stringify(this.originalIndexData.chartData));
      if (this.filterForm.deptList.length > 0) {
        const deptMap = {
          internal: '内科',
          surgery: '外科',
          obstetrics: '妇产科',
          pediatrics: '儿科',
          emergency: '急诊科',
        };
        const selectedDeptNames = this.filterForm.deptList.map(code => deptMap[code] || '');

        filteredData.deptIndexRadar.seriesData = filteredData.deptIndexRadar.seriesData.filter(item => selectedDeptNames.includes(item.name));
        filteredData.monthlyTrendCompare.seriesData = filteredData.monthlyTrendCompare.seriesData.filter(item => selectedDeptNames.includes(item.name));
      }

      this.chartData = filteredData;

      this.$nextTick(() => {
        this.initCharts();
      });
    },

    initCharts() {
      this.initDeptIndexRadarChart();
      this.initMonthlyTrendCompareChart();
    },

    initDeptIndexRadarChart() {
      const el = this.$refs.deptIndexRadarChart;
      if (!el) return;

      if (this.chartInstances.deptIndexRadarChart) {
        this.chartInstances.deptIndexRadarChart.dispose();
      }

      const chart = echarts.init(el);
      this.chartInstances.deptIndexRadarChart = chart;
      const radarData = this.chartData.deptIndexRadar || {
        indicator: [
          { name: '甲级病历率', max: 100 },
          { name: '缺陷整改率', max: 100 },
          { name: '首次病程完成率', max: 100 },
          { name: '出院记录完成率', max: 100 },
          { name: '手术记录规范率', max: 100 },
          { name: '医嘱与病程一致性', max: 100 },
        ],
        seriesData: [],
      };

      console.log('radarData', radarData);
      chart.setOption({
        tooltip: {
          trigger: 'item',
          formatter: '{b}<br/>{a}：{c}%',
        },
        legend: {
          data: radarData.seriesData.map(item => item.name),
          top: 10, 
          left: 'center',
        },
        radar: {
          indicator: radarData.indicator,
          radius: '60%', 
          center: ['50%', '55%'], 
          shape: 'polygon', 
          splitNumber: 5, 
          name: {
            textStyle: {
              color: '#333',
              fontSize: 12,
            },
          },
          splitLine: {
            lineStyle: {
              color: '#e8e8e8',
            },
          },
          splitArea: {
            areaStyle: {
              color: ['#f8f8f8', '#fff'],
            },
          },
          axisLine: {
            lineStyle: {
              color: '#ccc',
            },
          },
        },
        series: [
          {
            name: '科室指标对比',
            type: 'radar',
            data: radarData.seriesData.map(item => ({
              name: item.name,
              value: item.data, 
              itemStyle: item.itemStyle,
              areaStyle: {
                opacity: 0.2,
              },
              lineStyle: {
                width: 2,
              },
              symbol: 'circle',
              symbolSize: 6,
            })),
            emphasis: {
              areaStyle: {
                opacity: 0.4,
              },
            },
          },
        ],
      });
    },

    initMonthlyTrendCompareChart() {
      const el = this.$refs.monthlyTrendCompareChart;
      if (!el) return;

      if (this.chartInstances.monthlyTrendCompareChart) {
        this.chartInstances.monthlyTrendCompareChart.dispose();
      }

      const chart = echarts.init(el);
      this.chartInstances.monthlyTrendCompareChart = chart;
      const trendData = this.chartData.monthlyTrendCompare || indexCompareMockData.filteredData.chartData.monthlyTrendCompare;

      chart.setOption({
        tooltip: {
          trigger: 'axis',
          axisPointer: {
            type: 'cross',
          },
          formatter: '{b}<br/>{a}：{c} 分',
        },
        legend: {
          data: trendData.seriesData.map(item => item.name),
          top: 0,
        },
        grid: {
          left: '3%',
          right: '4%',
          bottom: '3%',
          top: '10%',
          containLabel: true,
        },
        xAxis: {
          type: 'category',
          boundaryGap: false,
          data: trendData.xAxis,
        },
        yAxis: {
          type: 'value',
          name: '综合质控得分',
          min: 80,
          max: 100,
          axisLabel: {
            formatter: '{value}',
          },
        },
        series: trendData.seriesData.map(item => ({
          name: item.name,
          type: 'line',
          data: item.data,
          smooth: true,
          lineStyle: item.lineStyle,
          areaStyle: {
            opacity: 0.1,
            color: item.lineStyle.color,
          },
          symbol: 'circle',
          symbolSize: 6,
          emphasis: {
            symbolSize: 10,
          },
        })),
      });
    },

    resizeCharts() {
      Object.values(this.chartInstances).forEach(instance => {
        instance.resize();
      });
    },
  },
};
</script>

<style lang="scss" scoped>
.index-compare-content {
  width: 100%;
}

.filter-bar {
  background: #fff;
  border-radius: 4px;
  padding: 16px 20px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.08);
  border: 1px solid #e8e8e8;
  margin-bottom: 20px;

  .filter-form {
    ::v-deep .el-form-item {
      margin-bottom: 0;
      label {
        color: #333;
        font-weight: 500;
      }
      .el-select {
        min-width: 180px;
      }
    }
  }
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
    }
  }
}

@media (max-width: 768px) {
  .chart-group {
    grid-template-columns: 1fr;
  }

  .filter-bar {
    .filter-form {
      ::v-deep .el-form-item {
        display: block;
        margin-bottom: 12px;
      }
    }
  }
}
</style>