<!-- 时间区间选择器（仅年月） -->
<template>
  <el-form-item :label="labelText">
    <el-date-picker
      width="100%"
      v-model="timeValue"
      :type="type"
      :placeholder="placeholder"
      :picker-options="pickerOptions"
      format="yyyy年MM月"
      value-format="yyyy-MM"
      :editable="false"
    ></el-date-picker>
  </el-form-item>
</template>

<script>
import moment from 'moment';
export default {
  name: 'DateRangePicker',
  props: {
    placeholder: {
      type: String,
      required: false,
      default: '请选择年月',
    },
    labelText: {
      type: String,
      required: false,
      default: '年月',
    },
    value: {
      type: String,
      default: '',
    },
    showShortcuts: {
      type: Array,
      default: () => [],
    },
    hideShortcuts: {
      type: Array,
      default: () => [],
    },
    sortShortcuts: {
      type: Array,
      default: () => [],
    },
  },
  data() {
    return {
      type: 'month',
      timeValue: this.value,
      allShortcuts: [
        {
          key: 'currentMonth',
          text: '当月',
          type: 'month',
          onClick: (picker, _this) => {
            const pickVal = moment().format('YYYY-MM');
            picker.$emit('pick', pickVal);
            _this.timeValue = pickVal;
          },
        },
        {
          key: 'lastMonth',
          text: '上月',
          type: 'month',
          onClick: (picker, _this) => {
            const pickVal = moment().subtract(1, 'month').format('YYYY-MM');
            picker.$emit('pick', pickVal);
            _this.timeValue = pickVal;
          },
        },
        {
          key: 'thisYear',
          text: moment().format('YYYY') + '年',
          type: 'year',
          onClick: (picker, _this) => {
            const pickVal = moment().startOf('year').format('YYYY-MM');
            console.log("thisYear",pickVal);
            picker.$emit('pick', pickVal);
            _this.timeValue = pickVal;
          },
        },
        {
          key: 'lastYear',
          text: moment().add(-1, 'Y').format('YYYY') + '年',
          type: 'year',
          onClick: (picker, _this) => {
            const pickVal = moment().add(-1, 'Y').startOf('year').format('YYYY-MM');
            picker.$emit('pick', pickVal);
            _this.timeValue = pickVal;
          },
        },
        {
          key: 'beforeLastYear',
          text: moment().add(-2, 'Y').format('YYYY') + '年',
          type: 'year',
          onClick: (picker, _this) => {
            const pickVal = moment().add(-2, 'Y').startOf('year').format('YYYY-MM');
            picker.$emit('pick', pickVal);
            _this.timeValue = pickVal;
          },
        },
        {
          key: 'quarter1',
          text: '一季度',
          type: 'quarter',
          onClick: (picker, _this) => {
            const pickVal = moment().startOf('year').format('YYYY-MM');
            picker.$emit('pick', pickVal);
            _this.timeValue = pickVal;
          },
        },
        {
          key: 'quarter2',
          text: '二季度',
          type: 'quarter',
          onClick: (picker, _this) => {
            const pickVal = moment().startOf('year').add(3, 'M').format('YYYY-MM');
            picker.$emit('pick', pickVal);
            _this.timeValue = pickVal;
          },
        },
        {
          key: 'quarter3',
          text: '三季度',
          type: 'quarter',
          onClick: (picker, _this) => {
            const pickVal = moment().startOf('year').add(6, 'M').format('YYYY-MM');
            picker.$emit('pick', pickVal);
            _this.timeValue = pickVal;
          },
        },
        {
          key: 'quarter4',
          text: '四季度',
          type: 'quarter',
          onClick: (picker, _this) => {
            const pickVal = moment().startOf('year').add(9, 'M').format('YYYY-MM');
            picker.$emit('pick', pickVal);
            _this.timeValue = pickVal;
          },
        },
      ],
    };
  },
  watch: {
    timeValue(val) {
      console.log('选中的年月：', val);
      this.$emit('input', val);
    },
    value(newVal) {
      this.timeValue = newVal;
    },
  },
  computed: {
    filterSortShortcuts() {
      let shortcuts = [...this.allShortcuts];
      const _this = this;
      if (this.showShortcuts.length > 0) {
        shortcuts = shortcuts.filter(item => this.showShortcuts.includes(item.key));
      }
      if (this.hideShortcuts.length > 0) {
        shortcuts = shortcuts.filter(item => !this.hideShortcuts.includes(item.key));
      }
      if (this.sortShortcuts.length > 0) {
        shortcuts = this.sortShortcuts.map(key => shortcuts.find(item => item.key === key)).filter(item => item);
      }
      return shortcuts.map(item => ({
        text: item.text,
        onClick(picker) {
          item.onClick(picker, _this);
          _this.$emit('shortcut-click', item.type, _this.timeValue);
        },
      }));
    },
    pickerOptions() {
      const _this = this;
      return {
        disabledDate: () => false,
        shortcuts: this.filterSortShortcuts,
      };
    },
  },
};
</script>

<style scoped>
::v-deep .el-form-item__label {
  white-space: nowrap;
}
::v-deep .el-date-editor {
  font-size: 14px !important;
  width: 100% !important;
}
::v-deep .el-date-editor--month .el-input__inner {
  text-align: center;
}
::v-deep .el-date-picker__panel {
  .el-date-table {
    display: none !important;
  }
  .el-month-table {
    display: block !important;
  }
}
</style>