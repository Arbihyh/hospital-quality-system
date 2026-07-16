<template>
  <div class="dashboard-container">
    <div class="tableBox">
      
      <div class="block">
        <div class="blockCon">
          <div class="selectDns"></div>
          <el-input v-model="formData.AAA28" clearable placeholder="病案号" style="width: 200px;"></el-input>
          <span class="kong"></span>
          <el-select v-model="formData.AAC11C" clearable filterable class="selects" placeholder="出院科室">
            <el-option v-for="(item, index) in departmentList" :label="item.name" :value="item.name" :key="index"></el-option>
          </el-select>
          <span class="kong"></span>
          <el-select v-model="formData.level" class="selects" clearable filterable placeholder="问题属性">
            <el-option v-for="(item, index) in levelList" :label="item.name" :value="item.id" :key="index"></el-option>
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
          <!-- <span class="demonstration">反馈关键词</span> -->
          <!-- <el-input class="ins" placeholder="请输入内容"></el-input> -->
          <el-button type="primary" @click="funQuery">查询</el-button>
          <span class="kong"></span>
          <el-button @click="reset">重置条件</el-button>
        </div>
        <div style="margin: 20px 0; text-align: right;">
          <el-button type="primary" icon="el-icon-download" @click="onExport" class="export-btn">导出数据</el-button>
        </div>
      </div>
      <Title :title="'缺陷列表'" />
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
        <el-table-column prop="desc" label="修订建议"></el-table-column>
        <!-- <el-table-column  label="操作"></el-table-column> -->
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
        level: 'all',
        endTime:'',
        startTime:'',
        AAA28: '',
        AAC11C: ''
      },
      error_rule: '',
      hospital_name: '',
      hospitalName: '',
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
  async mounted() {
    await this.selectInfo();
    this.formData.endTime = this.storageGet('endTime');
    this.formData.startTime = this.storageGet('startTime');
    this.error_rule = this.$route.query.error_rule;
    this.hospital_name = this.$route.query.hospital_name;
    this.hospitalName = this.$route.query.hospitalName;
    this.error_type = this.$route.query.error_type;
    this.coder_name = this.$route.query.coder_name;
    this.department_name = this.$route.query.department_name;
    this.funQuery();
  },
  async activated() {
    await this.selectInfo();
    this.department_name = this.$route.query.department_name;
  },
  beforeRouteEnter(to, from, next) {
    const { path } = from
    if (['/data/front', '/data/after'].includes(path)) {
      next(vm => {
        vm.formData = {
          level: 'all',
          Department: '',
          endTime: vm.storageGet('endTime'),
          startTime: vm.storageGet('startTime'),
          AAA28: ''
        }
        vm.tableData = []
        vm.departmentList = []
        vm.levelList = []
        vm.paginationData ={
          total: 10,
          currentPage: 1,
          pageSize: 10
        }
        vm.error_rule = vm.$route.query.error_rule
        vm.hospital_name = vm.$route.query.hospital_name
        vm.error_type = vm.$route.query.error_type
        vm.coder_name = vm.$route.query.coder_name
        vm.department_name = vm.$route.query.department_name
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
    selectInfo() {
      // let pramse = {};
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
    funQuery() {
      //查询
      const AAC01 = [];
      AAC01.push(this.formData.startTime);
      AAC01.push(this.formData.endTime);
      let pramse = {
        AAC01, 
        AAA28: this.formData.AAA28,
        start_time:this.formData.startTime,
        end_time:this.formData.endTime,
        level: this.formData.level, //错误等级
        page: this.paginationData.currentPage, //页码
        limit: this.paginationData.pageSize, //条数
        isPage:1,
        AAC11C: this.formData.AAC11C
      };
      if (this.error_rule) {
        pramse.error_rule = this.error_rule;
      }
      if (this.hospital_name) {
        pramse.hospital_name = this.hospital_name;
      }
      if (this.hospitalName) {
        pramse.hospitalName = this.hospitalName;
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
      this.$axios.post('/homeErrorDataList', pramse).then(res => {
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
        level: this.formData.level,
        AAC11C: this.formData.AAC11C,
        is_export: 1,
        AAA28: ''
      };
      if(this.hospitalName){
        pramse.hospitalName = this.hospitalName;
      }
      if (this.error_rule) {
        pramse.error_rule = this.error_rule;
      }
      this.funExeclPost('病案首页列表', pramse, '/homeErrorDataList', 'xlsx');
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
  display: block;
  align-items: center;
  border-radius: 5px;
  height: 75px;
  padding-left: 0;
  padding-right: 0;
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
</style>
