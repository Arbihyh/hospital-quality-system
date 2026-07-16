<template>
  <el-table
    :data="data"
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
      prop="desc"
      label="缺陷描述"
      min-width="200%"
    >
    </el-table-column>
    <el-table-column
      prop="field"
      :label="`${from == 'ZMBLZK' ? '病历目录' : '缺陷字段'}`"
      ZMBLZK
    >
    </el-table-column>
    <el-table-column
      prop=""
      align="center"
      label="缺陷数量">
      <template slot-scope="scope">
        <span class="link" @click="toPage(scope.row)">{{ scope.row.total_num }}</span>
      </template>
    </el-table-column>
    <el-table-column
      prop="defect_total"
      align="center"
      label="质控病历数量"
    >
    </el-table-column>
    <el-table-column
      prop="proportion"
      align="center"
      label="缺陷占比"
      v-if="from == 'ZMBLZK'"
    >
    </el-table-column>
  </el-table>
</template>

<script>
export default {
  props: {
    from: {
      type: String,
      default() {
        return []
      }
    }, 
    data: {
      type: Array,
      default() {
        return []
      }
    }
  },
  emits: ['onGotoPage'],
  mounted() {
    console.log('------------------------------', this.$props)
  },
  methods: {
    toPage(row) {
      if(this.$props.from == 'ZMBLZK') { // 如果是来自终末病历质控
        this.$emit('onGotoPage', row)
        return
      }
      this.$router.push({ path: '/defectNumber', query: { rule_id: row.key }})
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
  color: red;
  text-decoration:underline;
  cursor: pointer;
}
</style>