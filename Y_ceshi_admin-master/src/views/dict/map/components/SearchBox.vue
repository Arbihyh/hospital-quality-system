<template>
  <div class="app-container">
    <el-form :inline="true" :model="data" class="demo-form-inline">
      <el-form-item label="">
        <el-input v-model="data.ssbm" clearable placeholder="手术2.0代码" />
      </el-form-item>
      <el-form-item label="">
        <el-input v-model="data.ssmc" clearable placeholder="手术2.0名称" />
      </el-form-item>
      <el-form-item label="">
        <el-input v-model="data.ssnm" clearable placeholder="手术内码" />
      </el-form-item>

      <el-form-item label="">
        <el-input v-model="data.ssysbm" clearable placeholder="手术3.0代码" />
      </el-form-item>
      <el-form-item label="">
        <el-input v-model="data.ssysmc" clearable placeholder="手术3.0名称" />
      </el-form-item>
      <el-form-item label="">
        <el-select v-model="data.sslb" filterable clearable placeholder="操作类型">
          <el-option label="介入治疗" value="介入治疗" />
          <el-option label="手术" value="手术" />
          <el-option label="治疗性操作" value="治疗性操作" />
          <el-option label="诊断性操作" value="诊断性操作" />
        </el-select>
      </el-form-item>
      <el-form-item>
        <el-button type="primary" @click="onSubmit">查询</el-button>
      </el-form-item>
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
          ssbm: '',
          ssmc: '',
          ssysbm: '',
          ssysmc: '',
          sslb: '',
          ssnm: ''
        }
      }
    }
  },
  methods: {
    onSubmit() {
      this.$emit('search')
    }
  }
}
</script>

<style lang="scss" scoped>

</style>
