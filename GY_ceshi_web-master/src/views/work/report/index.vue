<template>
  <div style="padding: 30px">
    <div class="block">
      <div class="blockCon">
        <el-radio-group v-model="formData.chooseDate" class="radio-group" size="medium" @change="chooseTime">
          <el-radio-button label="365">按年</el-radio-button>
          <el-radio-button label="90">按季</el-radio-button>
          <el-radio-button label="30">按月</el-radio-button>
        </el-radio-group>
      </div>
      <span class="kong"></span>
      <div class="selects">
        <el-date-picker
          v-model="formData.rangeDate"
          size="large"
          type="daterange"
          range-separator="-"
          start-placeholder="开始日期"
          end-placeholder="结束日期"
          format="yyyy 年 MM 月 dd 日"
          value-format="yyyyMMdd"
        ></el-date-picker>
        <span class="kong"></span>
        <el-button class="btn1" type="primary">下载报告</el-button>
      </div>
    </div>
    <div class="tableBox">
      <el-table :data="tableData" style="width: 100%">
        <el-table-column type="index" label="序号" align="center"></el-table-column>
        <el-table-column prop="type" label="报告类型" align="center"></el-table-column>
        <el-table-column prop="time" align="center" label="数据时间范围"></el-table-column>
        <el-table-column label="操作" align="center">
          <template slot-scope="scope">
            <el-button @click="handleClick(scope.row)" type="text" size="small">下载</el-button>
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
        chooseDate: '',
        rangeDate: [],
      },
      value: '',
      paginationData: {
        total: 10,
        currentPage: 1,
        pageSize: 10,
      },
    };
  },
  mounted() {
    this.formData.chooseDate = '30';
    this.chooseTime(this.formData.chooseDate);
    this.funQuery();
  },
  methods: {
    // 选择时间段
    chooseTime(time) {
      this.formData.rangeDate = this.timesCalculation(time).slice(0, 2);
    },
    handleClick() {},
    pageHasChanged() {
      this.funQuery();
    },
    funQuery() {
      let pramse = {
        title: this.formData.rangeDate[0], //	start_time 业务时间-开始时间 时间戳or时间
        time: this.formData.rangeDate[1], //	end_time 业务实践-结束时间
        limit: this.paginationData.currentPage, //	end_time 每页的数据条数
        page: this.paginationData.pageSize, //	end_time 当前第几页
      };
      this.$axios.post('/workrepoet', pramse).then(res => {
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
  margin-bottom: 20px;
  .blockCon {
    display: flex;
    .selects {
      margin: 20px;
      span {
        margin-right: 10px;
      }
    }
  }
}
.footers {
  display: flex;
  justify-content: center;
  margin-top: 20px;
}
.kong {
  margin: 0 20px;
}
</style>
