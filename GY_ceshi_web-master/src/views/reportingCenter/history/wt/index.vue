<template>
  <div>
    <div class="block">
      <div class="blockCon">
        <div class="selectDns">
          <span>上报平台</span>
          <!-- <el-select v-model="value" placeholder="请选择"> -->
          <!-- <el-option
              v-for="item in options"
              :key="item.value"
              :label="item.label"
              :value="item.value"
            >
            </el-option>
          </el-select> -->
        </div>
        <span class="demonstration">病案月份（出院日期）</span>
        <el-date-picker v-model="formData.startTime" type="date" format="yyyy 年 MM 月 dd 日" value-format="yyyyMMdd" placeholder="开始日期"></el-date-picker>

        <el-date-picker
          v-model="formData.endTime"
          type="date"
          style="margin-left: 10px"
          format="yyyy 年 MM 月 dd 日"
          value-format="yyyyMMdd"
          placeholder="结束日期"
        ></el-date-picker>
        <el-button type="primary" @click="funQuery">查询</el-button>
      </div>
    </div>
    <div class="tableBox">
      <el-table :data="tableData" style="width: 100%">
        <el-table-column type="index" label="序号" align="center"></el-table-column>
        <el-table-column prop="hospital_time" label="病案月份（出院日期）" align="center"></el-table-column>
        <el-table-column prop="hospital_num" align="center" label="出院人次"></el-table-column>
        <el-table-column prop="medical_num" align="center" label="病案数量"></el-table-column>
        <el-table-column prop="report_num" align="center" label="上报数量"></el-table-column>
        <el-table-column prop="report_probability" align="center" label="上报率"></el-table-column>
        <el-table-column prop="report_platform" align="center" label="上报平台"></el-table-column>
        <el-table-column prop="address" align="center" label="操作">
          <template slot-scope="scope">
            <el-button @click="handleClick(scope.row)" type="text" size="small">下载文件</el-button>
          </template>
        </el-table-column>
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
      tableData: [],
      formData: {
        startTime:'',
        endTime:''
      },
      paginationData: {
        total: 10,
        currentPage: 1,
        pageSize: 10,
      },
    };
  },
  mounted() {
    this.funQuery();
  },
  methods: {
    pageHasChanged() {
      this.funQuery();
    },
    handleClick() {},
    funQuery() {
      // api/errorCount
      let pramse = {
        start_time: this.formData.startTime, //开始时间
        end_time: this.formData.endTime, //结束时间
        int_type: '2', //上报平台（1：国考；2：卫统；3：医保）
        page: this.paginationData.currentPage, //页码
        limit: this.paginationData.pageSize, //条数
      };
      this.$axios.post('/reportingHistory', pramse).then(res => {
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
  margin-bottom: 20px;
  .blockCon {
    display: flex;
    align-items: center;
    .selectDns {
      span {
        margin-right: 5px;
      }
    }
    .demonstration {
      margin-left: 10px;
    }
    .pickers {
      margin-left: 5px;
    }
    .lsxd {
      margin-left: 20px;
    }
    .ins {
      width: 150px;
      margin: 0 10px;
    }
  }
  .sc {
    background: #185da6;
    color: #fff;
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
</style>
