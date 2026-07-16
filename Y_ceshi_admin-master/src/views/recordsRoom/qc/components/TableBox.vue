<template>
  <div class="app-container">
    <el-button type="primary" @click="toExamine">批量审核</el-button>
    <el-button @click="onRevoke">撤销审核</el-button>
    <el-table
      v-loading="loading"
      :data="data"
      style="width: 100%"
      @selection-change="handleSelectionChange"
    >
      <el-table-column
          type="selection"
          width="55">
      </el-table-column>
      <el-table-column type="index" label="序号" width="80" />
      <el-table-column
        prop="sum_minus_points"
        label="病历得分"
        width="80"
        show-overflow-tooltip
      />
      <el-table-column
        prop="home_minus_points"
        label="首页得分"
        width="80"
        show-overflow-tooltip
      />
      <el-table-column
        prop="BLZT"
        label="病历状态"
        width="100"
        show-overflow-tooltip
      />
      <el-table-column
        prop=""
        label="住院号码"
        width="100"
        show-overflow-tooltip
      >
        <template slot-scope="scope">
          <el-button type="text" @click="toPage(scope.row)">{{ scope.row.AAA28 }}</el-button>
        </template>
      </el-table-column>
      <el-table-column
        prop="AAA01"
        label="姓名"
        width="100"
        show-overflow-tooltip
      />
      <el-table-column
        prop="CH"
        label="床号"
        width="100"
        show-overflow-tooltip
      />
      <el-table-column
        prop="AAA29"
        label="住院次数"
        width="100"
        show-overflow-tooltip
      />
      <el-table-column
        prop="AAC11N"
        label="接收科室"
        width="140"
        show-overflow-tooltip
      />
      <el-table-column
        prop="JSR"
        label="接收人"
        width="140"
        show-overflow-tooltip
      />
      <el-table-column
        prop="AAB01"
        label="入院时间"
        width="140"
        show-overflow-tooltip
      />
      <el-table-column
        prop="AAC01"
        label="出院时间"
        width="140"
        show-overflow-tooltip
      />
      <el-table-column
        prop="ZKY"
        label="质控员"
        width="100"
        show-overflow-tooltip
      />
      <el-table-column
        prop="ZGQR"
        label="整改日期"
        width="140"
        show-overflow-tooltip
      />
      <el-table-column
        prop="ZKKS"
        label="质控科室"
        width="140"
        show-overflow-tooltip
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
      },
      multipleSelection: []
    },
    loading: {
      type: Boolean,
      default() {
        return false
      }
    }
  },
  methods: {
    toPage(row) {
      const { ZYH } = row
      localStorage.setItem('getData', ZYH)
      this.$router.push({ path: '/recordsRoom/qc/caseViews' })
    },

    /**
     * 选中
     * @param val
     */
    handleSelectionChange(val) {
      this.multipleSelection = val;
    },

    /**
     * 批量审核
     */
    toExamine() {
      console.log(this.multipleSelection);
    },

    /**
     * 撤销审核
     */
    onRevoke() {
      console.log(this.multipleSelection);
    }
  }
}
</script>

<style lang="scss" scoped>
</style>
