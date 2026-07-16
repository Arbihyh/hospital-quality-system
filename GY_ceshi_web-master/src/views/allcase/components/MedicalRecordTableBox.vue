<template>
  <el-table
    :data="data"
    max-height="530"
    style="width: 100%;">
    <el-table-column
      prop=""
      label="序号"
      width="80">
      <template slot-scope="scope">
        <span>{{ scope.$index + 1}}</span>
      </template>
    </el-table-column>
    <el-table-column
      prop="key"
      label="医师姓名">
    </el-table-column>
    <el-table-column
      prop="doc_count"
      label="总病例数">
      <template slot-scope="scope">
        <span class="link" @click="toPage1(scope.row)">{{ scope.row.doc_count }}</span>
      </template>
    </el-table-column>
    <el-table-column
      prop=""
      label="总扣分">
      <template slot-scope="scope">
        <span class="link" @click="toPage2(scope.row)">{{ scope.row.score }}</span>
      </template>
    </el-table-column>
  </el-table>
</template>

<script>
export default {
  props: {
    data: {
      type: Array,
      default() {
        return []
      }
    }
  },
  methods: {
    toPage1(row) {
      this.$router.push({ path: '/defectNumber', query: { doc_count: row.doc_count }})
    },
    toPage2(row) {
      this.$router.push({ path: '/defectNumber', query: { score: row.score }})
    }
  }
}
</script>

<style lang="scss" scoped>
::v-deep.el-table .el-table__row td {
  color: #7e8bab;
  border-bottom: 1px solid #f4f4f4;
}
::v-deep.el-table .el-table__header tr th:first-child {
  border-radius: 10px 0px 0px 10px;
}
::v-deep.el-table .el-table__header tr th:last-child {
  border-radius: 0px 10px 10px 0px;
}
::v-deep.el-table .el-table__header tr th {
  background: #f1f6ff;
  color: #13171e;
  border-bottom: 0px;
}
.link{
  font-weight: 600;
  color: #409eff;
  text-decoration:underline;
  cursor: pointer;
}
</style>