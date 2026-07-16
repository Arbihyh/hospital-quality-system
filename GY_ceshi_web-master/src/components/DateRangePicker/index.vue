<!-- 时间区间选择器 -->
<template>
  <el-form-item :label="labelText">
    <el-date-picker 
      v-model="startTime" 
      type="date" 
      :picker-options="pickerStartOptions"
      placeholder="开始日期" 
      value-format="yyyyMMdd" 
      format="yyyy年MM月dd日" 
      style="width: 48%; margin-right: 4%;"
    />
    <el-date-picker 
      v-model="endTime" 
      type="date" 
      :picker-options="pickerEndOptions" 
      placeholder="结束日期"
      value-format="yyyyMMdd" 
      format="yyyy年MM月dd日" 
      style="width: 48%;"
    />
  </el-form-item>
</template>

<script>
import moment from 'moment'
export default {
  name: 'DateRangePicker',
  props: {
    labelText: {
      type: String,
      required: false,
      default: '时间'
    },
    value: {
      type: Object,
      default: () => ({
        startTime: '',
        endTime: ''
      })
    },
    // 传入需要【显示】的快捷项key数组，不传默认显示全部
    showShortcuts: {
      type: Array,
      default: () => []
    },
    // 传入需要【隐藏】的快捷项key数组，优先级低于showShortcuts
    hideShortcuts: {
      type: Array,
      default: () => []
    },
    // 传入【排序】的快捷项key数组，自定义展示顺序，优先级最高
    sortShortcuts: {
      type: Array,
      default: () => []
    }
  },
  data() {
    return {
      startTime: this.value.startTime,
      endTime: this.value.endTime,
      allShortcuts: [
        { key: 'today', text: '今天', type: 'day', onClick: (picker, _this) => {
          const cur = moment().format('YYYYMMDD')
          picker.$emit('pick', cur)
          _this.endTime = cur
        }},
        { key: '7days', text: '近7天', type: 'day', onClick: (picker, _this) => {
          picker.$emit('pick', moment().subtract(7, 'days').format('YYYYMMDD'))
          _this.endTime = moment().format('YYYYMMDD')
        }},
        { key: '30days', text: '近30天', type: 'day', onClick: (picker, _this) => {
          picker.$emit('pick', moment().subtract(30, 'days').format('YYYYMMDD'))
          _this.endTime = moment().format('YYYYMMDD')
        }},
        { key: 'currentMonth', text: '当月', type: 'month', onClick: (picker, _this) => {
          picker.$emit('pick', moment().startOf('month').format('YYYYMMDD'))
          _this.endTime = moment().format('YYYYMMDD')
        }},
        { key: 'lastMonth', text: '上月', type: 'month', onClick: (picker, _this) => {
          picker.$emit('pick', moment().subtract(1, 'month').startOf('month').format('YYYYMMDD'))
          _this.endTime = moment().subtract(1, 'month').endOf('month').format('YYYYMMDD')
        }},
        { key: '3years', text: '近3年', type: 'year', onClick: (picker, _this) => {
          picker.$emit('pick', moment().subtract(3, 'years').format('YYYYMMDD'))
          _this.endTime = moment().format('YYYYMMDD')
        }},
        { key: 'thisYear', text: moment().format("YYYY") + '年', type: 'year', onClick: (picker, _this) => {
          picker.$emit('pick', moment().startOf('year').format('YYYYMMDD'))
          _this.endTime = moment().endOf('year').format('YYYYMMDD')
        }},
        { key: 'lastYear', text: moment().add(-1, 'Y').format("YYYY") + '年', type: 'year', onClick: (picker, _this) => {
          picker.$emit('pick', moment().add(-1, 'Y').startOf('year').format('YYYYMMDD'))
          _this.endTime = moment().add(-1, 'Y').endOf('year').format('YYYYMMDD')
        }},
        { key: 'beforeLastYear', text: moment().add(-2, 'Y').format("YYYY") + '年', type: 'year', onClick: (picker, _this) => {
          picker.$emit('pick', moment().add(-2, 'Y').startOf('year').format('YYYYMMDD'))
          _this.endTime = moment().add(-2, 'Y').endOf('year').format('YYYYMMDD')
        }},
        { key: 'quarter1', text: '一季度', type: 'season', onClick: (picker, _this) => {
          picker.$emit('pick', moment().startOf('year').format('YYYYMMDD'))
          _this.endTime = moment().startOf('year').add(3, 'M').subtract(1, 'days').format('YYYYMMDD')
        }},
        { key: 'quarter2', text: '二季度', type: 'season', onClick: (picker, _this) => {
          picker.$emit('pick', moment().startOf('year').add(3, 'M').format('YYYYMMDD'))
          _this.endTime = moment().startOf('year').add(6, 'M').subtract(1, 'days').format('YYYYMMDD')
        }},
        { key: 'quarter3', text: '三季度', type: 'season', onClick: (picker, _this) => {
          picker.$emit('pick', moment().startOf('year').add(6, 'M').format('YYYYMMDD'))
          _this.endTime = moment().startOf('year').add(9, 'M').subtract(1, 'days').format('YYYYMMDD')
        }},
        { key: 'quarter4', text: '四季度', type: 'season', onClick: (picker, _this) => {
          picker.$emit('pick', moment().startOf('year').add(9, 'M').format('YYYYMMDD'))
          _this.endTime = moment().endOf('year').format('YYYYMMDD')
        }}
      ]
    }
  },
  watch: {
    startTime(val) {
      this.$emit('input', { ...this.value, startTime: val })
    },
    endTime(val) {
      this.$emit('input', { ...this.value, endTime: val })
    },
    value: {
      deep: true,
      handler(newVal) {
        this.startTime = newVal.startTime
        this.endTime = newVal.endTime
      }
    }
  },
  computed: {

    filterSortShortcuts() {
      let shortcuts = [...this.allShortcuts]
      const _this = this
      if (this.showShortcuts.length > 0) {
        shortcuts = shortcuts.filter(item => this.showShortcuts.includes(item.key))
      }
      if (this.hideShortcuts.length > 0) {
        shortcuts = shortcuts.filter(item => !this.hideShortcuts.includes(item.key))
      }
      if (this.sortShortcuts.length > 0) {
        shortcuts = this.sortShortcuts.map(key => shortcuts.find(item => item.key === key)).filter(item => item)
      }
      return shortcuts.map(item => ({
        text: item.text,
        onClick(picker) {
          item.onClick(picker, _this)
          _this.$emit('shortcut-click', item.type)
        }
      }))
    },
    pickerStartOptions() {
      const _this = this
      return {
        disabledDate: (time) => {
          if (_this.endTime) {
            const endDate = new Date(_this.endTime.replace(/(\d{4})(\d{2})(\d{2})/, '$1-$2-$3')).getTime()
            return time.getTime() > endDate || time.getTime() > Date.now()
          }
          return time.getTime() > Date.now()
        },
        shortcuts: this.filterSortShortcuts
      }
    },
    pickerEndOptions() {
      const _this = this
      return {
        disabledDate: (time) => {
          if (_this.startTime) {
            const startDate = new Date(_this.startTime.replace(/(\d{4})(\d{2})(\d{2})/, '$1-$2-$3')).getTime()
            return time.getTime() < startDate || time.getTime() > Date.now()
          }
          return time.getTime() > Date.now()
        }
      }
    }
  }
}
</script>

<style scoped>
::v-deep .el-form-item__label {
  white-space: nowrap;
}
::v-deep .el-date-editor {
  font-size: 14px;
}
</style>