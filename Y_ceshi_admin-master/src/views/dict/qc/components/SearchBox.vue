<template>
  <div class="app-container">
    <el-form :inline="true" :model="data" class="demo-form-inline">
      <el-form-item label="">
        <el-input v-model="data.name" clearable placeholder="字典名称" />
      </el-form-item>
      <el-form-item label="">
        <el-input v-model="data.keyword" clearable placeholder="映射名称" />
      </el-form-item>
      <el-form-item>
        <el-button type="primary" @click="onSubmit">查询</el-button>
        <el-button @click="onReset">重置</el-button>
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
          name: '',
          keyword: ''
        }
      }
    }
  },
  methods: {
    onSubmit() {
      this.$emit('search')
    },
    onReset() {
      this.$emit('reset')
    }
  }
}
</script>

<style lang="scss" scoped>

</style>
