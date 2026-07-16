<!-- 时间区间选择器 -->
<template>
  <el-form-item :label="labelText">
    <el-date-picker 
      v-model="innerStartValue" 
      type="date" 
      :picker-options="pickerStartOptions"
      :placeholder="startPlaceholder" 
      value-format="yyyyMMdd" 
      format="yyyy年MM月dd日" 
      style="width: 48%; margin-right: 4%;"
    />
    <el-date-picker 
      v-model="innerEndValue" 
      type="date" 
      :picker-options="pickerEndOptions" 
      :placeholder="endPlaceholder"
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
      default: () => ({})
    },
    startKey: {
      type: String,
      required: false,
      default: 'startTime'
    },
    endKey: {
      type: String,
      required: false,
      default: 'endTime'
    },
    startPlaceholder: {
      type: String,
      required: false,
      default: '开始日期'
    },
    endPlaceholder: {
      type: String,
      required: false,
      default: '结束日期'
    },
    showShortcuts: {
      type: Array,
      default: () => []
    },
    hideShortcuts: {
      type: Array,
      default: () => []
    },
    sortShortcuts: {
      type: Array,
      default: () => []
    },
    disabled: {
      type: Boolean,
      default: false
    },
    maxDate: {
      type: String,
      default: ''
    }
  },
  data() {
    return {
      innerStartValue: '',
      innerEndValue: '',
      allShortcuts: [
        { key: 'today', text: '今天', type: 'day', onClick: (picker, _this) => {
          const cur = moment().format('YYYYMMDD')
          _this.innerStartValue = cur
          _this.innerEndValue = cur
        }},
        { key: '7days', text: '近7天', type: 'day', onClick: (picker, _this) => {
          const start = moment().subtract(7, 'days').format('YYYYMMDD')
          const end = moment().format('YYYYMMDD')
          _this.innerStartValue = start
          _this.innerEndValue = end
        }},
        { key: '30days', text: '近30天', type: 'day', onClick: (picker, _this) => {
          const start = moment().subtract(30, 'days').format('YYYYMMDD')
          const end = moment().format('YYYYMMDD')
          _this.innerStartValue = start
          _this.innerEndValue = end
        }},
        { key: 'currentMonth', text: '当月', type: 'month', onClick: (picker, _this) => {
          const start = moment().startOf('month').format('YYYYMMDD')
          const end = moment().format('YYYYMMDD')
          _this.innerStartValue = start
          _this.innerEndValue = end
        }},
        { key: 'lastMonth', text: '上月', type: 'month', onClick: (picker, _this) => {
          const start = moment().subtract(1, 'month').startOf('month').format('YYYYMMDD')
          const end = moment().subtract(1, 'month').endOf('month').format('YYYYMMDD')
          _this.innerStartValue = start
          _this.innerEndValue = end
        }},
        { key: '3years', text: '近3年', type: 'year', onClick: (picker, _this) => {
          const start = moment().subtract(3, 'years').format('YYYYMMDD')
          const end = moment().format('YYYYMMDD')
          _this.innerStartValue = start
          _this.innerEndValue = end
        }},
        { key: 'thisYear', text: moment().format("YYYY") + '年', type: 'year', onClick: (picker, _this) => {
          const start = moment().startOf('year').format('YYYYMMDD')
          const end = moment().endOf('year').format('YYYYMMDD')
          _this.innerStartValue = start
          _this.innerEndValue = end
        }},
        { key: 'lastYear', text: moment().add(-1, 'Y').format("YYYY") + '年', type: 'year', onClick: (picker, _this) => {
          const start = moment().add(-1, 'Y').startOf('year').format('YYYYMMDD')
          const end = moment().add(-1, 'Y').endOf('year').format('YYYYMMDD')
          _this.innerStartValue = start
          _this.innerEndValue = end
        }},
        { key: 'beforeLastYear', text: moment().add(-2, 'Y').format("YYYY") + '年', type: 'year', onClick: (picker, _this) => {
          const start = moment().add(-2, 'Y').startOf('year').format('YYYYMMDD')
          const end = moment().add(-2, 'Y').endOf('year').format('YYYYMMDD')
          _this.innerStartValue = start
          _this.innerEndValue = end
        }},
        { key: 'quarter1', text: '一季度', type: 'season', onClick: (picker, _this) => {
          const start = moment().startOf('year').format('YYYYMMDD')
          const end = moment().startOf('year').add(3, 'M').subtract(1, 'days').format('YYYYMMDD')
          _this.innerStartValue = start
          _this.innerEndValue = end
        }},
        { key: 'quarter2', text: '二季度', type: 'season', onClick: (picker, _this) => {
          const start = moment().startOf('year').add(3, 'M').format('YYYYMMDD')
          const end = moment().startOf('year').add(6, 'M').subtract(1, 'days').format('YYYYMMDD')
          _this.innerStartValue = start
          _this.innerEndValue = end
        }},
        { key: 'quarter3', text: '三季度', type: 'season', onClick: (picker, _this) => {
          const start = moment().startOf('year').add(6, 'M').format('YYYYMMDD')
          const end = moment().startOf('year').add(9, 'M').subtract(1, 'days').format('YYYYMMDD')
          _this.innerStartValue = start
          _this.innerEndValue = end
        }},
        { key: 'quarter4', text: '四季度', type: 'season', onClick: (picker, _this) => {
          const start = moment().startOf('year').add(9, 'M').format('YYYYMMDD')
          const end = moment().endOf('year').format('YYYYMMDD')
          _this.innerStartValue = start
          _this.innerEndValue = end
        }}
      ]
    }
  },
  watch: {
    value: {
      immediate: true,
      deep: true,
      handler(newVal) {
        this.innerStartValue = newVal[this.startKey] || ''
        this.innerEndValue = newVal[this.endKey] || ''
      }
    },
    innerStartValue: {
      immediate: true,
      handler(val) {
        this.syncBothValues()
      }
    },
    innerEndValue: {
      immediate: true,
      handler(val) {
        this.syncBothValues()
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
        shortcuts = this.sortShortcuts.map(key => shortcuts.find(item => item.key === key)).filter(Boolean)
      }
      
      return shortcuts.map(item => ({
        text: item.text,
        onClick() {
          item.onClick(null, _this)
          _this.$nextTick(() => {
            _this.syncBothValues()
            _this.$emit('shortcut-click', {
              type: item.type,
              key: item.key,
              startValue: _this.innerStartValue,
              endValue: _this.innerEndValue
            })
          })
        }
      }))
    },
    pickerStartOptions() {
      const _this = this
      const maxTime = this.maxDate ? this.parseDateToTimestamp(this.maxDate) : Date.now()
      
      return {
        disabledDate: (time) => {
          if (_this.disabled) return true
          const endDate = _this.innerEndValue ? _this.parseDateToTimestamp(_this.innerEndValue) : 0
          return time.getTime() > (endDate || maxTime) || time.getTime() > maxTime
        },
        shortcuts: this.filterSortShortcuts
      }
    },
    pickerEndOptions() {
      const _this = this
      const maxTime = this.maxDate ? this.parseDateToTimestamp(this.maxDate) : Date.now()
      
      return {
        disabledDate: (time) => {
          if (_this.disabled) return true
          const startDate = _this.innerStartValue ? _this.parseDateToTimestamp(_this.innerStartValue) : 0
          return time.getTime() < (startDate || 0) || time.getTime() > maxTime
        }
      }
    }
  },
  methods: {
    parseDateToTimestamp(dateStr) {
      if (!dateStr) return Date.now()
      try {
        if (dateStr.length === 8) {
          return new Date(
            dateStr.substring(0, 4),
            dateStr.substring(4, 6) - 1,
            dateStr.substring(6, 8)
          ).getTime()
        }
        return new Date(dateStr).getTime()
      } catch (e) {
        console.warn('日期转换失败:', dateStr, e)
        return Date.now()
      }
    },

    syncBothValues() {
      const newValue = { ...this.value }
      newValue[this.startKey] = this.innerStartValue
      newValue[this.endKey] = this.innerEndValue
      this.$emit('input', newValue)
      this.$emit('change', {
        ...newValue,
        startKey: this.startKey,
        endKey: this.endKey,
        startValue: this.innerStartValue,
        endValue: this.innerEndValue
      })
      
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
::v-deep .el-date-editor.is-disabled {
  background-color: #f5f7fa;
  cursor: not-allowed;
}
</style>