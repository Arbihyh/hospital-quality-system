<template>
  <div class="search-box">
    <el-form :model="data" class="demo-form-inline" label-suffix=":" label-width="74px">
      <el-row :gutter="20">
        <el-col :span="6">
          <el-form-item label="住院号码">
            <el-input v-model="data.ZYH" clearable placeholder="请输入"></el-input>
          </el-form-item>
        </el-col>
        <el-col :span="6">
          <el-form-item label="费用名称">
            <el-input v-model="data.FYMC" clearable placeholder="请输入"></el-input>
          </el-form-item>
        </el-col>
        <el-col :span="6">
          <el-form-item label="计费日期">
            <el-date-picker
              v-model="data.time"
              type="daterange"
              start-placeholder="开始时间"
              end-placeholder="结束时间"
              value-format="yyyyMMdd"
              style="width: 100%;">
            </el-date-picker>
          </el-form-item>
        </el-col>
        <el-col :span="6">
          <el-form-item label="科室">
            <el-select v-model="data.FYKS" filterable clearable placeholder="全部" style="width: 100%;">
              <el-option v-for="(item, index) in departmentList" :key="index" :label="item.dep_name" :value="item.dep_id"></el-option>
            </el-select>
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
          ZYH: '',
          FYMC: '',
          time: [],
          FYKS: ''
        }
      }
    }
  },
  data() {
    return {
      departmentList: []
    }
  },
  created() {
    this.getDepartment()
  },
  methods: {
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