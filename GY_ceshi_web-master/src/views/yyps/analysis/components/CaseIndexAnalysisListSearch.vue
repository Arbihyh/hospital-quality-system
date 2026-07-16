<template>
  <div>
    <el-form :inline="true" :model="data" class="demo-form-inline">
      <el-form-item label="">
        <el-select v-model="data.dep_name" filterable placeholder="请选择科室">
          <el-option v-for="(item, index) of departments" :key="index" :label="item.name" :value="item.name" />
        </el-select>
      </el-form-item>
      <el-form-item label="" style="margin-bottom: 0">
        <el-select v-model="data.status" clearable placeholder="请选择状态">
          <el-option label="正确" :value="1"></el-option>
          <el-option label="错误" :value="0"></el-option>
        </el-select>
      </el-form-item>
      <el-form-item label="">
        <el-date-picker v-model="data.start_time" type="date" :picker-options="pickerOptions1" placeholder="开始日期"></el-date-picker>
      </el-form-item>
      <el-form-item label="">
        <el-date-picker v-model="data.end_time" type="date" :picker-options="pickerOptions2" placeholder="结束日期"></el-date-picker>
      </el-form-item>
      <el-form-item>
        <el-button type="primary" @click="onSubmit">查询</el-button>
      </el-form-item>
      <el-form-item>
        <el-button @click="onReset">重置条件</el-button>
      </el-form-item>
      <el-form-item style="float: right;">
        <el-button @click="onBack">返回</el-button>
      </el-form-item>
    </el-form>
  </div>
</template>

<script>
import moment from 'moment'
  export default {
    props: {
      data: {
        type: Object,
        default() {
          return {
            dep_name: '',
            status: '',
            start_time: '',
            end_time: ''
          }
        }
      }
    },
    data() {
      return {
        pickerOptions1: {
          disabledDate: time => {
            if (this.data.end_time) {
              return time.getTime() > new Date(this.data.end_time).getTime();
            } else {
              return time.getTime() > Date.now();
            }
          },
        },
        pickerOptions2: {
          disabledDate: time => {
            if (this.data.start_time) {
              return time.getTime() < new Date(this.data.start_time).getTime();
            } else {
              return time.getTime() > Date.now();
            }
          },
        },
        departments: []
      }
    },
    async created() {
      await this.getDeportmentList()
      const { dep_name, start, end, status } = this.$route.query
      this.data.dep_name = dep_name
      this.data.start_time = moment(start)
      this.data.end_time = moment(end)
      this.data.status = status ? parseInt(status) : ''
      this.onSubmit()
    },
    methods: {
      onReset() {
        this.data.status = ''
        this.data.start_time = ''
        this.data.end_time = ''
        this.$emit('search')
      },
      onSubmit() {
        this.$emit('search')
      },
      getDeportmentList() {
        this.$axios.post("/get_department_list").then((res) => {
          // 不要全部选项
          this.departments = res.data
        });
      },
      onBack() {
        this.$router.go(-1)
      }
    }
  }
</script>

<style lang="scss" scoped>

</style>