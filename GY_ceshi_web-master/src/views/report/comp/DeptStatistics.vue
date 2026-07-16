<!-- 科室统计 -->
<template>
  <div class="dept-stat-content">
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
        <el-form-item label="科室名称：">
          <!-- <el-select v-model="filterForm.doctorTitle" placeholder="请选择医师职称" clearable @change="handleFilterChange">
            <el-option label="全部职称" value=""></el-option>
            <el-option label="主任医师" value="chief"></el-option>
            <el-option label="副主任医师" value="associate"></el-option>
            <el-option label="主治医师" value="attending"></el-option>
            <el-option label="住院医师" value="resident"></el-option>
          </el-select> -->
          <el-input v-model="filterForm.doctorTitle" placeholder="搜书科室名称"></el-input>
        </el-form-item>
      </el-form>
    </div>

    <div class="chart-group">
      <div class="chart-card">
        <h3 class="chart-title">科室病历等级分布</h3>
        <div class="chart-box" ref="deptRecordLevelChart"></div>
      </div>
      <div class="chart-card">
        <h3 class="chart-title">科室缺陷数量排名</h3>
        <div class="chart-box" ref="deptDefectRankChart"></div>
      </div>
    </div>

    <div class="table-card">
      <h3 class="table-title">科室质控详细统计</h3>
      <el-table :data="deptStatList" border stripe size="medium" style="width: 100%" :row-class-name="tableRowClassName">
        <el-table-column label="序号" align="center" width="80">
          <template slot-scope="scope">
            {{ scope.$index + 1 }}
          </template>
        </el-table-column>
        <el-table-column label="科室名称" align="left" min-width="200">
          <template slot-scope="scope">
            {{ scope.row.deptName }}
          </template>
        </el-table-column>
        <el-table-column label="病历数" align="center" width="140">
          <template slot-scope="scope">
            {{ scope.row.totalCases }}
          </template>
        </el-table-column>
        <el-table-column label="甲级数" align="center" width="140">
          <template slot-scope="scope">
            <span class="trend-green">{{ scope.row.levelA }}</span>
          </template>
        </el-table-column>
        <el-table-column label="甲级占比" align="center" width="140">
          <template slot-scope="scope">
            <span class="trend-green">{{ scope.row.levelARatio }}</span>
          </template>
        </el-table-column>
        <el-table-column label="乙级数" align="center" width="140">
          <template slot-scope="scope">
            <span class="trend-yellow">{{ scope.row.levelB }}</span>
          </template>
        </el-table-column>
        <el-table-column label="乙级占比" align="center" width="140">
          <template slot-scope="scope">
            <span class="trend-yellow">{{ scope.row.levelBRatio }}</span>
          </template>
        </el-table-column>
        <el-table-column label="丙级数" align="center" width="140">
          <template slot-scope="scope">
            <span class="trend-red">{{ scope.row.levelC }}</span>
          </template>
        </el-table-column>
        <el-table-column label="丙级占比" align="center" width="140">
          <template slot-scope="scope">
            <span class="trend-red">{{ scope.row.levelCRatio }}</span>
          </template>
        </el-table-column>
      </el-table>
    </div>
  </div>
</template>

<script>
import * as echarts from 'echarts';

const deptStatMockData = {
  originalData: {
    chartData: {
      deptRecordLevel: {
        yAxis: ['内科', '外科', '妇产科', '儿科', '急诊科', '骨科', '神经内科', '眼科'],
        seriesData: [
          { name: '甲级病历', data: [3890, 3750, 3560, 3420, 1890, 2100, 2250, 2080] },
          { name: '乙级病历', data: [876, 798, 690, 650, 450, 520, 460, 420] },
          { name: '丙级病历', data: [51, 94, 61, 93, 45, 80, 69, 25] },
        ],
      },
      deptDefectRank: {
        yAxis: ['内科', '外科', '妇产科', '儿科', '急诊科', '骨科', '神经内科', '眼科'],
        data: [896, 752, 638, 512, 489, 423, 367, 298],
      },
    },
    tableData: [
      {
        deptName: '内科',
        totalCases: 4817,
        levelA: 3890,
        levelARatio: '80.76%',
        levelB: 876,
        levelBRatio: '18.19%',
        levelC: 51,
        levelCRatio: '1.06%',
        deptType: 'clinical',
      },
      {
        deptName: '外科',
        totalCases: 4642,
        levelA: 3750,
        levelARatio: '80.78%',
        levelB: 798,
        levelBRatio: '17.19%',
        levelC: 94,
        levelCRatio: '2.03%',
        deptType: 'clinical',
      },
      {
        deptName: '妇产科',
        totalCases: 4311,
        levelA: 3560,
        levelARatio: '82.58%',
        levelB: 690,
        levelBRatio: '16.01%',
        levelC: 61,
        levelCRatio: '1.41%',
        deptType: 'clinical',
      },
      {
        deptName: '儿科',
        totalCases: 4163,
        levelA: 3420,
        levelARatio: '82.15%',
        levelB: 650,
        levelBRatio: '15.61%',
        levelC: 93,
        levelCRatio: '2.23%',
        deptType: 'clinical',
      },
      {
        deptName: '急诊科',
        totalCases: 2385,
        levelA: 1890,
        levelARatio: '79.25%',
        levelB: 450,
        levelBRatio: '18.87%',
        levelC: 45,
        levelCRatio: '1.89%',
        deptType: 'clinical',
      },
      {
        deptName: '骨科',
        totalCases: 2700,
        levelA: 2100,
        levelARatio: '77.78%',
        levelB: 520,
        levelBRatio: '19.26%',
        levelC: 80,
        levelCRatio: '2.96%',
        deptType: 'clinical',
      },
      {
        deptName: '神经内科',
        totalCases: 2779,
        levelA: 2250,
        levelARatio: '80.96%',
        levelB: 460,
        levelBRatio: '16.55%',
        levelC: 69,
        levelCRatio: '2.48%',
        deptType: 'clinical',
      },
      {
        deptName: '眼科',
        totalCases: 2525,
        levelA: 2080,
        levelARatio: '82.38%',
        levelB: 420,
        levelBRatio: '16.63%',
        levelC: 25,
        levelCRatio: '0.99%',
        deptType: 'clinical',
      },
    ],
  },
  filteredData: {},
};

Object.assign(deptStatMockData.filteredData, deptStatMockData.originalData);

export default {
  name: 'DeptStatistics',
  props: {
    originalDeptData: {
      type: Object,
      default: () => deptStatMockData.originalData,
    },
  },
  data() {
    return {
      chartInstances: {}, // 存储 ECharts 实例，防止内存泄漏
      filterForm: {
        // 3个过滤条件表单
        deptType: '', // 科室类型
        period: 'month', // 统计周期（默认本月）
        recordLevel: '', // 病历等级
      },
      deptStatList: [], // 筛选后表格数据
      chartData: {}, // 筛选后图表数据
    };
  },
  watch: {
    originalDeptData: {
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
      this.chartData = deptStatMockData.filteredData.chartData || this.originalDeptData.chartData;
      this.deptStatList = deptStatMockData.filteredData.tableData || this.originalDeptData.tableData;
    },

    tableRowClassName({ row, rowIndex }) {
      return 'table-row-hover';
    },

    handleFilterChange() {
      this.initData();
      this.$nextTick(() => {
        this.initCharts();
      });
    },

    initCharts() {
      this.initDeptRecordLevelChart();
      this.initDeptDefectRankChart();
    },

    initDeptRecordLevelChart() {
      const el = this.$refs.deptRecordLevelChart;
      if (!el) return;

      if (this.chartInstances.deptRecordLevelChart) {
        this.chartInstances.deptRecordLevelChart.dispose();
      }

      const chart = echarts.init(el);
      this.chartInstances.deptRecordLevelChart = chart;
      const levelData = this.chartData.deptRecordLevel || deptStatMockData.filteredData.chartData.deptRecordLevel;

      chart.setOption({
        tooltip: {
          trigger: 'axis',
          axisPointer: {
            type: 'shadow',
          },
          formatter: '{b}<br/>{a}: {c}<br/>总计: {total}',
        },
        legend: {
          data: ['甲级病历', '乙级病历', '丙级病历'],
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
          type: 'value',
          // name: '病历数量',
          name: '',
        },
        yAxis: {
          type: 'category',
          data: levelData.yAxis,
        },
        series: levelData.seriesData.map(item => ({
          name: item.name,
          type: 'bar',
          data: item.data,
          barWidth: '60%',
          stack: 'totalRecord',
          itemStyle: {
            color: item.name === '甲级病历' ? '#22c55e' : item.name === '乙级病历' ? '#eab308' : '#ef4444',
          },
        })),
      });
    },

    initDeptDefectRankChart() {
      const el = this.$refs.deptDefectRankChart;
      if (!el) return;

      if (this.chartInstances.deptDefectRankChart) {
        this.chartInstances.deptDefectRankChart.dispose();
      }

      const chart = echarts.init(el);
      this.chartInstances.deptDefectRankChart = chart;
      const rankData = this.chartData.deptDefectRank || deptStatMockData.filteredData.chartData.deptDefectRank;

      chart.setOption({
        tooltip: {
          trigger: 'axis',
          axisPointer: {
            type: 'shadow',
          },
        },
        grid: {
          left: '3%',
          right: '4%',
          bottom: '3%',
          top: '10%',
          containLabel: true,
        },
        xAxis: {
          type: 'value',
          // name: '缺陷病历数'
          name: '',
        },
        yAxis: {
          type: 'category',
          data: rankData.yAxis,
        },
        series: [
          {
            name: '缺陷数量',
            type: 'bar',
            data: rankData.data,
            itemStyle: {
              color: new echarts.graphic.LinearGradient(1, 0, 0, 0, [
                { offset: 0, color: '#3b82f6' },
                { offset: 1, color: '#60a5fa' },
              ]),
            },
            barWidth: '60%',
          },
        ],
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
.dept-stat-content {
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
      padding: 0 20px;
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

.trend-green {
  color: #22c55e !important;
  font-weight: 500;
}

.trend-yellow {
  color: #eab308 !important;
  font-weight: 500;
}

.trend-red {
  color: #ef4444 !important;
  font-weight: 500;
}

::v-deep .table-row-hover:hover {
  background-color: #e8f4fc !important;
}

::v-deep .el-table__header-wrapper th {
  font-weight: 500 !important;
  // background: #185da6 !important;
  // color: #fff !important;
  border: none !important;
}

::v-deep .el-table__body-wrapper td {
  border-bottom: 1px solid #e8e8e8 !important;
  color: #333 !important;
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

  .table-card {
    overflow-x: scroll;
  }
}
</style>