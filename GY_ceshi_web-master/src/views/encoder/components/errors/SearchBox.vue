<template>
  <div class="search-box">
    <el-form :model="data" class="demo-form-inline" label-suffix=":" label-width="74px">
      <el-row :gutter="20">
        <el-col :span="6">
          <el-form-item label="住院号码">
            <el-input v-model="data.AAA28" clearable placeholder="请输入"></el-input>
          </el-form-item>
        </el-col>
        <el-col :span="6">
          <el-form-item label="住院科室">
            <el-select v-model="data.AAC11C" filterable clearable placeholder="全部" style="width: 100%;">
              <el-option v-for="item in departmentList" :key="'ks'+item.dep_id" :label="item.dep_name" :value="item.dep_id"></el-option>
            </el-select>
          </el-form-item>
        </el-col>
        <el-col :span="6">
          <el-form-item label="出院时间">
            <el-date-picker
              v-model="data.AAC01"
              type="daterange"
              start-placeholder="开始时间"
              end-placeholder="结束时间"
              value-format="yyyyMMdd"
              style="width: 100%;">
            </el-date-picker>
          </el-form-item>
        </el-col>
        <el-col :span="6">
          <el-form-item label="住院医师">
            <el-select v-model="data.AEE04_CODE" filterable clearable placeholder="全部" style="width: 100%;">
              <el-option v-for="item of doctors" :key="'ys'+item.id" :label="item.label" :value="item.id"></el-option>
            </el-select>
          </el-form-item>
        </el-col>
        <el-col :span="6">
          <el-form-item label="编码员">
            <el-select v-model="data.AEE08_CODE" filterable clearable placeholder="全部" style="width: 100%;">
              <el-option v-for="item of doctors" :key="'bmy'+item.id" :label="item.label" :value="item.id"></el-option>
            </el-select>
          </el-form-item>
        </el-col>
        <el-col :span="6">
          <el-form-item label="手术名称">
            <el-input v-model="data.ICD9_NAME" clearable placeholder="请输入"></el-input>
          </el-form-item>
        </el-col>
        <el-col :span="6">
          <el-form-item label="手术编号">
            <el-input v-model="data.ICD9_ID1" clearable placeholder="请输入"></el-input>
          </el-form-item>
        </el-col>
        <el-col :span="6">
          <el-form-item label="诊断名称">
            <el-input v-model="data.ICD10_NAME" clearable placeholder="请输入"></el-input>
          </el-form-item>
        </el-col>
        <el-col :span="6">
          <el-form-item label="诊断编码">
            <el-input v-model="data.ICD10_ID1" clearable placeholder="请输入"></el-input>
          </el-form-item>
        </el-col>
        <el-col :span="6">
          <el-form-item>
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
          AAA28: '',
          AAC11C: '',
          AAC01: [],
          AEE04_CODE: '',
          AEE08_CODE: '',
          ICD9_ID1: '',
          ICD9_NAME: '',
          ICD10_ID1: '',
          ICD10_NAME: ''
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
      this.$axios.post('/selectStaff').then(res => {
        this.doctors = res.data
      })
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