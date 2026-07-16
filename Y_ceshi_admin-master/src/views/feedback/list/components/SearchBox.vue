<template>
  <div class="app-container">
    <el-form :inline="true" :model="data" class="demo-form-inline">
      <el-form-item label="">
        <el-select v-model="data.type_id" filterable clearable placeholder="反馈类型">
          <el-option v-for="item of types" :key="item.id" :label="item.name" :value="item.id" />
        </el-select>
      </el-form-item>
      <el-form-item label="">
        <el-select v-model="data.dep_id" filterable clearable placeholder="反馈科室">
          <el-option v-for="item of deportments" :key="item.id" :label="item.name" :value="item.id" />
        </el-select>
      </el-form-item>
      <el-form-item label="">
        <el-input v-model="data.user_name" placeholder="反馈人" />
      </el-form-item>
      <el-form-item label="">
        <el-date-picker
          v-model="data.start_time"
          type="date"
          :picker-options="pickerOptions1"
          placeholder="开始日期"
        />
      </el-form-item>
      <el-form-item label="">
        <el-date-picker
          v-model="data.end_time"
          type="date"
          :picker-options="pickerOptions2"
          placeholder="结束日期"
        />
      </el-form-item>
      <el-form-item>
        <el-button type="primary" @click="onSubmit">查询</el-button>
      </el-form-item>
    </el-form>
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
          type_id: '',
          dep_id: '',
          user_name: '',
          start_time: '',
          end_time: ''
        }
      }
    }
  },
  data() {
    return {
      pickerOptions1: {
        disabledDate: (time) => {
          if (this.data.end_time) {
            return time.getTime() > new Date(this.data.end_time).getTime()
          } else {
            return time.getTime() > Date.now()
          }
        }
      },
      pickerOptions2: {
        disabledDate: (time) => {
          if (this.data.start_time) {
            return time.getTime() < new Date(this.data.start_time).getTime()
          } else {
            return time.getTime() > Date.now()
          }
        }
      },
      deportments: [],
      types: [
        {
          id: 1,
          name: '临床科研数据查询'
        },
        {
          id: 2,
          name: '病历指控规则'
        },
        {
          id: 3,
          name: '病案指标'
        }
      ]
    }
  },
  created() {
    this.getDeportmentList()
  },
  methods: {
    onSubmit() {
      console.log(this.data, 'this.data')
      this.$emit('search')
    },
    getDeportmentList() {
      getDeportmentList().then(res => {
        console.log(res)
        const { p } = res
        if (Object.keys(p.list).length) {
          for (const key in p.list) {
            this.deportments.push({
              id: key,
              name: p.list[key]
            })
          }
          console.log(this.deportments, 'this.deportments')
        }
      }).catch(error => {
        console.log(error)
      })
    }
  }
}
</script>

<style lang="scss" scoped>

</style>
