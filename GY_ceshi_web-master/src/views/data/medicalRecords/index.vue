<template>
  <div class="dashboard-container">
    <div class="tableBox">
      <div class="block">
        <div class="blockCon">
          <div class="selectDns"></div>
          <el-input v-model="formData.recordNum" class="width150" placeholder="病案号"></el-input>
          <span class="kong"></span>
          <el-select v-model="formData.AAC11C" clearable filterable class="selects" placeholder="出院科室">
            <el-option v-for="(item, index) in departmentList" :label="item.name" :value="item.name" :key="index"></el-option>
          </el-select>
          <span class="kong"></span>
          <el-date-picker v-model="formData.startTime" type="date" format="yyyy 年 MM 月 dd 日" value-format="yyyyMMdd" placeholder="开始日期"></el-date-picker>
          <el-date-picker
            v-model="formData.endTime"
            type="date"
            style="margin-left: 10px"
            format="yyyy 年 MM 月 dd 日"
            value-format="yyyyMMdd"
            placeholder="结束日期"
          ></el-date-picker>
          <span class="kong"></span>
          <el-button type="primary" @click="funQuery">查询</el-button>
          <span class="kong"></span>
          <el-button @click="reset">重置条件</el-button>
        </div>
      </div>
      <div style="position: relative; margin-bottom: 30px;">
        <Title :title="'病案首页列表'" />
        <el-button type="primary" icon="el-icon-download" @click="onExport" class="export-btn">导出数据</el-button>
      </div>
      <el-table :data="tableData" style="width: 100%">
        <el-table-column type="index" label="序号"></el-table-column>
        <el-table-column prop="AAA28" label="病案号">
          <template slot-scope="scope">
            <span class="blue" @click="funGoto(scope.row.MED_REC_ID)">
              <template>
                <div>
                  {{ scope.row.AAA28 }}
                </div>
              </template>
            </span>
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
      <!-- 分页控制 -->
      <mPagination v-if="tableData && tableData.length !== 0" :data="paginationData" @pageChangeEvent="pageHasChanged"></mPagination>
    </div>
  </div>
</template>

<script>
import Title from '@/components/Title';
import { mapGetters } from 'vuex';
import mPagination from '@/components/m-pagination';
import { downloadFile } from '@/httpFile';

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
        // rangeDate: [],
        recordNum: '',
        startTime: '',
        endTime: '',
        AAC11C: ''
      },
      tableData: [],
      // 分页数据
      paginationData: {
        total: 10,
        currentPage: 1,
        pageSize: 10,
      },
      departmentList: [],
      hospital_name:'', // 医院名称
      pageType:'', 
    };
  },
  mounted() {
    this.formData.endTime = this.storageGet('endTime');
    this.formData.startTime = this.storageGet('startTime');
    this.hospital_name = this.$route.query.hospital_name?this.$route.query.hospital_name:'';
    this.pageType = this.$route.query.pageType?this.$route.query.pageType:'';
    this.selectInfo()
    this.funQuery();
  },
  beforeRouteEnter(to, from, next) {
    const { path } = from
    if (['/data/front', '/data/after', '/dashboard'].includes(path)) {
      next(vm => {
        vm.formData = {
          // rangeDate: [],
          recordNum: '',
          startTime: vm.storageGet('startTime'),
          endTime: vm.storageGet('endTime'),
        }
        vm.tableData = []
        vm.paginationData ={
          total: 10,
          currentPage: 1,
          pageSize: 10
        }
        vm.funQuery()
      })
    } else {
      next()
    }
  },
  methods: {
    toPrePage() {
      this.$router.go(-1)
    },
    funGoto(val) {
      this.storageSet('getData', val);
      this.goto('/details');
    },
    funDel() {
      this.formData1.seniorList.pop();
    },
    funAdd() {
      this.formData1.seniorList.push({
        key: '',
        value: '',
        type: '1',
      });
    },
    pageHasChanged() {
      this.funQuery();
    },
    selectInfo() {
      this.$axios.post('/selectInfo').then(res => {
        this.departmentList = res.data.department.slice(1, res.data.department.length);
      });
    },
    funQuery() {
      //查询
      let pramse = {};
      let apiUrl = '';
      if(this.pageType == 'bl_import'){
        // 数据导入进入
        pramse.hospitalName = this.hospital_name, //医院
        pramse.dep_name = this.formData.AAC11C; //科室,
        pramse.AAA28 = this.formData.recordNum,
        pramse.start_time = this.formData.startTime || '',
        pramse.end_time = this.formData.endTime || '',
        pramse.page = this.paginationData.currentPage, //页码
        pramse.page_size = this.paginationData.pageSize; //条数,
        pramse.is_export = 0;
        apiUrl = '/bl_import/getBlAll'
      }else{
        
        pramse.AAC01_start_date = this.formData.startTime || '',
        pramse.AAC01_end_date = this.formData.endTime || '',
        pramse.AAA28 = this.formData.recordNum,
        pramse.page = this.paginationData.currentPage, //页码
        pramse.limit = this.paginationData.pageSize, //条数,
        pramse.AAC11C = this.formData.AAC11C;

        apiUrl = '/qualityList'
      }
      this.$axios.post(apiUrl, pramse).then(res => {
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
    // 导出
    onExport() {

      let pramse = {
        start_time:this.formData.startTime,
        end_time:this.formData.endTime,
        AAA28: this.formData.recordNum,
        is_export: 1
      };
      let apiUrl = '';
      
      if(this.pageType == 'bl_import'){

        pramse.hospitalName = this.hospital_name;
        apiUrl = '/bl_import/getBlAll'

      }else{
        
        apiUrl = '/homeErrorDataList'
      }
      this.funExeclPost('病案首页列表', pramse, apiUrl, 'xlsx');
    },
    funExeclPost(fileName, pramse, httpUrl, format) {
      //导出
      let httpUrls = '/api' + httpUrl;
      downloadFile(httpUrls, pramse, format, fileName).then(res => {
        console.log(res);
      });
    }
  },
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
  background: #fff;
  padding: 19px;
  border-radius: 5px;
  font-size: 12px;
}
.block {
  border-radius: 5px;
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
  display: block;
  align-items: center;
  border-radius: 5px;
  height: 75px;
  margin-bottom: 20px;
  .blockCon {
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
.export-btn {
  position: absolute;
  right: 0;
  top: -10px;
}
</style>
