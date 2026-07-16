<template>
  <div class="dashboard-container">
    <div class="tableBox">
      <div class="block">
        <el-form :model="formData" class="demo-form-inline" label-suffix=":" label-width="74px">
          <el-row :gutter="20">
            <el-col :span="6">
              <el-form-item label="出院时间">
                <el-date-picker
                  v-model="formData.time"
                  type="daterange"
                  start-placeholder="开始时间"
                  end-placeholder="结束时间"
                  value-format="yyyyMMdd"
                  style="width: 100%;">
                </el-date-picker>
              </el-form-item>
            </el-col>
            <el-col :span="6">
              <el-form-item label="病案号">
                <el-input v-model="formData.recordNum" placeholder="病案号"></el-input>
              </el-form-item>
            </el-col>
            <el-col :span="6" v-if="sort">
              <el-form-item label="医师姓名">
                <el-select v-model="doctor_name" multiple collapse-tags filterable clearable placeholder="全部" style="width: 100%;">
                  <el-option v-for="(item, index) of doctorList" :key="index" :label="item" :value="item"></el-option>
                </el-select>
              </el-form-item>
            </el-col>
            <el-col :span="6">
              <el-form-item label="出院科室">
                <el-select v-model="formData.AAC11N" clearable filterable placeholder="全部" style="width: 100%;">
                  <el-option v-for="(item, index) in departmentList" :label="item.name" :value="item.name" :key="index"></el-option>
                </el-select>
              </el-form-item>
            </el-col>
            <el-col :span="6">
              <el-form-item label="质控类型">
                <el-select v-model="formData.rule_type" clearable filterable class="selects" placeholder="全部" style="width: 100%;">
                  <el-option label="时效性" value="时效性"></el-option>
                  <el-option label="专科质控" value="专科质控"></el-option>
                  <el-option label="内涵质控" value="内涵质控"></el-option>
                  <el-option label="检查报告质控" value="检查报告质控"></el-option>
                  <el-option label="检验报告质控" value="检验报告质控"></el-option>
                  <el-option label="专病质控" value="专病质控"></el-option>
                </el-select>
              </el-form-item>
            </el-col>
            <el-col :span="6" :offset="18">
              <el-form-item style="text-align: right;">
                <el-button plain @click="handleReset" icon="el-icon-refresh">重置</el-button>
                <el-button type="primary" @click="onSearch" class="export-btn" icon="el-icon-search">查询</el-button>
              </el-form-item>
            </el-col>
          </el-row>
        </el-form>
      </div>
      <el-table :data="tableData" style="width: 100%">
        <el-table-column type="index" label="序号" width="80"></el-table-column>
        <el-table-column prop="AAC11N" label="出院科室" width="200"></el-table-column>
        <el-table-column prop="AAA28" label="病案号" width="200">
          <template slot-scope="scope">
            <span class="blue" @click="funGoto(scope.row.MED_REC_ID)">
              {{ scope.row.AAA28 }}
            </span>
          </template>
        </el-table-column>
        <el-table-column prop="AAC01" label="出院时间" width="200"></el-table-column>
        <el-table-column prop="AAA01" label="患者姓名" width="200"></el-table-column>
        <el-table-column prop="ZHFZRYSXM" label="医师姓名" width="200"></el-table-column>
        <el-table-column prop="rule_notice" label="缺陷问题描述"></el-table-column>
        <el-table-column prop="" label="扣分" v-if="sort == 'score'">
          <template slot-scope="scope">
            <span>
              {{ Number( scope.row.score ? (100 - scope.row.score) : 0 ) }}
            </span>
          </template>
        </el-table-column>
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
        AAC11N: '',
        time: [],
        startTime:'',
        endTime:'',
        recordNum: '',
        rule_type: '',
      },
      doctor_name:'', // 医师姓名
      error_rule: '',
      score:'',
      tableData: [],
      // 分页数据
      paginationData: {
        total: 10,
        currentPage: 1,
        pageSize: 10,
      },
      levelList: [], //问题属性
      departmentList: [],
      doctorList: [], // 医师姓名列表
      sort:''
    };
  },
  mounted() {
    this.error_rule = this.$route.query.rule_id;// 规则ID
    this.sort = this.$route.query.sort;
    this.doctor_name = this.$route.query.doctor_name;
    this.formData.time = [this.storageGet('start_time'),this.storageGet('end_time')];
    this.selectInfo();
    // 获取医生列表
    this.getDoctorList();
    this.funQuery();
  },
  methods: {
    // 获取医生列表
    getDoctorList(){
      this.$axios2.post('/case-quality/doctor_list').then(res => {
        this.doctorList = res.data;
      });
    },
    toBack() {
      this.$router.history.go(-1)
    },
    funGoto(val) {
      this.storageSet('getData', val);
      this.goto('/ccyxy/caseViews')
    },
    selectInfo() {
      this.$axios.post('/selectInfo').then(res => {
        this.payList = res.data.pay;
        //支付方式 pay
        this.departmentList = res.data.department.slice(1, res.data.department.length);
        //出院科室 department
        this.levelList = res.data.level;
      });
    },
    pageHasChanged() {
      this.funQuery();
    },
    handleReset() {
      this.formData = {
        problem: 'all',
        AAC11N: '',
        time:'',
        startTime:'',
        endTime:'',
        recordNum: '',
        rule_type: '',
      };
      this.doctor_name = ''; // 医师姓名
    },
    onSearch() {
      this.paginationData.currentPage = 1
      this.funQuery()
    },
    funQuery() {
      //查询
      let pramse = {
        start_time: this.formData.time[0] || '',
        end_time: this.formData.time[1] || '',
        level: this.formData.level,
        page: this.paginationData.currentPage,
        limit: this.paginationData.pageSize,
        AAA28: this.formData.recordNum, //住院号
        AAC11N: this.formData.AAC11N,  //科室
        rule_type: this.formData.rule_type, //质控类型
      };
      if (this.error_rule) {
        pramse.error_rule = this.error_rule; // 规则ID
      }
      if (this.doctor_name) {
        pramse.doctor_name = this.doctor_name; // 医师姓名
      }
      
      this.$axios.post('/errorDataList', pramse).then(res => {
        this.paginationData.total = res.data.count;
        this.tableData = res.data.list;
      });
    }
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
  cursor: pointer;
}
.block {
  background: #fff;
  align-items: center;
  border-radius: 5px;
  height: auto;
  margin-bottom: 30px;
  padding: 0;
  box-sizing: border-box;
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
  