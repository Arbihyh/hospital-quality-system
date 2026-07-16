<template>
  <div class="dashboard-container">
    <div class="block">
      <!-- <div class="lefts"> -->
      <!-- <el-button-group> -->
      <!-- <el-button>按年</el-button> -->
      <el-dropdown>
        <el-button :class="formData.year.name ? 'color-btn' : ''">
          {{ formData.year.name || '按年' }}
          <i class="el-icon-arrow-down el-icon--right"></i>
        </el-button>
        <el-dropdown-menu slot="dropdown">
          <el-dropdown-item v-for="(item, index) in yearList" :key="index" @click.native="funSeleterYear(item)">{{ item.name }}</el-dropdown-item>
        </el-dropdown-menu>
        <!-- <el-dropdown-item @click.native="funSeleterYear({ name: '2009', num: '2009' })">2009</el-dropdown-item> -->
        <!-- </el-dropdown-menu> -->
      </el-dropdown>

      <el-dropdown>
        <el-button :class="formData.quarter.name ? 'color-btn' : ''">
          {{ formData.quarter.name || '按季' }}
          <i class="el-icon-arrow-down el-icon--right"></i>
        </el-button>
        <el-dropdown-menu slot="dropdown">
          <el-dropdown-item v-for="(item, index) in quarterList" :key="index" @click.native="funSeleterQuarter(item)">{{ item.name }}</el-dropdown-item>
        </el-dropdown-menu>
      </el-dropdown>

      <el-dropdown>
        <el-button :class="formData.month.name ? 'color-btn' : ''">
          {{ formData.month.name || '按月' }}
          <i class="el-icon-arrow-down el-icon--right"></i>
        </el-button>
        <!-- <el-dropdown-menu slot="dropdown"> -->
        <el-dropdown-menu slot="dropdown">
          <el-dropdown-item v-for="(item, index) in monthList" :key="index" @click.native="funSeleterMonth(item)">{{ item.name }}</el-dropdown-item>
        </el-dropdown-menu>
        <!-- <el-dropdown-item @click.native="funSeleterMonth({ name: '2009', num: '2009' })">2009</el-dropdown-item> -->
        <!-- </el-dropdown-menu> -->
      </el-dropdown>
      <!-- <el-button>按季</el-button>
          <el-button>按月</el-button> -->
      <!-- </el-button-group> -->
      <div class="selects">
        <el-date-picker v-model="formData.startTime" type="date" format="yyyy 年 MM 月 dd 日" value-format="yyyyMMdd" placeholder="开始日期"></el-date-picker>

        <el-date-picker
          v-model="formData.endTime"
          type="date"
          style="margin-left: 10px"
          format="yyyy 年 MM 月 dd 日"
          value-format="yyyyMMdd"
          placeholder="结束日期"
        ></el-date-picker>
      </div>
      <el-button type="primary" @click="getListData">查询</el-button>
      <el-button type="primary" icon="el-icon-download" @click="funExport('病案首页缺陷分析','/errorList')" class="export-btn">导出数据</el-button>
    </div>
    <div class="tableBox">
      <div class="left">
        <div class="scds">
          <el-input placeholder="请选择内容" @input="funInput()" v-model="formData.state" suffix-icon="el-icon-search"></el-input>
        </div>
        <!-- <div class="Leftlist"> -->
        <div class="leftData-case">
          <el-tree :data="leftData" :props="defaultProps" @node-click="handleNodeClick"></el-tree>
        </div>

        <!-- <div class="items" v-for="(item,index) in leftData" :key="index">
              {{item.field}}
            </div> -->
        <!-- </div> -->
      </div>
      <div class="rightTable">
        <el-table :data="tableData" class="tables">
          <el-table-column type="index" label="序号" align="center"></el-table-column>
          <el-table-column prop="AAA28" label="病案号" align="center">
            <template slot-scope="scope">
              <span class="blue" @click="funGoto(scope.row.MED_REC_ID)">{{ scope.row.AAA28 }}</span>
            </template>
          </el-table-column>
          <el-table-column prop="AAA01" align="center" label="患者姓名"></el-table-column>
          <el-table-column prop="AAC11N" align="center" label="出院科室"></el-table-column>
          <el-table-column prop="AAB01" align="center" label="入院时间"></el-table-column>
          <el-table-column prop="AAC01" align="center" label="出院时间"></el-table-column>
          <el-table-column prop="address" align="center" label="数据项"></el-table-column>
          <el-table-column prop="error_name" align="center" label="缺陷字段"></el-table-column>
          <el-table-column prop="desc" align="center" label="缺陷描述"></el-table-column>
        </el-table>
        <div class="footers">
          <mPagination v-if="tableData && tableData.length !== 0" :data="paginationData" @pageChangeEvent="pageHasChanged"></mPagination>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
// import { mapGetters } from 'vuex';
import mPagination from '@/components/m-pagination';
import { downloadFile } from '@/httpFile'
export default {
  name: 'Dashboard',
  components: {
    mPagination,
  },
  computed: {},
  data() {
    return {
      value: '',
      value1: '',
      tableData: [],
      defaultProps: {
        children: 'children',
        label: 'field',
      },
      ftoe: {
        start_time: '',
        end_time: '',
      },
      formData: {
        state: '',
        startTime: '',
        endTime: '',
        year: {
          name: '',
        },
        month: {
          name: '',
        },
        quarter: {
          name: '',
        },
        problem: 'all',
        defectFelg: 'all',
        type: '1',
      },
      options: [],
      //对接数据
      leftData: [],
      paginationData: {
        total: 10,
        currentPage: 1,
        pageSize: 10,
      },
      quarterList: [],
      monthList: [],
      yearList: [],
    };
  },
  mounted() {
    this.getLeftData();
    this.getListData();
    this.selectInfo();
  },
  methods: {
    funExport(fileName,httpUrl){
      let type_id = '';
      if (this.formData.type == '1') {
        type_id = this.formData.year.id || '';
      } else if (this.formData.type == '2') {
        type_id = this.formData.quarter.id;
      } else {
        type_id = this.formData.month.id;
      }
      if ((this.formData.startTime && !this.formData.endTime) || (!this.formData.startTime && this.formData.endTime)) {
        this.$message.error('请完成时间区间选择');
        return;
      }
      let pramse = {
        type: this.formData.type, //按年/按月/按季
        type_id: type_id,
        start_time: this.formData.startTime, //开始时间 时间戳or时间
        end_time: this.formData.endTime, //结束时间
        qa_status: '1',
        is_export:'1',
        error_id: '',
        page: this.paginationData.currentPage, //页码
        limit: this.paginationData.pageSize, //条数
      };
      this.funExeclPost(fileName,pramse,httpUrl,'xlsx')
    },
    funExeclPost(fileName,pramse,httpUrl,format){
      //导出
      let httpUrls = '/api'+ httpUrl
      downloadFile(httpUrls,pramse, format, fileName)
        .then(res => {
          console.log(res)
        })
    },
    selectInfo() {
      // let pramse = {};
      this.$axios.post('/selectInfo').then(res => {
        // this.payList = res.data.pay;
        // console.log(this.payList);
        // this.departmentList = res.data.department;
        //出院科室 department
        // this.levelList = res.data.level;
        //问题属性 level
        this.quarterList = res.data.quarter;
        // 季度
        this.monthList = res.data.month;
        //月
        this.yearList = res.data.year;
      });
    },
    funSeleterYear(val) {
      this.formData.year = val;
      this.formData.type = '1';
      this.formData.month = {
        name: '',
      };
      this.formData.quarter = {
        name: '',
      };
      this.formData.endTime = this.goTimeTwe(val.end);
      this.formData.startTime = this.goTimeTwe(val.start);
    },
    funSeleterMonth(val) {
      this.formData.month = val;
      this.formData.type = '3';
      this.formData.year = {
        name: '',
      };
      this.formData.quarter = {
        name: '',
      };
      this.formData.endTime = this.goTimeTwe(val.end);
      this.formData.startTime = this.goTimeTwe(val.start);
    },
    funSeleterQuarter(val) {
      this.formData.type = '2';
      this.formData.quarter = val;
      this.formData.year = {
        name: '',
      };
      this.formData.month = {
        name: '',
      };
      this.formData.endTime = this.goTimeTwe(val.end);
      this.formData.startTime = this.goTimeTwe(val.start);
    },
    funInput() {
      var list = this.storageGet('leftData');
      console.log(list);
      var len = list.length;
      var arr = [];
      for (var i = 0; i < len; i++) {
        //如果字符串中不包含目标字符会返回-1
        if (list[i].field.indexOf(this.formData.state) >= 0) {
          arr.push(list[i]);
        }
      }
      this.leftData = arr;
    },
    pageHasChanged() {
      this.getListData();
    },
    funGoto(val) {
      this.storageSet('getData', val);
      this.goto('/details');
    },
    getListData() {
      let type_id = '';
      if (this.formData.type == '1') {
        type_id = this.formData.year.id || '';
      } else if (this.formData.type == '2') {
        type_id = this.formData.quarter.id;
      } else {
        type_id = this.formData.month.id;
      }
      if ((this.formData.startTime && !this.formData.endTime) || (!this.formData.startTime && this.formData.endTime)) {
        this.$message.error('请完成时间区间选择');
        return;
      }
      let pramse = {
        // type: this.formData.type, //按年/按月/按季
        // type_id: type_id,
        start_time: this.formData.startTime, //开始时间 时间戳or时间
        end_time: this.formData.endTime, //结束时间
        qa_status: '1',
        error_id: '',
        page: this.paginationData.currentPage, //页码
        limit: this.paginationData.pageSize, //条数
      };
      this.$axios.post('/errorList', pramse).then(res => {
        //this.leftData
        this.tableData = res.data.list;
        this.paginationData.total = res.data.count;
        // console.log(res.data.list, 1);
      });
    },
    getLeftData() {
      this.$axios.post('/ruleList', {}).then(res => {
        this.leftData = res.data;
        this.storageSet('leftData', res.data);
      });
    },
    handleNodeClick(data) {
      console.log(data);
      let type_id = '';
      if (this.formData.type == '1') {
        type_id = this.formData.year.id || '';
      } else if (this.formData.type == '2') {
        type_id = this.formData.quarter.id;
      } else {
        type_id = this.formData.month.id;
      }
      if ((this.formData.startTime && !this.formData.endTime) || (!this.formData.startTime && this.formData.endTime)) {
        this.$message.error('请完成时间区间选择');
        return;
      }
      let pramse = {
        // type: this.formData.type, //按年/按月/按季
        // type_id: type_id,
        start_time: this.formData.startTime, //开始时间 时间戳or时间
        end_time: this.formData.endTime, //结束时间
        qa_status: '1',
        error_id: data.id,
        page: this.paginationData.currentPage, //页码
        limit: this.paginationData.pageSize, //条数
      };
      this.$axios.post('/errorList', pramse).then(res => {
        //this.leftData
        // localStorage.setItem("fydataInfo", JSON.stringify(this.dataInfo));
        this.tableData = res.data.list;
        this.paginationData.total = res.data.count;
        // console.log(res.data.list, 1);
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
.Leftlist {
  .items {
    line-height: 40px;
  }
}
.tableBox {
  background: #fff;
  padding: 19px;
  border-radius: 5px;
  display: flex;
  .left {
    width: 332px;
  }
  .rightTable {
    margin-left: 25px;
    flex: 1;
  }
}
.block {
  background: #fff;
  border-radius: 5px;
  padding: 20px 30px;
  margin-bottom: 20px;
  display: flex;
  align-items: center;
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
.footers {
  display: flex;
  align-items: center;
  margin-top: 20px;
  justify-content: center;
  span {
    margin-left: 20px;
  }
}
.leftData-case {
  margin: 50px 0;
  width: 330px;
  height: 750px;
  overflow: scroll;
}
.color-btn {
  color: #409eff;
  border-color: #c6e2ff;
  background-color: #ecf5ff;
}
</style>
