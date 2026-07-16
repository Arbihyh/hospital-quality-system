<!-- 医疗质量报告 -->
<template>
  <div class="report">
    <div class="section section--first">
      <div class="filter-bar">
        <button class="filter-btn" @click="toggleFilterPanel">
          <span class="filter-icon">⚙️</span>
          筛选
          <span class="toggle-arrow" :class="{ active: isFilterPanelShow }">▼</span>
        </button>
        <div class="export-bar">
          <el-date-picker v-model="selectMonth" type="month" placeholder="选择月" format="yyyy年MM月" value-format="yyyy年MM月"></el-date-picker>
          <el-button class="export-btn" @click="exportReportHandle">导出报告</el-button>
        </div>
      </div>

      <FilterPanel v-show="isFilterPanelShow" ref="filterPanelRef" @query="handleQuery" @reset="handleReset" @update-display="updateDisplay" />
    </div>

    <div class="section section--second">
      <ReportHeader :basic-info="reportData.basic_info" />
      <TabNav :tabs="tabList" :active-tab="activeTab" @tab-change="handleTabChange" />
      <div class="content-wrap">
        <div v-for="tab in tabList" :key="tab.key" v-show="activeTab === tab.key" class="content-item">
          <component :is="tab.componentName" ref="tabComponentRefs" :data-key="tab.dataKey" />
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import moment from 'moment/moment';

import FilterPanel from '../comp/FilterPanel.vue';
import ReportHeader from '../comp/ReportHeader.vue';
import TabNav from '../comp/TabNav.vue';
import Overview from '../comp/Overview.vue';
import Indicators from '../comp/Indicators.vue';
import DefectDistribution from '../comp/DefectDistribution.vue';
import DeptStatistics from '../comp/DeptStatisticsNew.vue';
import DoctorStatistics from '../comp/DoctorStatisticsNew.vue';
import IndexCompare from '../comp/IndexCompare.vue';
import MedicalRecordQuality from '../comp/MedicalRecordQuality.vue';

import { exportReportStatistics } from '@/api/excel';

const DEFAULT_REPORT_DATA = {
  basic_info: {},
  overview: {},
  quality_distribution: {},
  defect_distribution: {},
  department_ranking: {},
  doctor_ranking: {},
  index_compare: {},
};

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
    Overview,
    Indicators,
    DefectDistribution,
    DeptStatistics,
    DoctorStatistics,
    IndexCompare,
    MedicalRecordQuality,
  },
  data() {
    return {
      searchParams: {
        time: moment().startOf('month').format('YYYY年MM月'),
        dep_id: [],
        type: 'month',
      },
      isFilterPanelShow: false,
      activeTab: 'overview',
      selectMonth: getCurMonthFormat(),
      reportData: { ...DEFAULT_REPORT_DATA },
    };
  },
  computed: {
    tabList() {
      return [
        { key: 'overview', name: '质控概况', componentName: 'Overview', dataKey: 'overview' },
        { key: 'indicators', name: '病案指标', componentName: 'Indicators', dataKey: 'overview.quality_indicators' },
        { key: 'defects', name: '缺陷分布', componentName: 'DefectDistribution', dataKey: 'defect_distribution' },
        { key: 'medical', name: '病历质量', componentName: 'MedicalRecordQuality', dataKey: 'quality_distribution' },
        { key: 'department', name: '科室统计', componentName: 'DeptStatistics', dataKey: 'department_ranking' },
        { key: 'doctor', name: '医师统计', componentName: 'DoctorStatistics', dataKey: 'doctor_ranking' },
      ];
    },
  },
  mounted() {
    this.loadReportData();
  },
  methods: {
    toggleFilterPanel() {
      this.isFilterPanelShow = !this.isFilterPanelShow;
    },

    handleTabChange(tabKey) {
      this.activeTab = tabKey;
      this.initTabComponentData(tabKey);
    },

    initTabComponentData(tabKey) {
      const tabConfig = this.tabList.find(item => item.key === tabKey);
      if (!tabConfig) return;

      // 获取组件实例
      const componentRefs = this.$refs.tabComponentRefs;
      const targetComponent = Array.isArray(componentRefs) ? componentRefs.find(ref => ref.$options.name === tabConfig.componentName) : componentRefs;

      if (!targetComponent) return;

      // 获取数据
      const dataValue = this.getNestedValue(this.reportData, tabConfig.dataKey);

      this.$nextTick(() => {
        targetComponent.initData(this.searchParams, dataValue);
      });
    },

    /**
     * 获取嵌套对象的属性值
     * @param {Object} obj 目标对象
     * @param {string} path 属性路径，如 'overview.quality_indicators'
     * @returns {*} 属性值
     */
    getNestedValue(obj, path) {
      return path.split('.').reduce((acc, curr) => acc?.[curr] || {}, obj);
    },

    /**
     * 更新显示的月份
     * @param {string} type 类型(month/year/quarter)
     * @param {string} time 时间
     * @param {string} quarter 季度
     */
    updateDisplay(type, time, quarter) {
      if (type === 'month' || type === 'year') {
        this.selectMonth = time;
        return;
      }

      const validQuarter = Number(quarter);
      if (isNaN(validQuarter) || validQuarter < 1 || validQuarter > 4) {
        this.selectMonth = `${time}年01月`;
        return;
      }

      const quarterToFirstMonth = { 1: '01', 2: '04', 3: '07', 4: '10' };
      this.selectMonth = `${time}年${quarterToFirstMonth[validQuarter]}月`;
    },

    handleQuery(filterParams) {
      this.searchParams = { ...filterParams };
      this.loadReportData();
    },

    handleReset(filterParams) {
      this.searchParams = { ...filterParams };
      this.activeTab = 'overview';
      this.loadReportData();
    },

    async loadReportData() {
      try {
        const params = {
          time: this.searchParams.time,
          type: this.searchParams.type,
          dep_id: this.searchParams.dep_id,
          is_export: 0,
        };

        const { data } = await this.$axios.post('/quality_report/getReportStatistics', params);

        if (data) {
          this.reportData = {
            ...data,
            basic_info: {
              ...data.basic_info,
              queryTime: this.selectMonth,
              title: '病历质量分析报告',
            },
          };

          this.$nextTick(() => {
            this.$refs.filterPanelRef?.initData(this.reportData.department_list);
            this.initTabComponentData(this.activeTab);
          });
        }
      } catch (error) {
        console.error('加载报告数据失败：', error);
        this.reportData = { ...DEFAULT_REPORT_DATA };
      }
    },

    async exportReportHandle() {
      try {
        const params = {
          time: this.selectMonth,
          is_export: 1,
        };

        const res = await exportReportStatistics(params);
        const blob = new Blob([res.data], { type: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' });
        const fileName = `病历质量分析报告_${this.selectMonth}.docx`;

        // 创建下载链接
        const elink = document.createElement('a');
        elink.download = fileName;
        elink.style.display = 'none';
        elink.href = URL.createObjectURL(blob);
        document.body.appendChild(elink);
        elink.click();

        // 清理资源
        URL.revokeObjectURL(elink.href);
        document.body.removeChild(elink);

        this.$message?.success('报告导出成功');
      } catch (error) {
        console.error('导出报告失败：', error);
      }
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