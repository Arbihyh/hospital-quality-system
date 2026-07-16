<template>
  <div class="dashboard-container">
    <div class="tableBox">
      <div class="block">
        <div class="blockCon">
          <div class="selectDns"></div>
          <el-select v-model="formData.Department" class="selects" placeholder="全部">
            <el-option v-for="(item, index) in departmentList" :label="item.name" :value="item.id"
              :key="index"></el-option>
          </el-select>
          <span class="kong"></span>
          <el-select v-model="formData.problem" class="selects" placeholder="全部">
            <el-option v-for="(item, index) in levelList" :label="item.name" :value="item.id" :key="index"></el-option>
          </el-select>
          <span class="kong"></span>
          <el-button type="primary" @click="funQuery">查询</el-button>
          <span class="kong"></span>
          <el-button @click="reset">重置条件</el-button>
          <span class="kong"></span>
          <el-button type="primary" icon="el-icon-download" class="export-btn" @click="onExport">导出数据</el-button>
        </div>
      </div>
      <Title :title="'缺陷结算单数量列表'" />
      <el-table :data="tableData" style="width: 100%">
        <el-table-column type="index" label="序号"></el-table-column>
        <el-table-column prop="AAA28" label="病案号">
          <template slot-scope="scope">
            <span class="blue" @click="funGoto(scope.row.MED_REC_ID)">{{ scope.row.AAA28 }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="AAA01" label="患者姓名"></el-table-column>
        <el-table-column prop="AAC11N" label="出院科室"></el-table-column>
        <el-table-column prop="AAC03" label="出院病房"></el-table-column>
        <el-table-column prop="AAC01" label="出院时间"></el-table-column>
        <el-table-column prop="AEE04" label="住院医师"></el-table-column>
        <el-table-column prop="error_field" label="缺陷项"></el-table-column>
        <!-- <el-table-column prop="AAA01" label="取值"></el-table-column> -->
        <el-table-column prop="level" label="缺陷级别"></el-table-column>
        <el-table-column prop="desc" label="修订建议"></el-table-column>
      </el-table>
      <!-- 分页控制 -->
      <mPagination v-if="tableData && tableData.length !== 0" :data="paginationData" @pageChangeEvent="pageHasChanged">
      </mPagination>
    </div>
  </div>
</template>

<script>
import Title from '@/components/Title';
import { mapGetters } from 'vuex';
import mPagination from '@/components/m-pagination';
import { errorDataListExport} from '@/api/excel';
export default {
  name: 'Dashboard',
  components: {
    Title,
    mPagination,
  },
  computed: {
    ...mapGetters(['name']),
  },
  data() {
    return {
      formData: {
        problem: 'all',
        Department: '',
        endTime: '',
        startTime: ''
      },
      error_rule: '',
      hospital_name: '',
      error_type: '',
      coder_name: '',
      department_name: '',
      tableData: [],
      // 分页数据
      paginationData: {
        total: 10,
        currentPage: 1,
        pageSize: 10,
      },
      levelList: [], //问题属性
      departmentList: [],
    };
  },
  mounted() {
    this.formData.endTime = this.storageGet('endTime');
    this.formData.startTime = this.storageGet('startTime');
    this.error_rule = this.$route.query.error_rule;
    this.hospital_name = this.$route.query.hospital_name;
    this.error_type = this.$route.query.error_type;
    this.coder_name = this.$route.query.coder_name;
    this.department_name = this.$route.query.department_name;
    this.selectInfo();
    this.funQuery();
  },
  methods: {

    onExport() {
      let pramse = {
        AAC01: this.formData.startTime, //出院时间
        AAA28: this.formData.endTime,
        source: 1,
        is_export: 1,
        // AAC11C: this.formData.Department, //科室ID
        level: this.formData.level, //错误等级
        page: this.paginationData.currentPage, //页码
        limit: this.paginationData.pageSize, //条数
      };
      if (this.error_rule) {
        pramse.error_rule = this.error_rule;
      }
      if (this.hospital_name) {
        pramse.hospital_name = this.hospital_name;
      }

      if (this.error_type) {
        pramse.error_type = this.error_type;
      }
      if (this.coder_name) {
        pramse.coder_name = this.coder_name;
      }
      if (this.department_name) {
        pramse.department_name = this.department_name;
      }
      errorDataListExport(params).then(res => {
        const content = res.data; // 后台返回二进制数据
        const blob = new Blob([content]);
        const fileName = `病历数据.csv`;
        if ('download' in document.createElement('a')) {
          // 非IE下载
          const elink = document.createElement('a');
          elink.download = fileName;
          elink.style.display = 'none';
          elink.href = URL.createObjectURL(blob);
          document.body.appendChild(elink);
          elink.click();
          URL.revokeObjectURL(elink.href); // 释放URL 对象
          document.body.removeChild(elink);
        } else {
          // IE10+下载
          navigator.msSaveBlob(blob, fileName);
        }
      });
    },
    funGoto(val) {
      this.storageSet('getData', val);
      this.goto('SetDetails');
    },
    selectInfo() {
      // let pramse = {};
      this.$axios.post('/selectInfo').then(res => {
        this.payList = res.data.pay;
        console.log(this.payList);
        //支付方式 pay
        this.departmentList = res.data.department;
        //出院科室 department
        this.levelList = res.data.level;
      });
    },
    pageHasChanged() {
      this.funQuery();
    },
    // selectInfo() {
    //   let pramse = {};
    //   this.$axios
    //     .post("/selectInfo")
    //     .then((res) => {
    //       this.payList = res.data.pay;
    //       console.log(this.payList);
    //       //支付方式 pay
    //       this.departmentList = res.data.department;
    //       //出院科室 department
    //       this.levelList = res.data.level;
    //       //问题属性 level
    //       this.coderList = res.data.coder;
    //       //编码元  coder
    //       this.statusList = res.data.status;
    //       this.fieldList = res.data.field;
    //     });
    // },
    funQuery() {
      //查询
      let pramse = {
        AAC01: this.formData.startTime, //出院时间
        AAA28: this.formData.endTime,
        source: 1,
        // AAC11C: this.formData.Department, //科室ID
        level: this.formData.level, //错误等级
        page: this.paginationData.currentPage, //页码
        limit: this.paginationData.pageSize, //条数
      };
      if (this.error_rule) {
        pramse.error_rule = this.error_rule;
      }
      if (this.hospital_name) {
        pramse.hospital_name = this.hospital_name;
      }

      if (this.error_type) {
        pramse.error_type = this.error_type;
      }
      if (this.coder_name) {
        pramse.coder_name = this.coder_name;
      }
      if (this.department_name) {
        pramse.department_name = this.department_name;
      }
      this.$axios.post('/errorDataList', pramse).then(res => {
        console.log(res);
        this.paginationData.total = res.data.count;
        this.tableData = res.data.list;
      });
    },
    reset() {
      // 重置数据
      if (this.choice == 0) {
        Object.assign(this.$data.formData, this.$options.data().formData);
      } else {
        Object.assign(this.$data.formData, this.$options.data().formData);
      }
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
  font-size: 12px;
}

.block {
  background: #fff;
  border-radius: 5px;
  padding: 20px 30px;
  margin-bottom: 20px;

  .fBtn {
    display: flex;
    align-items: center;
    justify-content: center;
  }

  .bnh {
    margin-bottom: 20px;
  }

  .barBtn {
    display: flex;
    align-items: center;
    justify-content: center;
  }

  .selects {
    width: 100%;
  }

  .rowsa {
    margin-bottom: 20px;
  }
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

.kong {
  padding: 0 10px;
}
</style>
