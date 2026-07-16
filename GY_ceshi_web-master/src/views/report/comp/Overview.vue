<!-- 质控概括 -->
<template>
  <div class="overview-content">
    <div class="stats-grid">
      <StatsCard
        label="质控病历数（例）"
        :value="overallData.current.total_cases"
        showUnderline
        @value-click="value => handleClick('cases', value)"
      />
      <StatsCard
        label="缺陷病历数（例）"
        :value="overallData.current.defect_cases"
        valueColor="red"
        showUnderline
        @value-click="value => handleClick('defect', value)"
      />
      <StatsCard
        label="缺陷占比"
        :value="formatPercent(overallData.current.defect_ratio)"
        valueColor="red"
        :trend="[{ text: overallData.comparison.defect_ratio_yoy }, { text: overallData.comparison.defect_ratio_mom }]"
      />
      <StatsCard
        label="缺陷数量（个）"
        :value="overallData.current.avg_defects"
        @value-click="value => handleClick('defect-num', value)"
        valueColor="red"
        showUnderline
      />
    </div>

    <div class="chart-group">
      <div class="chart-card">
        <h3 class="chart-title">病历等级分布</h3>
        <div class="chart-box" ref="levelChart"></div>
      </div>
      <div class="chart-card">
        <h3 class="chart-title">质控趋势分析</h3>
        <div class="chart-box" ref="trendChart"></div>
      </div>
    </div>

    <div class="table-card">
      <h3 class="table-title">病历等级统计</h3>
      <el-table
        :data="gradeTableList"
        border
        stripe
        size="medium"
        style="width: 100%"
        :row-class-name="tableRowClassName"
      >
        <el-table-column label="统计项目" align="left">
          <template slot-scope="scope">{{ scope.row.name }}</template>
        </el-table-column>
        <el-table-column label="当期" align="center">
          <template slot-scope="scope">
            <span
              v-if="scope.row.name.includes('病历数')"
              class="value-text-blue value-underline"
              @click="handleClick('current', scope.row)"
            >{{ scope.row.current }}</span>
            <span v-else>{{ scope.row.current }}%</span>
          </template>
        </el-table-column>
        <el-table-column label="同比" align="center">
          <template slot-scope="scope">
            <div class="trend-item" v-if="scope.row.tb !== '-'">
              <svg
                class="trend-icon"
                viewBox="0 0 1024 1024"
                version="1.1"
                xmlns="http://www.w3.org/2000/svg"
                width="14"
                height="14"
              >
                <path
                  v-if="scope.row.tb.includes('+')"
                  d="M740.266667 262.4l-213.333333-213.333333C522.666667 44.8 518.4 42.666667 512 42.666667c-6.4 0-10.666667 2.133333-14.933333 6.4l-213.333333 213.333333C279.466667 266.666667 277.333333 270.933333 277.333333 277.333333c0 12.8 8.533333 21.333333 21.333333 21.333333 6.4 0 10.666667-2.133333 14.933333-6.4L490.666667 115.2 490.666667 960c0 12.8 8.533333 21.333333 21.333333 21.333333 12.8 0 21.333333-8.533333 21.333333-21.333333L533.333333 115.2l177.066667 177.066667c4.266667 4.266667 8.533333 6.4 14.933333 6.4 12.8 0 21.333333-8.533333 21.333333-21.333333C746.666667 270.933333 744.533333 266.666667 740.266667 262.4z"
                  fill="#22c55e"
                />
                <path
                  v-else
                  d="M495.616 726.528V0h32.768v726.528H660.48L512 1024l-148.48-297.472z"
                  fill="#ef4444"
                />
              </svg>
              <span
                :class="scope.row.tb.includes('+') ? 'trend-green' : 'trend-red'"
              >{{ scope.row.tb }}%</span>
            </div>
            <span v-else class="trend-green">-</span>
          </template>
        </el-table-column>

        <el-table-column label="环比" align="center">
          <template slot-scope="scope">
            <div class="trend-item" v-if="scope.row.hb !== '-'">
              <svg
                class="trend-icon"
                viewBox="0 0 1024 1024"
                version="1.1"
                xmlns="http://www.w3.org/2000/svg"
                width="14"
                height="14"
              >
                <path
                  v-if="scope.row.hb.includes('+')"
                  d="M740.266667 262.4l-213.333333-213.333333C522.666667 44.8 518.4 42.666667 512 42.666667c-6.4 0-10.666667 2.133333-14.933333 6.4l-213.333333 213.333333C279.466667 266.666667 277.333333 270.933333 277.333333 277.333333c0 12.8 8.533333 21.333333 21.333333 21.333333 6.4 0 10.666667-2.133333 14.933333-6.4L490.666667 115.2 490.666667 960c0 12.8 8.533333 21.333333 21.333333 21.333333 12.8 0 21.333333-8.533333 21.333333-21.333333L533.333333 115.2l177.066667 177.066667c4.266667 4.266667 8.533333 6.4 14.933333 6.4 12.8 0 21.333333-8.533333 21.333333-21.333333C746.666667 270.933333 744.533333 266.666667 740.266667 262.4z"
                  fill="#22c55e"
                />
                <path
                  v-else
                  d="M495.616 726.528V0h32.768v726.528H660.48L512 1024l-148.48-297.472z"
                  fill="#ef4444"
                />
              </svg>
              <span
                :class="scope.row.hb.includes('+') ? 'trend-green' : 'trend-red'"
              >{{ scope.row.hb }}%</span>
            </div>
            <span v-else class="trend-green">-</span>
          </template>
        </el-table-column>
        <el-table-column label="前一期" align="center">
          <template slot-scope="scope">
            <span
              v-if="scope.row.name.includes('病历数')"
              class="value-text-blue value-underline"
              @click="handleClick('period', scope.row)"
            >{{ scope.row.last_period }}</span>
            <span v-else>{{ scope.row.last_period }}%</span>
          </template>
        </el-table-column>
      </el-table>
    </div>
  </div>
</template>

<script>
import StatsCard from './StatsCard.vue';
import * as echarts from 'echarts';

export default {
  name: 'Overview',
  components: {
    StatsCard,
  },
  data() {
    return {
      queryParams: {},
      overViewData: {
        overall: {
          current: { total_cases: 0, defect_cases: 0, defect_ratio: 0, avg_defects: 0 },
          last_period: { time: '', total_cases: 0, defect_cases: 0, defect_ratio: 0 },
          comparison: { defect_ratio_yoy: '0.00', defect_ratio_mom: '0.00' },
        },
        case_level: {
          current: {
            grade_a: { count: 0, ratio: 0 },
            grade_b: { count: 0, ratio: 0 },
            grade_c: { count: 0, ratio: 0 },
          },
          last_period: {
            grade_a: { count: 0, ratio: 0 },
            grade_b: { count: 0, ratio: 0 },
            grade_c: { count: 0, ratio: 0 },
          },
          comparison: { grade_a_yoy: '0.00', grade_a_mom: '0.00' },
        },
        quality_indicators: {
          timeliness: [],
          major_examination: [],
          treatment_behavior: [],
          archiving_quality: [],
        },
      },
      levelMap: [
        { key: '甲', value: '甲' },
        { key: '乙', value: '乙' },
        { key: '丙', value: '丙' },
      ],
      chartInstances: {},
      chartData: {
        levelData: [],
        trendData: { xAxis: [], qualityData: [], defectData: [] },
      },
      statsList: [],
      gradeTableList: [],
    };
  },
  computed: {
    overallData() {
      return this.overViewData?.overall || { current: {}, comparison: {} };
    },
  },

  mounted() {
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
    formatPercent(value) {
      if (value === undefined || value === null || isNaN(Number(value))) {
        return '0.00%';
      }
      return `${Number(value)}%`;
    },
    initData(queryParams = {}, data) {
      console.log('queryParams', queryParams);
      this.queryParams = { ...queryParams };
      this.overViewData = { ...data };

      this.parseOverviewData();
      this.initCharts();
    },

    parseOverviewData() {
      const { overall, case_level, trend_analysis } = this.overViewData;
      const { current, last_period, comparison } = overall;
      const { grade_a, grade_b, grade_c } = case_level.current;
      const { grade_a: grade_a_last, grade_b: grade_b_last, grade_c: grade_c_last } = case_level.last_period;

      //图表数据解析
      this.chartData.levelData = [
        {
          value: grade_a.count,
          name: '甲级',
          labelValue: grade_a.ratio.toFixed(2) + '%',
        },
        {
          value: grade_b.count,
          name: '乙级',
          labelValue: grade_b.ratio.toFixed(2) + '%',
        },
        {
          value: grade_c.count,
          name: '丙级',
          labelValue: grade_c.ratio.toFixed(2) + '%',
        },
      ];
      // 图表数据
      this.chartData.trendData = {
        xAxis: trend_analysis.months,
        qualityData: trend_analysis.quality_cases,
        defectData: trend_analysis.defect_cases,
      };

      //解析表格数据
      this.gradeTableList = [
        { id: 1, name: '甲级病历数', current: grade_a.count, tb: comparison.defect_ratio_yoy, hb: comparison.defect_ratio_mom, last_period: grade_a_last.count },
        { id: 2, name: '甲级占比', current: grade_a.ratio, tb: '-', hb: '-', last_period: grade_a_last.ratio, last_period_time: last_period.time },
        { id: 3, name: '乙级病历数', current: grade_b.count, tb: '-', hb: '-', last_period: grade_b_last.count, last_period_time: last_period.time },
        { id: 4, name: '乙级占比', current: grade_b.ratio, tb: '-', hb: '-', last_period: grade_b_last.ratio, last_period_time: last_period.time },
        { id: 5, name: '丙级病历数', current: grade_c.count, tb: '-', hb: '-', last_period: grade_c_last.count, last_period_time: last_period.time },
        { id: 6, name: '丙级占比', current: grade_c.ratio, tb: '-', hb: '-', last_period: grade_c_last.ratio, last_period_time: last_period.time },
      ];
    },

    handleClick(tabKey, row) {
      console.log('handleClick', tabKey, row);
      const { startTime, endTime } = this.calculateDateRange();
      const { dep_id } = this.queryParams;
      const matched = this.levelMap.find(item => row.name?.includes(item.key));
      const record_levels = matched ? [matched.value] : [];
      switch (tabKey) {
        // 质控病历数
        case 'cases':
          this.$router.push({
            name: 'reportMedicalRecords',
            query: { startTime, endTime, dep_id, type: 'quality-control' },
          });
          break;
        // 缺陷病历数
        case 'defect':
          this.$router.push({
            name: 'reportMedicalRecords',
            query: { startTime, endTime, dep_id, type: 'overview-defect-control' },
          });
          break;
        case 'defect-num':
          this.$router.push({
            name: 'reportMedicalRecords',
            query: { startTime, endTime, dep_id, type: 'overview-defect-control-num' },
          });
          break;
        // 当期
        case 'current':
          this.$router.push({
            name: 'reportMedicalRecords',
            query: { startTime, endTime, record_levels, dep_id, type: 'current' },
          });
          break;
        // 前一期
        // case 'period':
        //   this.$router.push({
        //     name: 'reportMedicalRecords',
        //     query: { startTime, endTime, record_levels, dep_id, type: 'period' },
        //   });
        //   break;
        case 'period':
          const { startTime: periodStart, endTime: periodEnd } = this.calculateDateRange(true);
          this.$router.push({
            name: 'reportMedicalRecords',
            query: { startTime: periodStart, endTime: periodEnd, record_levels, dep_id, type: 'period' },
          });
          break;
        default:
          break;
      }
    },
    tableRowClassName({ row, rowIndex }) {
      return 'table-row-hover';
    },

    // calculateDateRange() {
    //   let startTime = '';
    //   let endTime = '';
    //   const { type = 'year', year = new Date().getFullYear().toString(), quarter = 1, month = '01' } = this.queryParams;
    //   switch (type) {
    //     case 'year': {
    //       startTime = `${year}0101`;
    //       endTime = `${year}1231`;
    //       break;
    //     }
    //     case 'quarter': {
    //       const quarterStartMonthMap = {
    //         1: '01', // 一季度：1-3月
    //         2: '04', // 二季度：4-6月
    //         3: '07', // 三季度：7-9月
    //         4: '10', // 四季度：10-12月
    //       };
    //       const quarterEndMonthMap = {
    //         1: '03',
    //         2: '06',
    //         3: '09',
    //         4: '12',
    //       };
    //       const quarterEndDayMap = {
    //         1: '31', // 3月31日
    //         2: '30', // 6月30日
    //         3: '30', // 9月30日
    //         4: '31', // 12月31日
    //       };
    //       const startMonth = quarterStartMonthMap[quarter] || '01';
    //       const endMonth = quarterEndMonthMap[quarter] || '12';
    //       const endDay = quarterEndDayMap[quarter] || '31';

    //       startTime = `${year}${startMonth}01`;
    //       endTime = `${year}${endMonth}${endDay}`;
    //       break;
    //     }
    //     case 'month': {
    //       startTime = `${year}${month}01`;
    //       const lastDay = new Date(Number(year), Number(month), 0).getDate();
    //       const lastDayStr = lastDay.toString().padStart(2, '0');
    //       endTime = `${year}${month}${lastDayStr}`;
    //       break;
    //     }
    //   }
    //   return { startTime, endTime };
    // },

    /**
     * 计算时间范围
     * @param {boolean} isLastPeriod 是否获取上一期（true=上一期，false=当期）
     */
    calculateDateRange(isLastPeriod = false) {
      const { type = 'year', year = new Date().getFullYear().toString(), quarter = '1', month = '01' } = this.queryParams;

      let currentYear = Number(year);
      let currentQuarter = Number(quarter);
      let currentMonth = Number(month);

      // 如果需要【上一期】，先把时间往前推一期
      if (isLastPeriod) {
        if (type === 'month') {
          currentMonth -= 1;
          if (currentMonth < 1) {
            currentMonth = 12;
            currentYear -= 1;
          }
        } else if (type === 'quarter') {
          currentQuarter -= 1;
          if (currentQuarter < 1) {
            currentQuarter = 4;
            currentYear -= 1;
          }
        } else if (type === 'year') {
          currentYear -= 1;
        }
      }

      let startTime = '';
      let endTime = '';

      // 格式化年份、月份、季度
      const formatYear = currentYear.toString();
      const formatMonth = currentMonth.toString().padStart(2, '0');
      const formatQuarter = currentQuarter;

      switch (type) {
        case 'year':
          startTime = `${formatYear}0101`;
          endTime = `${formatYear}1231`;
          break;

        case 'quarter': {
          const quarterStartMap = { 1: '01', 2: '04', 3: '07', 4: '10' };
          const quarterEndMap = { 1: '03', 2: '06', 3: '09', 4: '12' };
          const quarterDayMap = { 1: '31', 2: '30', 3: '30', 4: '31' };

          startTime = `${formatYear}${quarterStartMap[formatQuarter]}01`;
          endTime = `${formatYear}${quarterEndMap[formatQuarter]}${quarterDayMap[formatQuarter]}`;
          break;
        }

        case 'month': {
          startTime = `${formatYear}${formatMonth}01`;
          const lastDay = new Date(currentYear, currentMonth, 0).getDate();
          endTime = `${formatYear}${formatMonth}${lastDay.toString().padStart(2, '0')}`;
          break;
        }
      }

      return { startTime, endTime };
    },

    initCharts() {
      this.initLevelChart();
      this.initTrendChart();
    },

    initLevelChart() {
      const el = this.$refs.levelChart;
      if (!el) return;

      if (this.chartInstances.levelChart) {
        this.chartInstances.levelChart.dispose();
      }

      const chart = echarts.init(el);
      this.chartInstances.levelChart = chart;

      // chartDom.style.width = '100%';
      // chartDom.style.height = '100%';

      const option = {
        color: ['#2D8042', '#E6851A', '#C5350C'],
        tooltip: {
          trigger: 'item',
          formatter: '{b}: {c} ({d}%)',
          backgroundColor: 'rgba(255, 255, 255, 0.9)',
          borderColor: '#e6e6e6',
          borderWidth: 1,
          textStyle: {
            color: '#333',
            fontSize: 12,
          },
          padding: [8, 12],
          borderRadius: 6,
        },
        legend: {
          orient: 'vertical',
          right: '10%',
          top: '40px',
          margin: [0, 0, 0, 60],
          width: '40%',
          textStyle: {
            color: '#666',
            fontSize: 18,
            fontWeight: 500,
          },
          itemWidth: 30,
          itemHeight: 30,
          itemGap: 40,
          align: 'left',
          formatter: function (name) {
            const targetItem = option.series[0].data.find(item => item.name === name);
            return `${name} ${targetItem ? targetItem.labelValue : ''}`;
          },
        },
        series: [
          {
            name: 'Access From',
            type: 'pie',
            radius: ['40%', '55%'],
            center: ['35%', '35%'],
            top: '40px',
            avoidLabelOverlap: false,
            data: this.chartData.levelData,
            itemStyle: {
              borderRadius: 10,
              borderColor: '#fff',
              borderWidth: 2,
              emphasis: {
                shadowBlur: 15,
                shadowOffsetX: 0,
                shadowColor: 'rgba(0, 0, 0, 0.3)',
              },
            },
            label: {
              show: true,
              position: 'outside',
              fontSize: 18,
              color: '#333',
              formatter: function (params) {
                return `${params.name}: ${params.data.labelValue}`;
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
          },
        ],
        graphic: [
          {
            type: 'group',
            left: '29%',
            top: '40%',
            width: 120,
            height: 60,
            origin: [60, 30],
            anchor: [0.5, 0.5],
            children: [
              {
                type: 'text',
                left: 'center',
                top: '20%',
                style: { text: '病历数量', fontSize: 23, color: '#33333399', textAlign: 'center', fontWeight: '500' },
              },
              {
                type: 'text',
                left: 'center',
                top: '60%',
                style: {
                  text: this.chartData.levelData.reduce((sum, item) => {
                    const numValue = Number(item.value) || 0;
                    return sum + numValue;
                  }, 0),
                  fontSize: 24,
                  color: '#666',
                  textAlign: 'center',
                },
              },
            ],
          },
        ],

        grid: {
          left: '5%',
          right: '5%',
          top: '5%',
          bottom: '5%',
        },
        emphasis: {
          scale: true,
          scaleSize: 5,
        },
        animation: true,
        animationDuration: 1000,
        animationEasing: 'cubicOut',
        animationDelay: function (idx) {
          return idx * 50;
        },
      };
      chart.setOption(option);
    },

    initTrendChart() {
      const el = this.$refs.trendChart;
      if (!el) return;

      if (this.chartInstances.trendChart) {
        this.chartInstances.trendChart.dispose();
      }

      const chart = echarts.init(el);
      this.chartInstances.trendChart = chart;
      const trendData = this.chartData.trendData;

      chart.setOption({
        tooltip: {
          trigger: 'axis',
        },
        legend: {
          data: ['质控病历数', '缺陷病历数'],
        },
        grid: {
          left: '3%',
          right: '4%',
          bottom: '3%',
          containLabel: true,
        },
        xAxis: {
          type: 'category',
          boundaryGap: false,
          data: trendData.xAxis,
          axisLabel: {
            rotate: 45,
          },
        },
        yAxis: {
          type: 'value',
        },
        series: [
          {
            name: '质控病历数',
            type: 'line',
            stack: 'Total',
            data: trendData.qualityData,
          },
          {
            name: '缺陷病历数',
            type: 'line',
            stack: 'Total',
            data: trendData.defectData,
            itemStyle: {
              color: '#dc2626',
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
  },
};
</script>

<style lang="scss" scoped>
.overview-content {
  width: 100%;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 16px;
  margin-bottom: 20px;
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
}

.trend-red {
  color: #ef4444 !important;
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

::v-deep .table-row-hover:hover {
  background-color: #e8f4fc !important;
}

::v-deep .el-table__header-wrapper th {
  font-weight: 500 !important;
}

@media (max-width: 768px) {
  .chart-group {
    grid-template-columns: 1fr;
  }

  .stats-grid {
    grid-template-columns: 1fr 1fr;
  }
}
</style>