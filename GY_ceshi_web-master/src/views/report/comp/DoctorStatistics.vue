<!-- 医师统计 -->
<template>
  <div class="medical-stat-content">
    <div class="filter-bar">
      <el-form :inline="true" :model="filterForm" class="filter-form">
        <el-form-item label="所属科室：">
          <el-select v-model="filterForm.deptBelong" placeholder="请选择科室" clearable @change="handleFilterChange">
            <el-option label="全部科室" value=""></el-option>
            <el-option label="内科" value="internal"></el-option>
            <el-option label="外科" value="surgery"></el-option>
            <el-option label="妇产科" value="obstetrics"></el-option>
            <el-option label="儿科" value="pediatrics"></el-option>
          </el-select>
        </el-form-item>
        <el-form-item label="排序方式：">
          <el-select v-model="filterForm.period" placeholder="请选择排序方式" clearable @change="handleFilterChange">
            <el-option label="按缺陷占比排序" value="month"></el-option>
            <el-option label="按缺陷数量排序" value="quarter"></el-option>
            <el-option label="按病历数排序" value="year"></el-option>
          </el-select>
        </el-form-item>
        <el-form-item label="医师名称：">
          <!-- <el-select v-model="filterForm.doctorTitle" placeholder="请选择医师职称" clearable @change="handleFilterChange">
            <el-option label="全部职称" value=""></el-option>
            <el-option label="主任医师" value="chief"></el-option>
            <el-option label="副主任医师" value="associate"></el-option>
            <el-option label="主治医师" value="attending"></el-option>
            <el-option label="住院医师" value="resident"></el-option>
          </el-select> -->
          <el-input v-model="filterForm.doctorTitle" placeholder="请输入医师名称"></el-input>
        </el-form-item>
      </el-form>
    </div>

    <div class="chart-group">
      <div class="chart-card">
        <h3 class="chart-title">医师缺陷数量排名</h3>
        <div class="chart-box" ref="doctorDefectRankChart"></div>
      </div>
      <div class="chart-card">
        <h3 class="chart-title">医师质控评分分布</h3>
        <div class="chart-box" ref="doctorScoreDistChart"></div>
      </div>
    </div>
  </div>
</template>

<script>
import * as echarts from 'echarts';

const medicalStatMockData = {
  originalData: {
    chartData: {
      doctorDefectRank: {
        yAxis: [
          '张XX', '李XX', '王XX', '赵XX', '刘XX', 
          '陈XX', '杨XX', '黄XX', '周XX', '吴XX'
        ],
        data: [126, 98, 89, 76, 65, 58, 49, 36, 28, 15]
      },
      doctorScoreDist: {
        xAxis: ['60以下', '60-70', '70-80', '80-90', '90-100'],
        data: [8, 15, 32, 46, 29],
        colors: ['#ef4444', '#f97316', '#eab308', '#3b82f6', '#22c55e']
      }
    }
  },
  filteredData: {}
};

Object.assign(medicalStatMockData.filteredData, medicalStatMockData.originalData);

export default {
  name: 'MedicalStatistics',
  props: {
    originalMedicalData: {
      type: Object,
      default: () => medicalStatMockData.originalData
    }
  },
  data() {
    return {
      chartInstances: {}, 
      filterForm: { 
        deptBelong: '', 
        period: 'month', 
        doctorTitle: '' 
      },
      chartData: {} 
    };
  },
  watch: {
    originalMedicalData: {
      deep: true,
      handler() {
        this.initData();
        this.initCharts();
      }
    },
    chartData: {
      deep: true,
      handler() {
        this.initCharts();
      }
    }
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
      this.chartData = medicalStatMockData.filteredData.chartData || this.originalMedicalData.chartData;
    },

    handleFilterChange() {
      this.initData();
      this.$nextTick(() => {
        this.initCharts(); 
      });
    },

    initCharts() {
      this.initDoctorDefectRankChart();
      this.initDoctorScoreDistChart();
    },

    initDoctorDefectRankChart() {
      const el = this.$refs.doctorDefectRankChart;
      if (!el) return;

      if (this.chartInstances.doctorDefectRankChart) {
        this.chartInstances.doctorDefectRankChart.dispose();
      }

      const chart = echarts.init(el);
      this.chartInstances.doctorDefectRankChart = chart;
      const rankData = this.chartData.doctorDefectRank || medicalStatMockData.filteredData.chartData.doctorDefectRank;

      chart.setOption({
        tooltip: {
          trigger: 'axis',
          axisPointer: {
            type: 'shadow'
          },
          formatter: '{b}<br/>缺陷数量：{c} 条'
        },
        grid: {
          left: '3%',
          right: '4%',
          bottom: '3%',
          top: '10%',
          containLabel: true
        },
        xAxis: {
          type: 'value',
          // name: '缺陷数量',
          nameLocation: 'end',
          nameGap: 10
        },
        yAxis: {
          type: 'category',
          data: rankData.yAxis,
          axisLabel: {
            fontSize: 12
          }
        },
        series: [
          {
            name: '医师缺陷数',
            type: 'bar',
            data: rankData.data,
            barWidth: '60%',
            itemStyle: {
              color: new echarts.graphic.LinearGradient(1, 0, 0, 0, [
                { offset: 0, color: '#ef4444' },
                { offset: 1, color: '#f87171' }
              ])
            },
            emphasis: {
              itemStyle: {
                color: new echarts.graphic.LinearGradient(1, 0, 0, 0, [
                  { offset: 0, color: '#dc2626' },
                  { offset: 1, color: '#ef4444' }
                ])
              }
            }
          }
        ]
      });
    },

    initDoctorScoreDistChart() {
      const el = this.$refs.doctorScoreDistChart;
      if (!el) return;

      if (this.chartInstances.doctorScoreDistChart) {
        this.chartInstances.doctorScoreDistChart.dispose();
      }

      const chart = echarts.init(el);
      this.chartInstances.doctorScoreDistChart = chart;
      const scoreData = this.chartData.doctorScoreDist || medicalStatMockData.filteredData.chartData.doctorScoreDist;

      chart.setOption({
        tooltip: {
          trigger: 'axis',
          axisPointer: {
            type: 'shadow'
          },
          formatter: '{b}<br/>医师人数：{c} 人'
        },
        grid: {
          left: '3%',
          right: '4%',
          bottom: '3%',
          top: '10%',
          containLabel: true
        },
        xAxis: {
          type: 'category',
          data: scoreData.xAxis,
          axisLabel: {
            fontSize: 12
          }
        },
        yAxis: {
          type: 'value',
          name: '医师人数',
          nameLocation: 'end',
          nameGap: 10,
          min: 0
        },
        series: [
          {
            name: '医师人数',
            type: 'bar',
            data: scoreData.data,
            barWidth: '60%',
            itemStyle: {
              color: function(params) {
                return scoreData.colors[params.dataIndex] || '#3b82f6';
              }
            },
            emphasis: {
              itemStyle: {
                opacity: 0.9
              }
            }
          }
        ]
      });
    },

    resizeCharts() {
      Object.values(this.chartInstances).forEach(instance => {
        instance.resize();
      });
    }
  }
};
</script>

<style lang="scss" scoped>
.medical-stat-content {
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