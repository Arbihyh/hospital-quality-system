<template>
  <div class="app-container">
    <el-form :inline="true" :model="data" class="demo-form-inline">
      <el-form-item label="">
        <el-input v-model="data.ssbm" clearable placeholder="手术操作编码" />
      </el-form-item>
      <el-form-item label="">
        <el-input v-model="data.ssmc" clearable placeholder="手术操作名称" />
      </el-form-item>
      <el-form-item label="">
        <el-select v-model="data.sslb" filterable clearable placeholder="操作类型">
          <el-option label="介入治疗" value="介入治疗" />
          <el-option label="手术" value="手术" />
          <el-option label="治疗性操作" value="治疗性操作" />
          <el-option label="诊断性操作" value="诊断性操作" />
        </el-select>
      </el-form-item>
      <el-form-item label="">
        <el-select v-model="data.lrxx" filterable clearable placeholder="录入选项">
          <el-option label="选择性" value="选择性" />
          <el-option label="必选" value="必选" />
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
          sslb: '',
          lrxx: ''
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
