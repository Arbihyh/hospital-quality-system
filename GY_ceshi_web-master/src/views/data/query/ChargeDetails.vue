<template>
  <div class="dashboard-container" :class="{'nocopy': $route.meta.nocopy}">
    <div class="tableBox">
      <Title :title="'费用明细'" />
      <el-table :data="tableData" style="width: 100%">
        <el-table-column type="index" label="序号"></el-table-column>
        <el-table-column prop="FYXH" label="费用序号"></el-table-column>
        <el-table-column prop="FYMC" label="费用名称"></el-table-column>
        <el-table-column prop="ZFJE" label="自付金额"></el-table-column>
        <el-table-column prop="JFRQ" label="计费日期"></el-table-column>
        <el-table-column prop="FYSL" label="费用数量"></el-table-column>
        <el-table-column prop="FYDJ" label="费用单价"></el-table-column>
        <el-table-column prop="ZJE" label="总金额"></el-table-column>
        <el-table-column prop="FYKS" label="费用科室"></el-table-column>
        <el-table-column prop="FYGB" label="费用归并"></el-table-column>
        <el-table-column prop="SYFYGB" label="首页费用归并"></el-table-column>
      </el-table>
      <!-- 分页控制 -->
      <mPagination v-if="tableData && tableData.length !== 0" :data="paginationData" @pageChangeEvent="pageHasChanged"></mPagination>
    </div>
  </div>
</template>

<script>
import Title from '@/components/Title';
import { mapGetters } from 'vuex';
import mPagination from '@/components/m-pagination';

export default {
  name: 'ChargeDetails',
  components: {
    Title,
    mPagination,
  },
  computed: {
    ...mapGetters(['name']),
  },
  data() {
    return {
      tableData: [],
      // 分页数据
      paginationData: {
        total: 10,
        currentPage: 1,
        pageSize: 10,
      },
      MED_REC_ID: '',
    };
  },
  mounted() {
    this.MED_REC_ID = this.$route.query.MED_REC_ID;
    this.funQuery();
  },
  methods: {
    pageHasChanged() {
      this.funQuery();
    },
    funQuery() {
      //查询
      let pramse = {
        MED_REC_ID: this.MED_REC_ID,
        page: this.paginationData.currentPage, 
        limit: this.paginationData.pageSize, 
      };
      // 20240614 建坤让替换 接口  /feeDetail =》/bmy/getFeeDetailed
      this.$axios.post('/bmy/getFeeDetailed', pramse).then(res => {
        console.log(res);
        this.paginationData.total = res.data.count;
        this.tableData = res.data.list;
      });
    },
  }
};
</script >
<style scoped>
::v-deep.el-pagination.is-background .btn-next,
::v-deep.el-pagination.is-background .btn-prev,
::v-deep.el-pagination.is-background .el-pager li {
  margin: 0 5px;
  background-color: #fff;
  color: #606266;
  min-width: 30px;
  border-radius: 2px;
  border: 1px solid #dfe3f3;
  line-height: 27px;
}
::v-deep.el-pagination.is-background .el-pager li:not(.disabled).active {
  background: #7e8bab;
}
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
</style>
<style lang="scss" scoped>
.dashboard-container{
  padding: 0;
  margin: 10px !important;
}
.tableBox {
  width: 100%;
  background: #fff;
  padding: 19px;
  border-radius: 5px;
  font-size: 12px;
}
.tableBox {
  background: #fff;
  padding: 19px;
  border-radius: 5px;
}
.dashboard {
  &-container {
    margin: 30px;
  }
  &-text {
    font-size: 30px;
    line-height: 46px;
  }
}
.pind {
  padding: 0 20px;
}
.pind10 {
  padding: 0 5px;
}
.width150 {
  width: 200px;
}
.width300 {
  width: 295px;
}
.width500 {
  width: 645px;
}
.width90 {
  width: 90px;
}
.blue {
  color: #185da6;
}
</style>
