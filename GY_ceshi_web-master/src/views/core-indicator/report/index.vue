<!-- 医疗质量报告 -->
<template>
  <div class="report">
    <div class="section section--first">
      <div class="filter-bar">
        <!-- <button class="filter-btn" @click="isFilterPanelShow = !isFilterPanelShow">
          <span class="filter-icon">⚙️</span>
          筛选
          <span class="toggle-arrow" :class="{ active: isFilterPanelShow }">▼</span>
        </button> -->
        <!-- <div class="export-bar">
          <el-date-picker v-model="selectMonth" @change="selectMonthChange" type="month" placeholder="选择月" format="yyyy年MM月" value-format="yyyy年MM月"></el-date-picker>
          <el-button class="export-btn" @click="exportReportHandle">导出报告</el-button>
        </div> -->
      </div>

      <FilterPanel v-show="isFilterPanelShow" ref="filterPanelRef" @query="handleQuery" @reset="handleReset" @update-display="updateDisplay" @update-params="updateParams" />
    </div>

    <div class="section section--second">
      <ReportHeader :basic-info="basic_info" :isShow="0" />
      <TabNav :tabs="tabList" :active-tab="activeTab" @tab-change="handleTabChange" />
      <div class="content-wrap">
        <div v-show="activeTab === 'indexCoreReport'" class="content-item">
          <IndexCoreReport ref="indexCoreReportRef" />
        </div>
        <div v-show="activeTab === 'department'" class="content-item">
          <DeptStatistics ref="departmentRef" />
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import moment from 'moment/moment';

import FilterPanel from '@/views/report/comp/FilterPanel.vue';
import ReportHeader from '@/views/report/comp/ReportHeader.vue';
import TabNav from '@/views/report/comp/TabNav.vue';
import DeptStatistics from './comp/DeptStatisticsNew.vue';
import IndexCoreReport from './comp/IndexCoreReport.vue';

import { exportReportStatistics } from '@/api/excel';
const getCurMonthFormat = () => {
  const now = new Date();
  const year = now.getFullYear();
  const month = (now.getMonth() + 1).toString().padStart(2, '0');
  return `${year}年${month}月`;
};
export default {
  name: 'MedicalRecordQualityReport',
  components: {
    FilterPanel,
    ReportHeader,
    TabNav,
    DeptStatistics,
    IndexCoreReport,
  },
  data() {
    return {
      searchParams: {
        time: moment().subtract(1, 'month').startOf('month').format('YYYY年MM月'),
        dep_id: [],
        type: 'month',
      },
      basic_info:{
        title: '核心制度指标分析报告',
      },
      isFilterPanelShow: true,
      activeTab: 'indexCoreReport',
      timeType: 'month',
      selectMonth: getCurMonthFormat(),
      tabList: [
        { key: 'indexCoreReport', name: '核心制度指标' },
        { key: 'department', name: '科室统计' },
      ],
      tableData: [],
      top5DataList: [],
      endDataList: [],
    };
  },
  mounted() {
    this.loadingData();
  },
  destroyed() {},
  methods: {
    handleTabChange(tabKey) {
      this.activeTab = tabKey;
      switch (tabKey) {
        case 'department':
          this.$nextTick(() => {
            this.$refs.departmentRef.initTopData(this.searchParams, this.top5DataList);
            this.$refs.departmentRef.initEndData(this.searchParams, this.endDataList);
          });

          break;
        case 'indexCoreReport':
          this.$nextTick(() => {
            this.$refs.indexCoreReportRef.initData(this.searchParams,this.tableData);
          });
          break;
      }
    },

    selectMonthChange(val) {
      const { year, month } = this.extractYearAndMonth(val);
      console.log('selectMonthChange', val, year, month);
      this.$refs.filterPanelRef.setChangeTime(year, month, this.timeType);
      this.searchParams.time = `${year}年${month}月`;
      this.searchParams.type = 'month';
      this.loadingData();
    },
    extractYearAndMonth(dateStr) {
      const regex = /(\d{4})年(?:(\d{1,2})月)?/;
      const match = dateStr.match(regex);
      const year = match ? match[1] : '';
      const month = match && match[2] ? (match[2].length === 1 ? `0${match[2]}` : match[2]) : '';

      return { year, month };
    },
    updateDisplay(type, time, quarter) {
      this.timeType = type;
      if (type === 'month' || type === 'year') {
        this.selectMonth = time;
      } else {
        const validQuarter = Number(quarter);
        if (isNaN(validQuarter) || validQuarter < 1 || validQuarter > 4) {
          this.selectMonth = `${time}年01月`;
          return;
        }
        const quarterToFirstMonth = {
          1: '01', // 第一季度 → 1月
          2: '04', // 第二季度 → 4月
          3: '07', // 第三季度 → 7月
          4: '10', // 第四季度 → 10月
        };
        const firstMonth = quarterToFirstMonth[validQuarter];
        this.selectMonth = `${time}年${firstMonth}月`;
      }
    },
    updateParams(filterParams) {
      this.searchParams = filterParams;
    },

    handleQuery(filterParams) {
      this.searchParams = filterParams;
      this.loadingData();
    },

    handleReset(filterParams) {
      this.searchParams = filterParams;
      this.activeTab = 'overview';
    },

    async loadingTableData() {
      try {
        const params = {
          period: this.searchParams.time,
          type: this.searchParams.type,
          AAC11N: this.searchParams.dep_id.join(','),
          is_export: 0,
        };
        const res = await this.$axios2.post('/quality_index_core_report', params);
        if (res.data) {
          this.tableData = res.data.list;
          this.$nextTick(() => {
            this.$refs.indexCoreReportRef.initData(this.searchParams, this.tableData);
          });

          this.$nextTick(() => {
            this.$refs.filterPanelRef.initData(res.data.departments);
          });
        } else {
          this.tableData = [];
        }
      } catch (error) {
        console.error('加载报告数据失败：', error);
        this.tableData = [];
      }
    },

    async top5Data() {
      try {
        const params = {
          period: this.searchParams.time,
          type: this.searchParams.type,
          // AAC11N: this.searchParams.dep_id,
          is_export: 0,
        };
        const res = await this.$axios2.post('/quality_index_core_top_departments', params);
        if (res.data) {
          this.top5DataList = res.data.list;
          this.$nextTick(() => {
            this.$refs.departmentRef.initTopData(this.searchParams, this.top5DataList);
          });
        } else {
          this.top5DataList = [];
        }
      } catch (error) {
        console.error('加载报告数据失败：', error);
        this.top5DataList = [];
      }
    },

    async end5Data() {
      try {
        const params = {
          period: this.searchParams.time,
          type: this.searchParams.type,
          // AAC11N: this.searchParams.dep_id,
          is_export: 0,
        };
        const res = await this.$axios2.post('/quality_index_core_bottom_departments', params);
        if (res.data) {
          this.endDataList = res.data.list;
          this.$nextTick(() => {
            this.$refs.departmentRef.initEndData(this.searchParams, this.endDataList);
          });
        } else {
          this.endDataList = [];
        }
      } catch (error) {
        console.error('加载报告数据失败：', error);
        this.endDataList = [];
      }
    },

    async loadingData() {
      try {
        
        this.loadingTableData();
        this.top5Data();
        this.end5Data();
      } catch (error) {
        console.error('加载报告数据失败：', error);
        this.tableData = [];
        this.top5DataList = [];
        this.endDataList = [];
      }
    },

    async exportReportHandle() {
      const params = {
        time: this.searchParams.time,
        type: this.searchParams.type,
        is_export: 1,
      };
      exportReportStatistics(params).then(res => {
        const blob = new Blob([res.data]);
        const fileName = `分析报告.docx`;
        if ('download' in document.createElement('a')) {
          const elink = document.createElement('a');
          elink.download = fileName;
          elink.style.display = 'none';
          elink.href = URL.createObjectURL(blob);
          document.body.appendChild(elink);
          elink.click();
          URL.revokeObjectURL(elink.href);
          document.body.removeChild(elink);
        } else {
          navigator.msSaveBlob(blob, fileName);
        }
      });
    },
  },
};
</script>

<style lang="scss" scoped>
.report {
  margin: 0 auto;
  padding: 20px;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'PingFang SC', 'Hiragino Sans GB', 'Microsoft YaHei', sans-serif;
  background: #f0f2f5;
  min-height: 100vh;
  box-sizing: border-box;

  .section {
    background-color: #fff;
    border-radius: 6px;
    border: 1px solid #e5e7eb;
    margin-bottom: 4px;
    padding: 16px;

    &--first {
      margin-bottom: 4px;
    }

    &--second {
      margin-bottom: 0;
    }
  }

  .filter-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    margin-bottom: 16px;

    .filter-btn {
      display: flex;
      align-items: center;
      gap: 8px;
      padding: 8px 16px;
      border: 1px solid #e5e7eb;
      background-color: #fff;
      cursor: pointer;
      font-size: 14px;
      color: #374151;
      border-radius: 4px;
      transition: all 0.2s;

      &:hover {
        background-color: #f9fafb;
        border-color: #d1d5db;
      }
    }

    .toggle-arrow {
      transition: transform 0.2s;

      &.active {
        transform: rotate(180deg);
      }
    }

    .export-bar {
      display: flex;
      align-items: center;
      gap: 8px;

      .export-btn {
        background: #185da6;
        color: #fff;
        border: none;
        padding: 8px 20px;
        border-radius: 4px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 500;
        transition: background 0.3s;

        &:hover {
          background: #134a85;
        }
      }
    }
  }

  .content-wrap {
    margin-top: 20px;

    .content-item {
      width: 100%;
    }
  }
}
</style>