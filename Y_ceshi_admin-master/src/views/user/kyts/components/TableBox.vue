<template>
  <div class="app-container">
    <div class="btn-box">
      <el-button type="primary" icon="el-icon-download" style="float: right; margin-bottom: 15px;" @click="onExcelDownload">导出</el-button>
    </div>
    <el-table
      v-loading="loading"
      :data="data"
      style="width: 100%"
    >
      <el-table-column type="index" label="序号" width="80" />
      <el-table-column
        prop="username"
        label="账号"
        width="180"
      />
      <el-table-column
        prop="realname"
        label="医师姓名"
        width="180"
      />
      <el-table-column
        prop="dep_name"
        label="科室"
        width="180"
      />
      <el-table-column
        prop="content"
        label="搜索条件"
      />
      <el-table-column
        prop="created_at"
        label="查询时间"
        width="180"
        align="right"
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
  },
  methods: {
    onExcelDownload() {
      this.$emit('download')
    }
  }
}
</script>

<style lang="scss" scoped>
.btn-box {
  overflow: hidden;
}
</style>
