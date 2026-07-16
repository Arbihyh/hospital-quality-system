<template>
  <div>
    <div class="btn-box">
      <Title :title="$route.query.ruleName" style="float: left; margin-top: 11px;" />
      <el-button type="primary" style="float: right;" @click="onExport" icon="el-icon-download" class="export-btn">导出数据</el-button>
    </div>
    <el-table
      :data="list"
      style="width: 100%">
      <el-table-column
        type="index"
        label="序号"
        width="80"
        align="center">
      </el-table-column>
      <el-table-column
        prop="AAA28"
        label="住院号码"
        width="160">
        <template slot-scope="scope">
          <span class="blue" @click="funGoto(scope.row)">
            <template>
              <div class="link">
                {{ scope.row.AAA28 }}
              </div>
            </template>
          </span>
        </template>
      </el-table-column>
      <el-table-column
        prop="AAA01"
        label="患者姓名"
        width="120">
      </el-table-column>
      <el-table-column
        prop="AAC11N"
        label="出院科室"
        width="160">
      </el-table-column>
      <el-table-column
        prop="AAC01"
        label="出院时间"
        width="160">
      </el-table-column>
      <el-table-column
        prop="AAB01"
        label="入院时间"
        width="160">
      </el-table-column>
      <el-table-column
        prop="status"
        label="状态"
        width="100">
        <template slot-scope="scope">
          <el-tag v-if="scope.row.status === '正确'" type="success">正确</el-tag>
          <el-tag v-else type="danger">错误</el-tag>
        </template>
      </el-table-column>
      <el-table-column
        prop="describe"
        label="描述">
      </el-table-column>
    </el-table>
  </div>
</template>

<script>
import Title from '@/components/Title';
  export default {
    components: {
      Title
    },
    props: {
      list: {
        type: Array,
        default() {
          return []
        }
      }
    },
    methods: {
      onExport() {
        this.$emit('export')
      },
      funGoto(row) {
        this.storageSet('getData', row.MED_REC_ID);
        let path
        if (this.$route.path === '/embedIndex-caseIndexAnalysisList') {
          path = '/embedIndex-caseViews'
        } else {
          path = '/caseViews'
        }
        this.goto(path);
      },
    }
  }
</script>

<style lang="scss" scoped>
.btn-box {
  overflow: hidden;
  margin-bottom: 15px;
}
::v-deep.el-table .el-table__header tr th {
  background: #f1f6ff;
  color: #13171e;
  border-bottom: 0px;
}
::v-deep.el-table .el-table__row td {
  color: #7e8bab;
  border-bottom: 1px solid #f4f4f4;
}
::v-deep.el-table .el-table__header tr th:first-child {
  border-radius: 5px 0px 0px 5px;
}
::v-deep.el-table .el-table__header tr th:nth-child(3) {
  border-radius: 0px 5px 5px 0px;
}
.blue {
  color: #185da6;
  cursor: pointer;
}
</style>