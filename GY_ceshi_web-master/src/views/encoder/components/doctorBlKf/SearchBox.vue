<template>
  <div class="search-box">
    <el-form :model="data" class="demo-form-inline" label-suffix=":" label-width="74px">
      <el-row :gutter="20">
        <el-col :span="6">
          <el-form-item label="出院时间">
            <el-date-picker v-model="data.time[0]" type="date" format="yyyy 年 MM 月 dd 日" value-format="yyyyMMdd" placeholder="开始日期" clearable></el-date-picker>
            <el-date-picker v-model="data.time[1]" type="date" style="margin-left: 10px" format="yyyy 年 MM 月 dd 日" value-format="yyyyMMdd" placeholder="结束日期" clearable></el-date-picker>
          </el-form-item>
        </el-col>
        <el-col :span="6">
          <el-form-item label="医师姓名">
            <el-select v-model="data.doctor_code" multiple collapse-tags filterable clearable placeholder="全部" style="width: 100%;">
              <el-option v-for="(item, index) of doctors" :key="index" :label="item.name" :value="item.code"></el-option>
            </el-select>
          </el-form-item>
        </el-col>
        <el-col :span="6">
          <el-form-item label="出院科室">
            <el-select v-model="data.AAC11N" filterable clearable placeholder="全部" style="width: 100%;">
              <el-option v-for="(item, index) in departmentList" :key="index" :label="item.dep_name" :value="item.dep_name"></el-option>
            </el-select>
          </el-form-item>
        </el-col>
        <el-col :span="6">
          <el-form-item style="text-align: right;">
            <el-button plain @click="onReset" icon="el-icon-refresh">重置</el-button>
            <el-button type="primary" @click="onSubmit" class="export-btn" icon="el-icon-search">查询</el-button>
          </el-form-item>
        </el-col>
      </el-row>
    </el-form>
  </div>
</template>

<script>
export default {
  props: {
    data: {
      type: Object,
      default() {
        return {
          time: [],
          doctor_code: [],
          AAC11N: ''
        }
      }
    }
  },
  data() {
    return {
      doctors: [],
      departmentList: []
    }
  },
  created() {
    this.getDoctors()
    this.getDepartment()
  },
  methods: {
    // 获取医生选线
    getDoctors() {
      this.$axios2.post('/case-quality/doctor_list').then(res => {
        this.doctors = res.data;
      });
    },
    getDepartment() {
      this.$axios.post('/bmy/getAllDepartment').then(res => {
        // 不要全部选项
        this.departmentList = res.data;
      })
    },
    onSubmit() {
      this.$emit('search')
    },
    onReset() {
      this.$emit('reset')
    }
  }
}
</script>

<style>

</style>
