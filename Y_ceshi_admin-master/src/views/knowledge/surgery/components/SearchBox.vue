<template>
  <div class="app-container">
    <div class="btn-box">
      <el-button type="primary" icon="el-icon-s-data" style="float: left;" @click="show = !show">筛选</el-button>
      <div style="float: right;">
        <el-button type="primary" icon="el-icon-plus" style="margin-right: 10px;" @click="$emit('create')">新增</el-button>
        <el-popover
          placement="bottom-end"
          title=""
          trigger="click"
          popper-class="table_code_popper"
        >
          <el-checkbox v-model="checkAll" :indeterminate="isIndeterminate" @change="handleCheckAllChange">全选</el-checkbox>
          <el-checkbox-group v-model="showCodes" @change="handleChange">
            <el-checkbox label="FLAG">序号</el-checkbox>
            <el-checkbox label="KSMC">科室</el-checkbox>
            <el-checkbox label="SSMC">手术名称</el-checkbox>
            <el-checkbox label="BM">手术别名</el-checkbox>
            <el-checkbox label="SSBM">手术编码</el-checkbox>
            <el-checkbox label="BFZ">并发症</el-checkbox>
            <el-checkbox label="JC">检查</el-checkbox>
            <el-checkbox label="JJ">检验</el-checkbox>
            <el-checkbox label="CKWX">参考文献</el-checkbox>
            <el-checkbox label="created_at">创建时间</el-checkbox>
            <el-checkbox label="updated_at">更新时间</el-checkbox>
          </el-checkbox-group>
          <el-button slot="reference" icon="el-icon-setting">设置</el-button>
        </el-popover>
      </div>
    </div>
    <el-collapse-transition>
      <div v-if="show" style="margin-bottom: 16px;">
        <el-form :inline="true" :model="data" class="demo-form-inline">
          <el-form-item label="">
            <el-input v-model="data.FLAG" placeholder="序号" />
          </el-form-item>
          <el-form-item label="">
            <el-select v-model="data.KSMC" filterable clearable placeholder="科室">
              <el-option v-for="item of deportments" :key="item.id" :label="item.name" :value="item.name" />
            </el-select>
          </el-form-item>
          <el-form-item label="">
            <el-input v-model="data.SSMC" placeholder="手术名称" />
          </el-form-item>
          <el-form-item label="">
            <el-input v-model="data.BM" placeholder="手术别名" />
          </el-form-item>
          <el-form-item label="">
            <el-input v-model="data.SSBM" placeholder="手术编码" />
          </el-form-item>
          <el-form-item label="">
            <el-input v-model="data.BFZ" placeholder="并发症" />
          </el-form-item>
          <el-form-item label="">
            <el-input v-model="data.JC" placeholder="检查" />
          </el-form-item>
          <el-form-item label="">
            <el-input v-model="data.JJ" placeholder="检验" />
          </el-form-item>
          <el-form-item label="">
            <el-input v-model="data.CKWX" placeholder="参考文献" />
          </el-form-item>
          <el-form-item label="">
            <el-date-picker
              v-model="data.createStartTime"
              type="date"
              :picker-options="pickerOptions1"
              placeholder="创建时间-开始"
            />
          </el-form-item>
          <el-form-item label="">
            <el-date-picker
              v-model="data.createEndTime"
              type="date"
              :picker-options="pickerOptions2"
              placeholder="创建时间-结束"
            />
          </el-form-item>
          <el-form-item label="">
            <el-date-picker
              v-model="data.updateStartTime"
              type="date"
              :picker-options="pickerOptions3"
              placeholder="更新时间-开始"
            />
          </el-form-item>
          <el-form-item label="">
            <el-date-picker
              v-model="data.updateEndTime"
              type="date"
              :picker-options="pickerOptions4"
              placeholder="更新时间-结束"
            />
          </el-form-item>
          <el-form-item>
            <el-button type="primary" icon="el-icon-search" @click="onSubmit">搜索</el-button>
          </el-form-item>
          <el-form-item>
            <el-button icon="el-icon-refresh" @click="onReset">重置</el-button>
          </el-form-item>
        </el-form>
      </div>
    </el-collapse-transition>
  </div>
</template>

<script>
import { getDeportmentList } from '@/api/admin'
export default {
  props: {
    data: {
      type: Object,
      default() {
        return {
          FLAG: '',
          KSMC: '',
          BM: '',
          SSBM: '',
          SSMC: '',
          JC: '',
          JJ: '',
          BFZ: '',
          CKWX: '',
          createStartTime: '',
          createEndTime: '',
          updateStartTime: '',
          updateEndTime: ''
        }
      }
    },
    codes: {
      type: Array,
      default() {
        return []
      }
    }
  },
  data() {
    const defaultCodes = [
      'FLAG',
      'KSMC',
      'SSMC',
      'BM',
      'SSBM',
      'BFZ',
      'JC',
      'JJ',
      'CKWX',
      'created_at',
      'updated_at'
    ]
    return {
      pickerOptions1: {
        disabledDate: (time) => {
          if (this.data.createEndTime) {
            return time.getTime() > new Date(this.data.createEndTime).getTime()
          } else {
            return time.getTime() > Date.now()
          }
        }
      },
      pickerOptions2: {
        disabledDate: (time) => {
          if (this.data.createStartTime) {
            return time.getTime() < new Date(this.data.createStartTime).getTime()
          } else {
            return time.getTime() > Date.now()
          }
        }
      },
      pickerOptions3: {
        disabledDate: (time) => {
          if (this.data.updateEndTime) {
            return time.getTime() > new Date(this.data.updateEndTime).getTime()
          } else {
            return time.getTime() > Date.now()
          }
        }
      },
      pickerOptions4: {
        disabledDate: (time) => {
          if (this.data.updateStartTime) {
            return time.getTime() < new Date(this.data.updateStartTime).getTime()
          } else {
            return time.getTime() > Date.now()
          }
        }
      },
      deportments: [],
      show: false,
      showCodes: [],
      checkAll: true,
      isIndeterminate: false,
      defaultCodes
    }
  },
  created() {
    this.getDeportmentList()
    this.showCodes = JSON.parse(JSON.stringify(this.codes))
  },
  methods: {
    handleCheckAllChange(val) {
      this.showCodes = val ? this.defaultCodes : []
      this.isIndeterminate = false
      this.$emit('codesChange', this.showCodes)
    },
    // 展示字段发生变化
    handleChange(val) {
      const checkedCount = val.length
      this.checkAll = checkedCount === this.defaultCodes.length
      this.$emit('codesChange', val)
    },
    // 重置
    onReset() {
      this.$emit('reset')
    },
    // 提交
    onSubmit() {
      this.$emit('search')
    },
    getDeportmentList() {
      getDeportmentList().then(res => {
        const { p } = res
        if (Object.keys(p.list).length) {
          for (const key in p.list) {
            this.deportments.push({
              id: key,
              name: p.list[key]
            })
          }
        }
      }).catch(error => {
        console.log(error)
      })
    }
  }
}
</script>

<style lang="scss" scoped>
.btn-box {
  overflow: hidden;
  margin-bottom: 15px;
}
</style>
<style lang="scss">
.table_code_popper {
  .el-checkbox {
    display: block;
    line-height: 26px;
  }
}
</style>
