<!-- 质控指标 -->
<template>
  <div class="indicators-container">
    <div class="table-card">
      <h3 class="table-title">病历书写时效性指标</h3>
      <el-table :data="quality_indicators.timeliness" border stripe style="width: 100%" size="medium" :row-class-name="tableRowClassName">
        <el-table-column type="index" label="序号" align="center" width="80"></el-table-column>
        <el-table-column prop="name" label="指标名称" align="left"></el-table-column>
        <el-table-column prop="ratio" label="指标率" align="center">
          <template slot-scope="scope">
            <span>{{ scope.row.ratio }}%</span>
          </template>
        </el-table-column>
        <el-table-column label="分子" align="center">
          <template slot-scope="scope">
            <span class="value-text-blue value-underline" @click="handleClick('numerator-1', scope.row)">{{ scope.row.numerator }}</span>
          </template>
        </el-table-column>
        <el-table-column label="分母" align="center">
          <template slot-scope="scope">
            <span class="value-text-blue value-underline" @click="handleClick('denominator-1', scope.row)">{{ scope.row.denominator }}</span>
          </template>
        </el-table-column>
        <el-table-column label="上月指标率" align="center">
          <template slot-scope="scope">
            <span>{{ scope.row.last_ratio }}%</span>
          </template>
        </el-table-column>
        <el-table-column label="同比" align="center">
          <template slot-scope="scope">
            <div class="trend-item" v-if="scope.row.yoy !== '-'">
              <svg class="trend-icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" width="14" height="14">
                <path
                  v-if="scope.row.yoy.includes('+')"
                  d="M740.266667 262.4l-213.333333-213.333333C522.666667 44.8 518.4 42.666667 512 42.666667c-6.4 0-10.666667 2.133333-14.933333 6.4l-213.333333 213.333333C279.466667 266.666667 277.333333 270.933333 277.333333 277.333333c0 12.8 8.533333 21.333333 21.333333 21.333333 6.4 0 10.666667-2.133333 14.933333-6.4L490.666667 115.2 490.666667 960c0 12.8 8.533333 21.333333 21.333333 21.333333 12.8 0 21.333333-8.533333 21.333333-21.333333L533.333333 115.2l177.066667 177.066667c4.266667 4.266667 8.533333 6.4 14.933333 6.4 12.8 0 21.333333-8.533333 21.333333-21.333333C746.666667 270.933333 744.533333 266.666667 740.266667 262.4z"
                  fill="#22c55e"
                ></path>
                <path v-else d="M495.616 726.528V0h32.768v726.528H660.48L512 1024l-148.48-297.472z" fill="#ef4444"></path>
              </svg>
              <span :class="scope.row.yoy.includes('+') ? 'trend-green' : 'trend-red'">{{ scope.row.yoy }}%</span>
            </div>
            <span v-else class="trend-green">-</span>
          </template>
        </el-table-column>

        <el-table-column label="环比" align="center">
          <template slot-scope="scope">
            <div class="trend-item" v-if="scope.row.mom !== '-'">
              <svg class="trend-icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" width="14" height="14">
                <path
                  v-if="scope.row.mom.includes('+')"
                  d="M740.266667 262.4l-213.333333-213.333333C522.666667 44.8 518.4 42.666667 512 42.666667c-6.4 0-10.666667 2.133333-14.933333 6.4l-213.333333 213.333333C279.466667 266.666667 277.333333 270.933333 277.333333 277.333333c0 12.8 8.533333 21.333333 21.333333 21.333333 6.4 0 10.666667-2.133333 14.933333-6.4L490.666667 115.2 490.666667 960c0 12.8 8.533333 21.333333 21.333333 21.333333 12.8 0 21.333333-8.533333 21.333333-21.333333L533.333333 115.2l177.066667 177.066667c4.266667 4.266667 8.533333 6.4 14.933333 6.4 12.8 0 21.333333-8.533333 21.333333-21.333333C746.666667 270.933333 744.533333 266.666667 740.266667 262.4z"
                  fill="#22c55e"
                ></path>
                <path v-else d="M495.616 726.528V0h32.768v726.528H660.48L512 1024l-148.48-297.472z" fill="#ef4444"></path>
              </svg>
              <span :class="scope.row.mom.includes('+') ? 'trend-green' : 'trend-red'">{{ scope.row.mom }}%</span>
            </div>
            <span v-else class="trend-green">-</span>
          </template>
        </el-table-column>
      </el-table>
      <div class="chart-container" ref="timelinessChart" v-if="quality_indicators.timeliness.length > 0"></div>
    </div>

    <div class="table-card">
      <h3 class="table-title">重大检查记录完整性</h3>
      <el-table :data="quality_indicators.major_examination" border stripe style="width: 100%" size="medium" :row-class-name="tableRowClassName">
        <el-table-column type="index" label="序号" align="center" width="80"></el-table-column>
        <el-table-column prop="name" label="指标名称" align="left"></el-table-column>
        <el-table-column prop="ratio" label="指标率" align="center">
          <template slot-scope="scope">
            <span>{{ scope.row.ratio }}%</span>
          </template>
        </el-table-column>
        <el-table-column label="分子" align="center">
          <template slot-scope="scope">
            <span class="value-text-blue value-underline" @click="handleClick('numerator-2', scope.row)">{{ scope.row.numerator }}</span>
          </template>
        </el-table-column>
        <el-table-column label="分母" align="center">
          <template slot-scope="scope">
            <span class="value-text-blue value-underline" @click="handleClick('denominator-2', scope.row)">{{ scope.row.denominator }}</span>
          </template>
        </el-table-column>
        <el-table-column label="上月指标率" align="center">
          <template slot-scope="scope">
            <span>{{ scope.row.last_ratio }}%</span>
          </template>
        </el-table-column>
        <el-table-column label="同比" align="center">
          <template slot-scope="scope">
            <div class="trend-item" v-if="scope.row.yoy !== '-'">
              <svg class="trend-icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" width="14" height="14">
                <path
                  v-if="scope.row.yoy.includes('+')"
                  d="M740.266667 262.4l-213.333333-213.333333C522.666667 44.8 518.4 42.666667 512 42.666667c-6.4 0-10.666667 2.133333-14.933333 6.4l-213.333333 213.333333C279.466667 266.666667 277.333333 270.933333 277.333333 277.333333c0 12.8 8.533333 21.333333 21.333333 21.333333 6.4 0 10.666667-2.133333 14.933333-6.4L490.666667 115.2 490.666667 960c0 12.8 8.533333 21.333333 21.333333 21.333333 12.8 0 21.333333-8.533333 21.333333-21.333333L533.333333 115.2l177.066667 177.066667c4.266667 4.266667 8.533333 6.4 14.933333 6.4 12.8 0 21.333333-8.533333 21.333333-21.333333C746.666667 270.933333 744.533333 266.666667 740.266667 262.4z"
                  fill="#22c55e"
                ></path>
                <path v-else d="M495.616 726.528V0h32.768v726.528H660.48L512 1024l-148.48-297.472z" fill="#ef4444"></path>
              </svg>
              <span :class="scope.row.yoy.includes('+') ? 'trend-green' : 'trend-red'">{{ scope.row.yoy }}%</span>
            </div>
            <span v-else class="trend-green">-</span>
          </template>
        </el-table-column>

        <el-table-column label="环比" align="center">
          <template slot-scope="scope">
            <div class="trend-item" v-if="scope.row.mom !== '-'">
              <svg class="trend-icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" width="14" height="14">
                <path
                  v-if="scope.row.mom.includes('+')"
                  d="M740.266667 262.4l-213.333333-213.333333C522.666667 44.8 518.4 42.666667 512 42.666667c-6.4 0-10.666667 2.133333-14.933333 6.4l-213.333333 213.333333C279.466667 266.666667 277.333333 270.933333 277.333333 277.333333c0 12.8 8.533333 21.333333 21.333333 21.333333 6.4 0 10.666667-2.133333 14.933333-6.4L490.666667 115.2 490.666667 960c0 12.8 8.533333 21.333333 21.333333 21.333333 12.8 0 21.333333-8.533333 21.333333-21.333333L533.333333 115.2l177.066667 177.066667c4.266667 4.266667 8.533333 6.4 14.933333 6.4 12.8 0 21.333333-8.533333 21.333333-21.333333C746.666667 270.933333 744.533333 266.666667 740.266667 262.4z"
                  fill="#22c55e"
                ></path>
                <path v-else d="M495.616 726.528V0h32.768v726.528H660.48L512 1024l-148.48-297.472z" fill="#ef4444"></path>
              </svg>
              <span :class="scope.row.mom.includes('+') ? 'trend-green' : 'trend-red'">{{ scope.row.mom }}%</span>
            </div>
            <span v-else class="trend-green">-</span>
          </template>
        </el-table-column>
      </el-table>

      <div class="chart-container" ref="examinationChart" v-if="quality_indicators.major_examination.length > 0"></div>
    </div>

    <div class="table-card">
      <h3 class="table-title">诊疗行为记录完整性</h3>
      <el-table :data="quality_indicators.treatment_behavior" border stripe style="width: 100%" size="medium" :row-class-name="tableRowClassName">
        <el-table-column type="index" label="序号" align="center" width="80"></el-table-column>
        <el-table-column prop="name" label="指标名称" align="left"></el-table-column>
        <el-table-column prop="ratio" label="指标率" align="center">
          <template slot-scope="scope">
            <span>{{ scope.row.ratio }}%</span>
          </template>
        </el-table-column>
        <el-table-column label="分子" align="center">
          <template slot-scope="scope">
            <span class="value-text-blue value-underline" @click="handleClick('numerator-3', scope.row)">{{ scope.row.numerator }}</span>
          </template>
        </el-table-column>
        <el-table-column label="分母" align="center">
          <template slot-scope="scope">
            <span class="value-text-blue value-underline" @click="handleClick('denominator-3', scope.row)">{{ scope.row.denominator }}</span>
          </template>
        </el-table-column>
        <el-table-column label="上月指标率" align="center">
          <template slot-scope="scope">
            <span>{{ scope.row.last_ratio }}%</span>
          </template>
        </el-table-column>
        <el-table-column label="同比" align="center">
          <template slot-scope="scope">
            <div class="trend-item" v-if="scope.row.yoy !== '-'">
              <svg class="trend-icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" width="14" height="14">
                <path
                  v-if="scope.row.yoy.includes('+')"
                  d="M740.266667 262.4l-213.333333-213.333333C522.666667 44.8 518.4 42.666667 512 42.666667c-6.4 0-10.666667 2.133333-14.933333 6.4l-213.333333 213.333333C279.466667 266.666667 277.333333 270.933333 277.333333 277.333333c0 12.8 8.533333 21.333333 21.333333 21.333333 6.4 0 10.666667-2.133333 14.933333-6.4L490.666667 115.2 490.666667 960c0 12.8 8.533333 21.333333 21.333333 21.333333 12.8 0 21.333333-8.533333 21.333333-21.333333L533.333333 115.2l177.066667 177.066667c4.266667 4.266667 8.533333 6.4 14.933333 6.4 12.8 0 21.333333-8.533333 21.333333-21.333333C746.666667 270.933333 744.533333 266.666667 740.266667 262.4z"
                  fill="#22c55e"
                ></path>
                <path v-else d="M495.616 726.528V0h32.768v726.528H660.48L512 1024l-148.48-297.472z" fill="#ef4444"></path>
              </svg>
              <span :class="scope.row.yoy.includes('+') ? 'trend-green' : 'trend-red'">{{ scope.row.yoy }}%</span>
            </div>
            <span v-else class="trend-green">-</span>
          </template>
        </el-table-column>

        <el-table-column label="环比" align="center">
          <template slot-scope="scope">
            <div class="trend-item" v-if="scope.row.mom !== '-'">
              <svg class="trend-icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" width="14" height="14">
                <path
                  v-if="scope.row.mom.includes('+')"
                  d="M740.266667 262.4l-213.333333-213.333333C522.666667 44.8 518.4 42.666667 512 42.666667c-6.4 0-10.666667 2.133333-14.933333 6.4l-213.333333 213.333333C279.466667 266.666667 277.333333 270.933333 277.333333 277.333333c0 12.8 8.533333 21.333333 21.333333 21.333333 6.4 0 10.666667-2.133333 14.933333-6.4L490.666667 115.2 490.666667 960c0 12.8 8.533333 21.333333 21.333333 21.333333 12.8 0 21.333333-8.533333 21.333333-21.333333L533.333333 115.2l177.066667 177.066667c4.266667 4.266667 8.533333 6.4 14.933333 6.4 12.8 0 21.333333-8.533333 21.333333-21.333333C746.666667 270.933333 744.533333 266.666667 740.266667 262.4z"
                  fill="#22c55e"
                ></path>
                <path v-else d="M495.616 726.528V0h32.768v726.528H660.48L512 1024l-148.48-297.472z" fill="#ef4444"></path>
              </svg>
              <span :class="scope.row.mom.includes('+') ? 'trend-green' : 'trend-red'">{{ scope.row.mom }}%</span>
            </div>
            <span v-else class="trend-green">-</span>
          </template>
        </el-table-column>
      </el-table>
      <div class="chart-container" ref="treatmentChart" v-if="quality_indicators.treatment_behavior.length > 0"></div>
    </div>

    <div class="table-card">
      <h3 class="table-title">病历归档质量指标</h3>
      <el-table :data="quality_indicators.archiving_quality" border stripe style="width: 100%" size="medium" :row-class-name="tableRowClassName">
        <el-table-column type="index" label="序号" align="center" width="80"></el-table-column>
        <el-table-column prop="name" label="指标名称" align="left"></el-table-column>
        <el-table-column prop="ratio" label="指标率" align="center">
          <template slot-scope="scope">
            <span>{{ scope.row.ratio }}%</span>
          </template>
        </el-table-column>
        <el-table-column label="分子" align="center">
          <template slot-scope="scope">
            <span class="value-text-blue value-underline" @click="handleClick('numerator-4', scope.row)">{{ scope.row.numerator }}</span>
          </template>
        </el-table-column>
        <el-table-column label="分母" align="center">
          <template slot-scope="scope">
            <span class="value-text-blue value-underline" @click="handleClick('denominator-4', scope.row)">{{ scope.row.denominator }}</span>
          </template>
        </el-table-column>
        <el-table-column label="上月指标率" align="center">
          <template slot-scope="scope">
            <span>{{ scope.row.last_ratio }}%</span>
          </template>
        </el-table-column>
        <el-table-column label="同比" align="center">
          <template slot-scope="scope">
            <div class="trend-item" v-if="scope.row.yoy !== '-'">
              <svg class="trend-icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" width="14" height="14">
                <path
                  v-if="scope.row.yoy.includes('+')"
                  d="M740.266667 262.4l-213.333333-213.333333C522.666667 44.8 518.4 42.666667 512 42.666667c-6.4 0-10.666667 2.133333-14.933333 6.4l-213.333333 213.333333C279.466667 266.666667 277.333333 270.933333 277.333333 277.333333c0 12.8 8.533333 21.333333 21.333333 21.333333 6.4 0 10.666667-2.133333 14.933333-6.4L490.666667 115.2 490.666667 960c0 12.8 8.533333 21.333333 21.333333 21.333333 12.8 0 21.333333-8.533333 21.333333-21.333333L533.333333 115.2l177.066667 177.066667c4.266667 4.266667 8.533333 6.4 14.933333 6.4 12.8 0 21.333333-8.533333 21.333333-21.333333C746.666667 270.933333 744.533333 266.666667 740.266667 262.4z"
                  fill="#22c55e"
                ></path>
                <path v-else d="M495.616 726.528V0h32.768v726.528H660.48L512 1024l-148.48-297.472z" fill="#ef4444"></path>
              </svg>
              <span :class="scope.row.yoy.includes('+') ? 'trend-green' : 'trend-red'">{{ scope.row.yoy }}%</span>
            </div>
            <span v-else class="trend-green">-</span>
          </template>
        </el-table-column>

        <el-table-column label="环比" align="center">
          <template slot-scope="scope">
            <div class="trend-item" v-if="scope.row.mom !== '-'">
              <svg class="trend-icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" width="14" height="14">
                <path
                  v-if="scope.row.mom.includes('+')"
                  d="M740.266667 262.4l-213.333333-213.333333C522.666667 44.8 518.4 42.666667 512 42.666667c-6.4 0-10.666667 2.133333-14.933333 6.4l-213.333333 213.333333C279.466667 266.666667 277.333333 270.933333 277.333333 277.333333c0 12.8 8.533333 21.333333 21.333333 21.333333 6.4 0 10.666667-2.133333 14.933333-6.4L490.666667 115.2 490.666667 960c0 12.8 8.533333 21.333333 21.333333 21.333333 12.8 0 21.333333-8.533333 21.333333-21.333333L533.333333 115.2l177.066667 177.066667c4.266667 4.266667 8.533333 6.4 14.933333 6.4 12.8 0 21.333333-8.533333 21.333333-21.333333C746.666667 270.933333 744.533333 266.666667 740.266667 262.4z"
                  fill="#22c55e"
                ></path>
                <path v-else d="M495.616 726.528V0h32.768v726.528H660.48L512 1024l-148.48-297.472z" fill="#ef4444"></path>
              </svg>
              <span :class="scope.row.mom.includes('+') ? 'trend-green' : 'trend-red'">{{ scope.row.mom }}%</span>
            </div>
            <span v-else class="trend-green">-</span>
          </template>
        </el-table-column>
      </el-table>

      <div class="chart-container" ref="archivingChart" v-if="quality_indicators.archiving_quality.length > 0"></div>
    </div>
  </div>
</template>

<script>
import * as echarts from 'echarts';

export default {
  name: 'Indicators',
  data() {
    return {
      quality_indicators: {
        timeliness: [],
        major_examination: [],
        treatment_behavior: [],
        archiving_quality: [],
      },
      chartInstances: {
        timeliness: null,
        examination: null,
        treatment: null,
        archiving: null,
      },
      searchParams: {},
    };
  },
  beforeDestroy() {
    // 清理图表实例和监听
    Object.values(this.chartInstances).forEach(chart => chart && chart.dispose());
    window.removeEventListener('resize', this.resizeCharts);
  },
  methods: {
    initData(queryParams = {}, data) {
      this.searchParams = { ...queryParams };
      this.quality_indicators = { ...data };

      this.parseOverviewData();
      this.initCharts();
    },
    parseOverviewData() {},

    initCharts() {
      this.initTimelinessChart();
      this.initExaminationChart();
      this.initTreatmentChart();
      this.initArchivingChart();
      window.addEventListener('resize', this.resizeCharts);
    },
    calculateDateRange() {
      let startTime = '';
      let endTime = '';
      const { type = 'year', year = new Date().getFullYear().toString(), quarter = 1, month = '01' } = this.searchParams;
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

    handleClick(tabKey, row) {
      const { startTime, endTime } = this.calculateDateRange();
      switch (tabKey) {
        case 'numerator-1':
        case 'numerator-2':
        case 'numerator-3':
        case 'numerator-4':
          //query: { startTime, endTime, year: row.year, month, AAC11N: this.formInline.AAC11N, AEE03: this.formInline.AEE03, status, catalog: this.indexData.url },
          this.$router.push({
            path: '/majorIndexDetail',
            query: { startTime, endTime, year: this.searchParams.year, AAC11N: '', catalog: row.category, status: 1 },
          });
          break;
        case 'denominator-1':
        case 'denominator-2':
        case 'denominator-3':
        case 'denominator-4':
          this.$router.push({
            path: '/majorIndexDetail',
            query: { startTime, endTime, year: this.searchParams.year, AAC11N: '', catalog: row.category, status: 0 },
          });
          break;

        default:
          break;
      }
    },

    initTimelinessChart() {
      const chartDom = this.$refs.timelinessChart;
      if (!chartDom) return;
      if (this.chartInstances.timeliness) this.chartInstances.timeliness.dispose();
      const chart = echarts.init(chartDom);
      this.chartInstances.timeliness = chart;
      const data = this.quality_indicators.timeliness;
      const xAxisData = data.map(item => item.name);
      const currentRatio = data.map(item => Number(item.ratio));
      const lastRatio = data.map(item => Number(item.last_ratio));
      const option = {
        tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' } },
        legend: {
          data: ['本期指标率', '上期指标率'],
          top: 0,
          left: 'center',
          right: 'auto',
          width: 'auto',
          itemWidth: 35,
          itemHeight: 20,
          itemGap: 40,
          textStyle: {
            fontSize: 12,
            padding: [0, 0, 0, 5],
          },
          align: 'left',
          orient: 'horizontal',
        },
        grid: { left: '10%', right: '10%', bottom: '15%', top: '15%', containLabel: true },
        xAxis: { type: 'value', name: '', max: 100, min: 0 },
        yAxis: { type: 'category', data: xAxisData, axisLabel: { fontSize: 12 } },
        series: [
          { name: '本期指标率', type: 'bar', data: currentRatio, itemStyle: { color: '#185da6' }, label: { show: true, position: 'right', formatter: '{c}%' } },
          { name: '上期指标率', type: 'bar', data: lastRatio, itemStyle: { color: '#8392a5' }, label: { show: true, position: 'right', formatter: '{c}%' } },
        ],
      };
      chart.setOption(option);
    },

    initExaminationChart() {
      const chartDom = this.$refs.examinationChart;
      if (!chartDom) return;
      if (this.chartInstances.examination) this.chartInstances.examination.dispose();
      const chart = echarts.init(chartDom);
      this.chartInstances.examination = chart;
      const data = this.quality_indicators.major_examination;
      const xAxisData = data.map(item => item.name);
      const currentRatio = data.map(item => Number(item.ratio));
      const lastRatio = data.map(item => Number(item.last_ratio));
      const option = {
        tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' } },
        legend: {
          data: ['本期指标率', '上期指标率'],
          top: 0,
          left: 'center',
          right: 'auto',
          width: 'auto',
          itemWidth: 35,
          itemHeight: 20,
          itemGap: 45,
          textStyle: {
            fontSize: 12,
            padding: [0, 0, 0, 5],
          },
          align: 'left',
          orient: 'horizontal',
        },
        grid: { left: '10%', right: '10%', bottom: '15%', top: '15%', containLabel: true },
        xAxis: { type: 'value', name: '', max: 100, min: 0 },
        yAxis: { type: 'category', data: xAxisData, axisLabel: { fontSize: 12 } },
        series: [
          { name: '本期指标率', type: 'bar', data: currentRatio, itemStyle: { color: '#185da6' }, label: { show: true, position: 'right', formatter: '{c}%' } },
          { name: '上期指标率', type: 'bar', data: lastRatio, itemStyle: { color: '#8392a5' }, label: { show: true, position: 'right', formatter: '{c}%' } },
        ],
      };
      chart.setOption(option);
    },

    initTreatmentChart() {
      const chartDom = this.$refs.treatmentChart;
      if (!chartDom) return;
      if (this.chartInstances.treatment) this.chartInstances.treatment.dispose();
      const chart = echarts.init(chartDom);
      this.chartInstances.treatment = chart;
      const data = this.quality_indicators.treatment_behavior;
      const xAxisData = data.map(item => item.name);
      const currentRatio = data.map(item => Number(item.ratio));
      const lastRatio = data.map(item => Number(item.last_ratio));
      const option = {
        tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' } },
        legend: {
          data: ['本期指标率', '上期指标率'],
          top: 0,
          left: 'center',
          right: 'auto',
          width: 'auto',
          itemWidth: 35,
          itemHeight: 20,
          itemGap: 45,
          textStyle: {
            fontSize: 12,
            padding: [0, 0, 0, 5],
          },
          align: 'left',
          orient: 'horizontal',
        },
        grid: { left: '10%', right: '10%', bottom: '15%', top: '15%', containLabel: true },
        xAxis: { type: 'value', name: '', max: 100, min: 0 },
        yAxis: { type: 'category', data: xAxisData, axisLabel: { fontSize: 12 } },
        series: [
          { name: '本期指标率', type: 'bar', data: currentRatio, itemStyle: { color: '#185da6' }, label: { show: true, position: 'right', formatter: '{c}%' } },
          { name: '上期指标率', type: 'bar', data: lastRatio, itemStyle: { color: '#8392a5' }, label: { show: true, position: 'right', formatter: '{c}%' } },
        ],
      };
      chart.setOption(option);
    },

    initArchivingChart() {
      const chartDom = this.$refs.archivingChart;
      if (!chartDom) return;
      if (this.chartInstances.archiving) this.chartInstances.archiving.dispose();
      const chart = echarts.init(chartDom);
      this.chartInstances.archiving = chart;
      const data = this.quality_indicators.archiving_quality; // 切换数据源
      const xAxisData = data.map(item => item.name);
      const currentRatio = data.map(item => Number(item.ratio));
      const lastRatio = data.map(item => Number(item.last_ratio));
      const option = {
        tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' } },
        legend: {
          data: ['本期指标率', '上期指标率'],
          top: 0,
          left: 'center',
          right: 'auto',
          width: 'auto',
          itemWidth: 35,
          itemHeight: 20,
          itemGap: 45,
          textStyle: {
            fontSize: 12,
            padding: [0, 0, 0, 5],
          },
          align: 'left',
          orient: 'horizontal',
        },
        grid: { left: '10%', right: '10%', bottom: '15%', top: '15%', containLabel: true },
        xAxis: { type: 'value', name: '', max: 100, min: 0 },
        yAxis: { type: 'category', data: xAxisData, axisLabel: { fontSize: 12 } },
        series: [
          { name: '本期指标率', type: 'bar', data: currentRatio, itemStyle: { color: '#185da6' }, label: { show: true, position: 'right', formatter: '{c}%' } },
          { name: '上期指标率', type: 'bar', data: lastRatio, itemStyle: { color: '#8392a5' }, label: { show: true, position: 'right', formatter: '{c}%' } },
        ],
      };
      chart.setOption(option);
    },

    resizeCharts() {
      Object.values(this.chartInstances).forEach(chart => {
        if (chart) chart.resize();
      });
    },
    tableRowClassName({ row, rowIndex }) {
      return 'table-row-hover';
    },
  },
};
</script>

<style lang="scss" scoped>
.indicators-container {
  width: 100%;
  display: flex;
  flex-direction: column;
  gap: 20px;
}
.chart-container {
  width: 100%;
  height: 500px;
  margin-top: 20px;
}

.table-card {
  background: #fff;
  padding: 20px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.08);
  border: 1px solid #e8e8e8;
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

.value-text-blue {
  color: #185da6;
  cursor: pointer;
}

.value-underline {
  text-decoration: underline;
  text-underline-offset: 4px;
  text-decoration-color: currentColor;
  text-decoration-thickness: 2px;
}

.trend-red {
  color: #ef4444 !important;
}

::v-deep .table-row-hover:hover {
  background-color: #e8f4fc !important;
}

::v-deep .el-table__header-wrapper th {
  // color: #fff !important;
  font-weight: 500 !important;
}

@media (max-width: 768px) {
  .table-card {
    padding: 16px;
  }

  .table-title {
    font-size: 14px;
  }
}
</style>