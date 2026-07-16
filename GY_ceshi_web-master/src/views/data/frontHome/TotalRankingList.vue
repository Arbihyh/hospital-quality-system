<template>
  <div class="dashboard-container">
    <div class="tableBox">

      <div class="block">
        <div class="blockCon">
          <div class="selectDns"></div>
          <el-select v-model="formData.Department" class="selects" placeholder="出院科室">
            <el-option v-for="(item, index) in departmentList" :label="item.name" :value="item.id" :key="index"></el-option>
          </el-select>
          <span class="kong"></span>
          <el-select v-model="formData.problem" class="selects" placeholder="问题属性">
            <el-option v-for="(item, index) in levelList" :label="item.name" :value="item.id" :key="index"></el-option>
          </el-select>
          <span class="kong"></span>

          <el-date-picker v-model="formData.startTime" type="month" format="yyyy 年 MM 月" value-format="yyyyMMdd" placeholder="开始日期"></el-date-picker>

          <el-date-picker
            v-model="formData.endTime"
            type="month"
            style="margin-left: 10px"
            format="yyyy 年 MM 月"
            value-format="yyyyMMdd"
            placeholder="结束日期"
          ></el-date-picker>
          <span class="kong"></span>
          <el-button type="primary" @click="funQuery">查询</el-button>
          <span class="kong"></span>
          <el-button @click="reset">重置条件</el-button>
          <span class="kong"></span>
          <el-button type="primary" @click="funExport('总排名列表')" icon="el-icon-download" class="export-btn">导出数据</el-button>
        </div>
      </div>

      <Title :title="'总排名列表'" />
      <el-table :data="tableData" style="width: 100%" v-if="queryFleg == '1'">
        <el-table-column type="index" align="center" label="序号"></el-table-column>
        <el-table-column prop="name" align="center" label="科室"></el-table-column>
        <el-table-column prop="total_medical" align="center" label="病案数">
          <template slot-scope="scope">
            <span class="blue" @click="getBlankT(scope.row, 1)">{{ scope.row.total_medical }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="total_error" align="center" label="总缺陷">
          <template slot-scope="scope">
            <span class="blue" @click="getBlankT(scope.row, 2)">{{ scope.row.total_error }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="average_error" align="center" label="平均缺陷"></el-table-column>
        <el-table-column prop="average_score" align="center" label="平均分"></el-table-column>
        <el-table-column prop="max_score" align="center" label="最高分"></el-table-column>
        <el-table-column prop="min_score" align="center" label="最低分"></el-table-column>
        <el-table-column prop="outstanding" align="center" label="优秀率"></el-table-column>
      </el-table>
      <el-table :data="tableData" style="width: 100%" v-if="queryFleg == '2'">
        <el-table-column type="index" align="center" label="序号"></el-table-column>
        <el-table-column prop="name" align="center" label="科室名称"></el-table-column>
        <el-table-column prop="department_name" align="center" label="主诊组"></el-table-column>
        <el-table-column prop="total_medical" align="center" label="病案数">
          <template slot-scope="scope">
            <span class="blue" @click="getBlankT(scope.row, 1)">{{ scope.row.total_medical }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="control_medical" align="center" label="质控病案数">
          <template slot-scope="scope">
            <span class="blue" @click="getBlankT(scope.row, 1)">{{ scope.row.control_medical }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="complete_error_medical" align="center" label="问题病案数">
          <template slot-scope="scope">
            <span class="blue" @click="getBlankT(scope.row, 2)">{{ scope.row.complete_error_medical }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="total_error_proportion" align="center" label="问题比例"></el-table-column>
        <el-table-column align="center" label="完整性问题">
          <el-table-column prop="complete_error_medical" align="center" label="病案数">
            <template slot-scope="scope">
              <span class="blue" @click="getBlankT(scope.row, 1)">{{ scope.row.total_medical }}</span>
            </template>
          </el-table-column>
          <el-table-column prop="complete_error_proportion" align="center" label="病案比例"></el-table-column>
        </el-table-column>
        <el-table-column align="center" label="准确性问题">
          <el-table-column prop="logic_error_medical" align="center" label="逻辑性"></el-table-column>
          <el-table-column prop="standard_error_medical" align="center" label="规范性"></el-table-column>
          <el-table-column prop="code_error_medical" align="center" label="编码错误"></el-table-column>
        </el-table-column>
      </el-table>
      <el-table :data="tableData" style="width: 100%" v-if="queryFleg == '3'">
        <el-table-column type="index" align="center" label="序号"></el-table-column>
        <el-table-column prop="name" align="center" label="主治医师"></el-table-column>
        <el-table-column prop="department_name" align="center" label="科室名称">
          <template slot-scope="scope">
            <span class="blue" @click="funIndications(scope.row, 1)">{{ scope.row.department_name }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="total_medical" align="center" label="病案数">
          <template slot-scope="scope">
            <span class="blue" @click="funIndications(scope.row, 1)">{{ scope.row.total_medical }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="control_medical" align="center" label="质控病案数">
          <template slot-scope="scope">
            <span class="blue" @click="funIndications(scope.row, 1)">{{ scope.row.control_medical }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="complete_error_medical" align="center" label="问题病案数">
          <template slot-scope="scope">
            <span class="blue" @click="funIndications(scope.row, 1)">{{ scope.row.complete_error_medical }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="total_error_proportion" align="center" label="问题比例"></el-table-column>
        <el-table-column align="center" label="完整性问题">
          <el-table-column prop="complete_error_medical" align="center" label="病案数">
            <template slot-scope="scope">
              <span class="blue" @click="funIndications(scope.row, 1)">{{ scope.row.complete_error_medical }}</span>
            </template>
          </el-table-column>
          <el-table-column prop="complete_error_proportion" align="center" label="病案比例">
            <template slot-scope="scope">
              <span class="blue" @click="funIndications(scope.row, 1)">{{ scope.row.complete_error_proportion }}</span>
            </template>
          </el-table-column>
        </el-table-column>
        <el-table-column align="center" label="准确性问题">
          <el-table-column prop="logic_error_medical" align="center" label="逻辑性">
            <template slot-scope="scope">
              <span class="blue" @click="funIndications(scope.row, 2, 0)">{{ scope.row.logic_error_medical }}</span>
            </template>
          </el-table-column>
          <el-table-column prop="standard_error_medical" align="center" label="规范性">
            <template slot-scope="scope">
              <span class="blue" @click="funIndications(scope.row, 2, 0)">{{ scope.row.standard_error_medical }}</span>
            </template>
          </el-table-column>
          <el-table-column prop="code_error_medical" align="center" label="编码错误">
            <template slot-scope="scope">
              <span class="blue" @click="funIndications(scope.row, 2, 0)">{{ scope.row.code_error_medical }}</span>
            </template>
          </el-table-column>
        </el-table-column>
      </el-table>
      <el-table :data="tableData" style="width: 100%" v-if="queryFleg == '4'">
        <el-table-column type="index" align="center" label="序号"></el-table-column>
        <el-table-column prop="name" align="center" label="科室名称"></el-table-column>
        <el-table-column prop="hospital_name" align="center" label="住院医师">
          <template slot-scope="scope">
            <span class="blue" @click="funBlank(scope.row, 1)">{{ scope.row.hospital_name }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="total_medical" align="center" label="病案数">
          <template slot-scope="scope">
            <span class="blue" @click="funBlank(scope.row, 1)">{{ scope.row.total_medical }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="control_medical" align="center" label="质控病案数">
          <template slot-scope="scope">
            <span class="blue" @click="funBlank(scope.row, 1)">{{ scope.row.control_medical }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="complete_error_medical" align="center" label="问题病案数">
          <template slot-scope="scope">
            <span class="blue" @click="funBlank(scope.row, 1)">{{ scope.row.complete_error_medical }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="total_error_proportion" align="center" label="问题比例"></el-table-column>
        <el-table-column align="center" label="完整性问题">
          <el-table-column prop="complete_error_medical" align="center" label="病案数">
            <template slot-scope="scope">
              <span class="blue" @click="funBlank(scope.row, 1)">{{ scope.row.complete_error_medical }}</span>
            </template>
          </el-table-column>
          <el-table-column prop="complete_error_proportion" align="center" label="病案比例">
            <template slot-scope="scope">
              <span class="blue" @click="funBlank(scope.row, 1)">{{ scope.row.complete_error_proportion }}</span>
            </template>
          </el-table-column>
        </el-table-column>
        <el-table-column align="center" label="准确性问题">
          <el-table-column prop="logic_error_medical" align="center" label="逻辑性">
            <template slot-scope="scope">
              <span class="blue" @click="funBlank(scope.row, 2, 0)">{{ scope.row.logic_error_medical }}</span>
            </template>
          </el-table-column>
          <el-table-column prop="standard_error_medical" align="center" label="规范性">
            <template slot-scope="scope">
              <span class="blue" @click="funBlank(scope.row, 2, 1)">{{ scope.row.standard_error_medical }}</span>
            </template>
          </el-table-column>
          <el-table-column prop="code_error_medical" align="center" label="编码错误">
            <template slot-scope="scope">
              <span class="blue" @click="funBlank(scope.row, 2, 2)">{{ scope.row.code_error_medical }}</span>
            </template>
          </el-table-column>
        </el-table-column>
      </el-table>
      <el-table :data="tableData" style="width: 100%" v-if="queryFleg == '5'">
        <el-table-column type="index" align="center" label="序号"></el-table-column>
        <el-table-column prop="name" align="center" label="编码员">
          <template v-slot="{ row }">
            <span class="blue">{{ row.name }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="scores" align="center" label="分数"></el-table-column>
        <el-table-column prop="total_error" align="center" label="处理病案数">
          <!-- <template v-slot="{ row }">
                <span class="blue" @click="jump('/codeList', { AAC11C: row.department_id })">{{ row.total_error }}</span>
              </template> -->
          <template v-slot="{ row }">
            <span class="blue" @click="jump('/defectList?coder_name=' + row.name)">{{ row.total_error }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="error_proportion" align="center" label="处理病案占比"></el-table-column>
        <el-table-column prop="code_error_medical" align="center" label="编码问题病案数">
          <!-- <template v-slot="{ row }">
                <span class="blue" @click="jump('/codeList', { AAC11C: row.department_id })">{{ row.code_error_medical }}</span>
              </template> -->
          <template v-slot="{ row }">
            <span class="blue" @click="jump('/defectList?coder_name=' + row.name)">{{ row.code_error_medical }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="code_proportion" align="center" label="编码问题病案占比"></el-table-column>
      </el-table>
      <!-- 分页控制 -->
      <mPagination v-if="tableData && tableData.length !== 0" :data="paginationData" @pageChangeEvent="pageHasChanged"></mPagination>
    </div>
  </div>
</template>
  
<script>
import { downloadFile } from '@/httpFile';
import Title from '@/components/Title';
import mPagination from '@/components/m-pagination';
export default {
  name: 'Dashboard',
  components: {
    Title,
    mPagination,
  },
  computed: {},
  data() {
    return {
      formData: {
        problem: 'all',
        Department: '',
      },
      tableData: [],
      // 分页数据
      paginationData: {
        total: 10,
        currentPage: 1,
        pageSize: 10,
      },
      levelList: [], //问题属性
      departmentList: [],
      AAC11C: this.$route.query.AAC11C,
      queryFleg: '',
    };
  },
  mounted() {
    this.queryFleg = this.storageGet('jumpFleg');
    this.formData.endTime = this.storageGet('endTime');
    this.formData.startTime = this.storageGet('startTime');
    console.log(this.$route.query)
    this.selectInfo();
    this.funQuery();
  },
  methods: {
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
    jump(path, query) {
      this.$router.push(path, query);
    },
    getBlankT(depart, type) {
      if (type == 1) {
        this.$router.push(`/errorList?AAC11C=${depart.department_id}`);
      } else {
        this.$router.push(`/department?AAC11C=${depart.department_id}`);
      }
    },
    funGoto(val) {
      this.storageSet('getData', val);
      this.goto('/details');
    },
    pageHasChanged() {
      this.funQuery();
    },

    funQuery() {
      //查询
      const AAC01 = [];
      AAC01.push(this.formData.startTime);
      AAC01.push(this.formData.endTime);
      let pramse = {
        AAC01, 
        AAA28: '',
        start_time:this.formData.startTime,
        end_time:this.formData.endTime,
        level: this.formData.level, //错误等级
        page: this.paginationData.currentPage, //页码
        limit: this.paginationData.pageSize, //条数
        Department: this.formData.Department,
        problem: this.formData.problem,
        isPage:1
      };
      if (this.queryFleg == '1') {
        this.$axios.post('/ranking_department', pramse).then(res => {
          this.tableData = res.data.list;
          this.paginationData.total = res.data.count;
        });
      } else if (this.queryFleg == '2') {
        this.$axios.post('/ranking_attending_group', pramse).then(res => {
          this.tableData = res.data.list;
          this.paginationData.total = res.data.count;
        });
      } else if (this.queryFleg == '3') {
        this.$axios.post('/ranking_indications', pramse).then(res => {
          this.tableData = res.data.list;
          this.paginationData.total = res.data.count;
        });
      } else if (this.queryFleg == '4') {
        this.$axios.post('/ranking_hospital', pramse).then(res => {
          this.tableData = res.data.list;
          this.paginationData.total = res.data.count;
        });
      } else {
        this.$axios.post('/ranking_coder', pramse).then(res => {
          this.tableData = res.data.list;
          this.paginationData.total = res.data.count;
        });
      }
    },


    funExport(fileName) {
      //查询
      const AAC01 = [];
      AAC01.push(this.formData.startTime);
      AAC01.push(this.formData.endTime);
      let pramse = {
        AAC01, 
        AAA28: '',
        start_time:this.formData.startTime,
        end_time:this.formData.endTime,
        level: this.formData.level, //错误等级
        page: this.paginationData.currentPage, //页码
        limit: this.paginationData.pageSize, //条数
        Department: this.formData.Department,
        problem: this.formData.problem,
        isPage:1,
        is_export: 1,
      };
      let httpUrl = '';
      if (this.queryFleg == '1') {            // 科室总排名
        httpUrl = '/ranking_department';
      } else if (this.queryFleg == '2') {     //主诊组总排名
        httpUrl = '/ranking_attending_group';
      } else if (this.queryFleg == '3') {     //主治医师总排名
        httpUrl = '/ranking_indications';
      } else if (this.queryFleg == '4') {     //住院医师总排名
        httpUrl = '/ranking_hospital';
      } else {                                //编码员排名统计
        httpUrl = '/ranking_coder';
      }
      this.funExeclPost(fileName, pramse, httpUrl, 'xlsx');
    },
    funExeclPost(fileName, pramse, httpUrl, format) {
      //导出
      let httpUrls = '/api' + httpUrl;
      downloadFile(httpUrls, pramse, format, fileName).then(res => {
        console.error('111', res);
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
  // .selects {
  //   width: 100%;
  // }
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
  padding-left: 0;
  padding-right: 0;
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
  