<template>
  <div class="app-container">
    <el-table
      v-loading="loading"
      :data="data"
      style="width: 100%"
    >
      <el-table-column type="index" label="序号" width="80" />
      <el-table-column
        prop="type_name"
        label="反馈类型"
        width="180"
      />
      <el-table-column
        prop="content"
        label="反馈内容"
      />
      <el-table-column
        prop="created_at"
        label="反馈时间"
      />
      <el-table-column
        prop="user_name"
        label="反馈人"
      />
      <el-table-column
        prop="dep_name"
        label="反馈科室"
      />
    </el-table>
  </div>
</template>

<script>

export default {
  props: {
    data: {
      type: Array,
      default() {
        return []
      }
    },
    loading: {
      type: Boolean,
      default() {
        return false
      }
    }
  }
}
</script>

<style lang="scss" scoped>
</style>
