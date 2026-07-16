<template>
  <div class="pages">
    <div class="block">
      <span class="demonstration">质控周期（出院日期）</span>
      <!-- <el-date-picker
        v-model="formData.rangeDate"
        size="large"
        type="daterange"
        range-separator="-"
        start-placeholder="开始日期"
        end-placeholder="结束日期"
        format="yyyy 年 MM 月 dd 日"
        value-format="yyyyMMdd"
      ></el-date-picker> -->
      <el-date-picker v-model="formData.startTime" type="date" format="yyyy 年 MM 月 dd 日" value-format="yyyyMMdd" placeholder="开始日期"></el-date-picker>
      <el-date-picker v-model="formData.endTime" type="date" style="margin-left: 10px" format="yyyy 年 MM 月 dd 日" value-format="yyyyMMdd" placeholder="结束日期"></el-date-picker>
      <el-button @click="funQuery" type="primary">查询</el-button>
      <el-button type="primary">国考上报检查</el-button>
      <el-dropdown class="lsxd">
        <el-button class="dc">
          导出国考反馈信息
          <i class="el-icon-arrow-down el-icon--right"></i>
        </el-button>
        <el-dropdown-menu slot="dropdown">
          <el-dropdown-item>csv格式</el-dropdown-item>
          <el-dropdown-item>Excel格式</el-dropdown-item>
        </el-dropdown-menu>
      </el-dropdown>
    </div>
    <div class="bars">
      <div class="items" @click="funQueryNum()">
        <p class="names">病案数量</p>
        <p class="bar">{{ value1.total }}</p>
      </div>
      <div class="items" @click="funQueryNum()">
        <p class="names">总缺陷数</p>
        <p class="bar">{{ value1.total_error }}</p>
      </div>
      <div class="items" @click="funQueryNum()">
        <p class="names">平均得分</p>
        <p class="bar">{{ value1.avg }}</p>
      </div>
    </div>
    <div class="tableBox">
      <el-table :data="tableData" style="width: 100%">
        <el-table-column type="index" label="序号"></el-table-column>
        <el-table-column prop="AAA28" label="病案号">
          <template slot-scope="scope">
            <span class="blue" @click="funGoto(scope.row.MED_REC_ID)"></span>
          </template>
        </el-table-column>
        <el-table-column prop="AAA01" label="患者姓名"></el-table-column>
        <el-table-column prop="AAA02C" label="性别"></el-table-column>
        <el-table-column prop="AAA04" label="年龄"></el-table-column>
        <el-table-column prop="AAA29" label="住院次数"></el-table-column>
        <el-table-column prop="AAC11N" label="出院科室"></el-table-column>
        <el-table-column prop="AAC01" label="出院日期"></el-table-column>
        <el-table-column prop="ADA01" label="总费用"></el-table-column>
        <el-table-column prop="F_D" label="药品费用"></el-table-column>
        <el-table-column prop="J" label="材料费用"></el-table-column>
        <el-table-column prop="ABC01N" label="主诊断"></el-table-column>
        <el-table-column prop="ICD9_NAME" label="主手术"></el-table-column>
        <!-- <el-table-column prop="address" label="业务操作人"> </el-table-column> -->
        <el-table-column prop="AAC04" label="实际住院(天)"></el-table-column>
        <el-table-column prop="ATTEND_GRP_NAME" label="主诊组"></el-table-column>
        <el-table-column prop="AEM01C" label="离院方式"></el-table-column>
        <el-table-column prop="AAB06C" label="入院途径"></el-table-column>
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
      value1: '',
      tableData: [],
      formData: {
        rangeDate: [],
        startTime: '',
        endTime: '',
      },
      paginationData: {
        total: 10,
        currentPage: 1,
        pageSize: 10,
      },
      numFelg: 0,
    };
  },
  mounted() {
    this.funQueryOn();
    this.funQuery();
  },
  methods: {
    funGoto(val) {
      this.storageSet('getData', val);
      this.goto('/details');
    },
    pageHasChanged() {
      this.funQuery();
    },
    funQuery() {
      let pramse = {
        start_time: this.formData.startTime, //开始时间
        end_time: this.formData.endTime, //结束时间
        page: this.paginationData.currentPage, //页码
        limit: this.paginationData.pageSize, //条数
      };
      this.$axios.post('/qualityList', pramse).then(res => {
        console.log(res);
        this.paginationData.total = res.data.count;
        this.tableData = res.data.list;
      });
    },
    funQueryNum() {
      let pramse = {
        is_error: 1,
        AAC01: this.formData.rangeDate, //出院时间
        page: this.paginationData.currentPage, //页码
        limit: this.paginationData.pageSize, //条数
      };
      this.$axios.post('/qualityList', pramse).then(res => {
        console.log(res);
        this.paginationData.total = res.data.count;
        this.tableData = res.data.list;
      });
    },
    funQueryOn() {
      // api/errorCount
      let pramse = {
        AAC01: this.formData.rangeDate,
      };
      this.$axios.post('/errorCount', pramse).then(res => {
        this.value1 = res.data;
        console.log(res);
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
.pages{
  margin: 0 16px 16px 16px;
}
.tableBox {
  background: #fff;
  padding: 19px;
  border-radius: 5px;
}
.bars {
  display: flex;
  margin-bottom: 20px;
  .items {
    flex: 1;
    margin-right: 10px;
    height: 86px;
    border-radius: 5px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    padding-left: 30px;
    .names {
      margin-bottom: 8px;
      font-size: 12px;
      color: #fff;
      font-weight: 400;
    }
    p {
      margin: 0;
    }
    .bar {
      font-size: 24px;
      color: #fff;
    }
  }
  .items:nth-child(1) {
    background: url('../../../../assets/images/b1.png') no-repeat;
    background-size: 100% 100%;
  }
  .items:nth-child(2) {
    background: url('../../../../assets/images/b2.png') no-repeat;
    background-size: 100% 100%;
  }
  .items:nth-child(3) {
    margin-right: 0px;
    background: url('../../../../assets/images/b3.png') no-repeat;
    background-size: 100% 100%;
  }
}

.block {
  background: #fff;
  display: flex;
  align-items: center;
  border-radius: 5px;
  height: 75px;
  margin-bottom: 16px;
  padding-left: 34px;
  .demonstration {
    color: #333333;
  }
  .sc {
    margin: 0 10px;
    background: #35ae4a;
  }
  .dc {
    background: #d38000;
    color: #fff;
  }
  .pickers {
    margin: 0 30px;
  }
  .lsxd {
    margin-left: 20px;
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
