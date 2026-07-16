<template>
  <div class="table-box">
    <el-table
      :data="data"
      style="width: 100%">
      <el-table-column type="index" label="序号" width="50" align="center">
        <template slot-scope="scope">
          <span>{{ scope.$index + 1 + (paginationData.page - 1) * paginationData.page_size }}</span>
        </template>
      </el-table-column>
      <el-table-column prop="FYXH" label="费用序号" width="120" align="center"></el-table-column>
      <el-table-column prop="FYMC" label="费用名称" align="center" show-overflow-tooltip></el-table-column>
      <el-table-column prop="ZFJE" label="自付金额" width="120" align="center"></el-table-column>
      <el-table-column prop="JFRQ" label="计费日期" width="160" align="center"></el-table-column>
      <el-table-column prop="FYSL" label="费用数量" width="120" align="center"></el-table-column>
      <el-table-column prop="FYDJ" label="费用单价" width="120" align="center"></el-table-column>
      <el-table-column prop="ZJE" label="总金额" width="160" align="center"></el-table-column>
      <el-table-column prop="FYKS" label="费用科室" width="160" align="center" show-overflow-tooltip></el-table-column>
      <el-table-column prop="FYGB" label="费用归并" width="120"  align="center"></el-table-column>
      <el-table-column prop="SYFYGB" label="首页费用归并" width="120" align="center"></el-table-column>
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
      paginationData: {
        type: Object,
        default() {
          return {
            page: 1,
            page_size: 10,
            total: 0
          }
        }
      }
    },
    methods: {
    }
  }
</script>

<style lang="scss" scoped>
.table-box {
  margin-bottom: 20px;
}
</style>