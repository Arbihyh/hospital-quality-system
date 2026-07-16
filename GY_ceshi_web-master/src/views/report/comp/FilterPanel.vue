<template>
  <div class="filter-panel">
    <div class="filter-panel__content">
      <div class="filter-panel__wrapper">
        <div class="filter-panel__left">
          <TabBtnGroup
            :active-key="currentGranularity"
            :tab-list="[
              { key: 'year', label: '年' },
              { key: 'quarter', label: '季' },
              { key: 'month', label: '月' },
            ]"
            @tab-change="key => switchGranularity('timeActiveKey', key)"
          />

          <!-- 年份选择器（年/季维度显示） -->
          <el-date-picker
            v-if="currentGranularity === 'year' || currentGranularity === 'quarter'"
            v-model="selectedYear"
            type="year"
            placeholder="选择年份"
            format="yyyy年"
            value-format="yyyy"
            class="filter-panel__date-picker"
            @change="updateDisplayTime"
          ></el-date-picker>

          <!-- 月份选择器（月维度显示） -->
          <el-date-picker
            v-if="currentGranularity === 'month'"
            v-model="selectedTime"
            type="month"
            placeholder="选择月份"
            format="yyyy年MM月"
            value-format="yyyy年MM月"
            class="filter-panel__date-picker"
            @change="updateDisplayTime"
          ></el-date-picker>

          <!-- 季度选择器（季维度显示） -->
          <el-select v-if="currentGranularity === 'quarter'" v-model="selectedQuarter" placeholder="选择季度" class="filter-panel__date-picker" @change="updateDisplayTime">
            <el-option v-for="quarter in quarterList" :key="quarter.key" :label="quarter.name" :value="quarter.key"></el-option>
          </el-select>

          <el-select v-model="selectedDept" multiple collapse-tags filterable placeholder="选择科室" class="filter-panel__dept-select" @change="handleDeptChange">
            <el-option v-for="(item, index) in deptList" :label="item.dep_name" :value="item.dep_id" :key="index"></el-option>
          </el-select>
        </div>

        <div class="filter-panel__right">
          <el-button class="filter-btn query-btn" type="primary" @click="handleQuery">查询</el-button>
          <el-button class="filter-btn reset-btn" @click="handleReset">重置</el-button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import TabBtnGroup from '@/components/TabBtnGroup';

export default {
  name: 'FilterPanel',
  components: {
    TabBtnGroup,
  },
  data() {
    return {
      granularityList: [
        { key: 'month', name: '月' },
        { key: 'quarter', name: '季' },
        { key: 'year', name: '年' },
      ],
      currentGranularity: 'month',
      quarterList: [
        { key: 1, name: '第一季度' },
        { key: 2, name: '第二季度' },
        { key: 3, name: '第三季度' },
        { key: 4, name: '第四季度' },
      ],
      selectedTime: '', // 月份值（yyyy年MM月）
      selectedYear: '', // 年份值（yyyy）
      selectedQuarter: 1,
      currentYear: new Date().getFullYear(),
      displayTime: '',

      deptList: [],
      selectedDept: [],
    };
  },
  computed: {
    currentGranularityText() {
      return this.granularityList.find(item => item.key === this.currentGranularity)?.name || '月';
    },
  },
  mounted() {
    // this.selectInfo();
    this.initCurrentTime();
    this.updateDisplayTime();
  },
  methods: {
    // initCurrentTime() {
    //   const now = new Date();
    //   const year = now.getFullYear();
    //   const month = (now.getMonth() + 1).toString().padStart(2, '0');

    //   this.selectedTime = `${year}年${month}月`;
    //   this.selectedYear = year.toString();
    //   this.currentYear = year;
    //   this.selectedQuarter = Math.ceil((now.getMonth() + 1) / 3);
    // },
    initCurrentTime() {
      const now = new Date();
      // 计算上个月的日期（处理1月→去年12月的边界情况）
      const lastMonth = new Date(now.getFullYear(), now.getMonth() - 1, 1);
      const year = lastMonth.getFullYear();
      const month = (lastMonth.getMonth() + 1).toString().padStart(2, '0');

      this.selectedTime = `${year}年${month}月`; // 默认选中上个月
      this.selectedYear = year.toString();
      this.currentYear = year;
      // 基于上个月计算对应的季度
      this.selectedQuarter = Math.ceil((lastMonth.getMonth() + 1) / 3);
    },

    setChangeTime(year, month, type) {
      // console.log('setChangeTime', year, month, type);
      this.currentGranularity = 'month';
      this.selectedTime = year + '年' + month + '月';
      this.selectedYear = year + '年';
      const validMonth = Number(month);
      let quarter = 1;
      if (validMonth >= 4 && validMonth <= 6) {
        quarter = 2; // 4-6月 → 第二季度
      } else if (validMonth >= 7 && validMonth <= 9) {
        quarter = 3; // 7-9月 → 第三季度
      } else if (validMonth >= 10 && validMonth <= 12) {
        quarter = 4; // 10-12月 → 第四季度
      }

      this.selectedQuarter = quarter;
    },

    initData(data) {
      this.deptList = data;
    },

    switchGranularity(type, key) {
      this.currentGranularity = key;
      if (this.selectedTime) {
        this.selectedYear = this.selectedTime.slice(0, 4);
      }
      this.selectedDept = [];
      this.updateDisplayTime();
    },

    updateDisplayTime() {
      switch (this.currentGranularity) {
        case 'month':
          this.displayTime = this.selectedTime || `${this.currentYear}年01月`;
          this.currentYear = parseInt(this.displayTime.slice(0, 4));
          this.$emit('update-display', 'month', this.displayTime);
          break;
        case 'quarter':
          const currentYear = this.selectedYear || this.currentYear;
          const quarterName = this.quarterList.find(q => q.key === this.selectedQuarter)?.name;
          this.displayTime = `${currentYear}年${quarterName}`;
          this.currentYear = parseInt(currentYear);
          this.$emit('update-display', 'quarter', this.currentYear, this.selectedQuarter);
          break;
        case 'year':
          this.displayTime = this.selectedYear ? `${this.selectedYear}年` : `${this.currentYear}年`;
          this.currentYear = parseInt(this.displayTime.slice(0, 4));
          this.$emit('update-display', 'year', this.displayTime + '01月');
          break;
        default:
          this.displayTime = this.selectedTime || `${this.currentYear}年01月`;
      }

      const filterParams = {
        type: this.currentGranularity,
        time: this.displayTime,
        dep_id: this.selectedDept,
        year: this.selectedYear || this.currentYear,
        quarter: this.selectedQuarter,
        month: this.selectedTime ? this.selectedTime.slice(5, 7) : '01',
      };

      this.$emit('update-params', filterParams);
    },

    // selectInfo() {
    //   this.$axios
    //     .post('/get_omr_department_list')
    //     .then(res => {
    //       this.deptList = res.data;
    //     })
    //     .catch(err => {
    //       console.error('获取科室列表失败：', err);
    //     });
    // },

    handleDeptChange() {
      if (this.selectedDept.includes('all') && this.selectedDept.length < this.deptList.length + 1) {
        this.selectedDept = this.deptList.map(dept => dept.dep_id);
      }
    },

    handleQuery() {
      const filterParams = {
        type: this.currentGranularity,
        time: this.displayTime,
        dep_id: this.selectedDept,
        year: this.selectedYear || this.currentYear,
        quarter: this.selectedQuarter,
        month: this.selectedTime ? this.selectedTime.slice(5, 7) : '01',
      };
      this.$emit('query', filterParams);
    },

    handleReset() {
      this.currentGranularity = 'month';
      this.initCurrentTime();
      this.updateDisplayTime();
      this.selectedDept = [];

      const filterParams = {
        type: this.currentGranularity,
        time: this.displayTime,
        dep_id: [],
        year: this.currentYear,
        quarter: 1,
        month: '01',
      };
      this.$emit('reset', filterParams);
    },
  },
};
</script>

<style lang="scss" scoped>
.filter-panel {
  margin-bottom: 16px;
  padding: 16px;
  background-color: #fff;
  border-radius: 4px;
}

.filter-panel__wrapper {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 20px;
}

.filter-panel__left {
  display: flex;
  align-items: center;
  gap: 16px;
  flex: 1;
}

.filter-panel__date-picker {
  width: 180px;
}

.filter-panel__dept-select {
  width: 220px;
  min-width: 200px;
}

.filter-panel__right {
  white-space: nowrap;
}

.filter-btn {
  padding: 8px 20px !important;
  font-size: 14px !important;
  font-weight: 500 !important;
  border-radius: 4px !important;
  transition: all 0.3s ease !important;
  border: none !important;
}

::v-deep .query-btn {
  background-color: #185da6 !important;
  color: #fff !important;
}

::v-deep .query-btn:hover {
  background-color: #134a82 !important;
  box-shadow: 0 2px 4px rgba(24, 93, 166, 0.2) !important;
}

::v-deep .reset-btn {
  background-color: #fafbfd !important;
  color: #080808 !important;
  border: 1px solid #cdcaca !important;
  margin-left: 12px !important;
}

::v-deep .reset-btn:hover {
  background-color: #f5f7fa !important;
  border-color: #b9b9b9 !important;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05) !important;
}
</style>