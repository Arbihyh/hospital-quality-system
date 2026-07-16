<template>
  <div style="padding: 30px">
    <div class="block">
      <span class="demonstration">业务时间</span>
      <el-date-picker v-model="formData.startTime" type="date" format="yyyy 年 MM 月 dd 日" value-format="yyyyMMdd" placeholder="开始日期"></el-date-picker>
      <el-date-picker v-model="formData.endTime" type="date" style="margin-left: 10px" format="yyyy 年 MM 月 dd 日" value-format="yyyyMMdd" placeholder="结束日期"></el-date-picker>
      <el-button class="btn1" type="primary" @click="funQuery">查询</el-button>
      <el-button class="btn1" type="primary">下载报告</el-button>
    </div>
    <div class="tableBox">
      <el-table :data="tableData" style="width: 100%">
        <el-table-column type="index" align="center" label="序号"></el-table-column>
        <el-table-column prop="AAA28" align="center" label="病案号"></el-table-column>
        <el-table-column prop="time" align="center" label="业务时间"></el-table-column>
        <el-table-column prop="desc" align="center" label="业务操作详情"></el-table-column>
        <el-table-column prop="operator" align="center" label="业务操作人"></el-table-column>
      </el-table>
      <div class="footers">
        <mPagination v-if="tableData && tableData.length !== 0" :data="paginationData" @pageChangeEvent="pageHasChanged"></mPagination>
      </div>
    </div>
  </div>
</template>
<script>
import mPagination from '@/components/m-pagination';
export default {
  name: 'Dashboard',
  components: {
    mPagination,
  },
  data() {
    return {
      formData: {
        startTime:'',
        endTime:''
      },
      tableData: [],
      paginationData: {
        total: 10,
        currentPage: 1,
        pageSize: 10,
      },
    };
  },
  created() {
    this.funQuery();
  },
  methods: {
    pageHasChanged() {
      this.funQuery();
    },
    funQuery() {
      let pramse = {
        title: this.formData.startTime, //	start_time 业务时间-开始时间 时间戳or时间
        time: this.formData.endTime, //	end_time 业务实践-结束时间
        limit: this.paginationData.currentPage, //	end_time 每页的数据条数
        page: this.paginationData.pageSize, //	end_time 当前第几页
      };
      this.$axios.post('/workrecord', pramse).then(res => {
        console.log(res);
        this.paginationData.total = res.data.count;
        this.tableData = res.data.list;
      });
    },
  },
};
</script>

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
.tableBox {
  background: #fff;
  padding: 19px;
  border-radius: 5px;
}
.block {
  background: #fff;
  display: flex;
  align-items: center;
  border-radius: 5px;
  height: 75px;
  padding-left: 34px;
  .pickers {
    margin: 0 30px;
  }
}
.footers {
  display: flex;
  align-items: center;
  margin-top: 20px;
  justify-content: center;
  span {
    margin-left: 20px;
  }
}
.demonstration {
  padding: 0 10px;
}
.btn1 {
  margin: 0 10px;
}
</style>
