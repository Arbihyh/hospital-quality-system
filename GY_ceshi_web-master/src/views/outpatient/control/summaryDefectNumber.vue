<template>
  <div class="dashboard-container">
    <div class="tableBox">
      <div style="overflow: hidden; margin-bottom: 16px">
        <el-radio-group v-if="$route.query.rule_id" v-model="currentTab" @input="changeTab" style="float: left; margin-bottom: 16px">
          <el-radio-button label="汇总"></el-radio-button>
          <el-radio-button label="明细"></el-radio-button>
        </el-radio-group>
        <el-button @click="toBack" style="float: right">返回</el-button>
      </div>
      <el-form :inline="true" :model="formData" class="demo-form-inline">
        <el-form-item label="">
          <el-date-picker
            v-model="formData.startTime"
            class="selects"
            type="date"
            format="yyyy年MM月dd日"
            value-format="yyyyMMdd"
            placeholder="就诊时间-开始"
            style="width: 180px"
          ></el-date-picker>
        </el-form-item>
        <el-form-item label="">
          <el-date-picker
            v-model="formData.endTime"
            type="date"
            class="selects"
            format="yyyy年MM月dd日"
            value-format="yyyyMMdd"
            placeholder="就诊时间-结束"
            style="width: 180px"
          ></el-date-picker>
        </el-form-item>
        <el-form-item label="">
          <el-select v-model="formData.dep_id" class="selects" filterable clearable placeholder="科室" style="width: 180px">
            <el-option v-for="(item, index) in departmentList" :label="item.name" :value="item.id" :key="index"></el-option>
          </el-select>
        </el-form-item>
        <el-form-item label="">
          <el-input v-model="formData.sfzh" placeholder="身份证号" style="width: 180px"></el-input>
        </el-form-item>
        <el-form-item label="">
          <el-input v-model="formData.mzh" placeholder="门诊号" style="width: 180px"></el-input>
        </el-form-item>
        <el-form-item label="">
          <el-select v-model="formData.doctor_id" class="selects" filterable clearable placeholder="医生签名" style="width: 180px">
            <el-option v-for="(item, index) in doctors" :label="item.name" :value="item.id" :key="index"></el-option>
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="funQuery">查询</el-button>
        </el-form-item>
        <el-form-item>
          <el-button @click="onReset">重置条件</el-button>
        </el-form-item>
        <el-form-item style="margin-right: 0; float: right">
          <el-button @click="onExport" type="primary" icon="el-icon-download" style="float: right; margin-bottom: 16px">导出数据</el-button>
        </el-form-item>
      </el-form>
      <div>
        <el-table :data="department_stats" style="width: 100%">
          <el-table-column type="index" label="序号"></el-table-column>
          <el-table-column prop="rule_notice" label="缺陷问题">
            <template slot-scope="scope">
              <span>
                {{ scope.row.rule_notice }}
              </span>
            </template>
          </el-table-column>
          <el-table-column prop="dep_name" label="科室名称">
            <template slot-scope="scope">
              <span>
                {{ scope.row.dep_name }}
              </span>
            </template>
          </el-table-column>
          <el-table-column prop="defect_count" label="问题数量">
            <template slot-scope="scope">
              <span class="blue link-text" @click="funGoto(scope.row.dep_id)">
                {{ scope.row.defect_count }}
              </span>
            </template>
          </el-table-column>
        </el-table>
      </div>
    </div>
    <!-- <defectNumber v-if="currentTab === '明细'" @toBack="toBackDefect" /> -->
  </div>
</template>
  
  <script>
import Title from '@/components/Title';
// import defectNumber from './defectNumber.vue';
import { mapGetters } from 'vuex';
import mPagination from '@/components/m-pagination';
import { outHospitalErrorListExport } from '@/api/excel';
import moment from 'moment/moment';
import useScrollPosition from '@/hooks/useScrollPosition.js';


export default {
  name: 'outpatientMedicalSummaryDefectNumber',
  components: {
    Title,
    mPagination,
    // defectNumber,
  },
  computed: {
    ...mapGetters(['name']),
  },
  data() {
    return {
      currentTab: '汇总',
      formData: {
        dep_id: '',
        startTime: '',
        endTime: '',
        sfzh: '',
        doctor_id: '',
        mzh: '',
      },
      rule_id: '',
      is_error: '',
      tableData: [],
      department_stats: [],
      paginationData: {
        total: 0,
        currentPage: 1,
        pageSize: 10,
      },
      departmentList: [],
      doctors: [],
      scrollPos: 0,
      scrollHelper: null,
    };
  },

  activated() {
    // this.$nextTick(() => {
    //   const container = document.querySelector('.dashboard-container');
    //   if (container) {
    //     container.scrollTop = this.scrollPos;
    //   }
    // });
    this.initPageData();
  },
  mounted() {
    this.initPageData();
  },
  created() {
    this.scrollHelper = useScrollPosition(this, '.dashboard-container');
  },

  methods: {
    changeTab() {
      if (this.currentTab == '明细') {
        this.$router.push({
          path: '/outpatientMedicalRecordDefectNumber',
          query: {
            pageType: 'summaryTab',
            is_error: 1,
            rule_id: this.$route.query.rule_id,
            dep_id: this.$route.query.dep_id,
            doctor_id: this.$route.query.doctor_id,
            start_time: this.$route.query.start_time,
            end_time: this.$route.query.end_time,
          },
        });
      }
    },

    initPageData() {
      let doctorId = this.$route.query.doctor_id;
      let depId = this.$route.query.dep_id;
      if (typeof doctorId === 'string' && /^\d+$/.test(doctorId)) {
        doctorId = Number(doctorId);
      }
      if (typeof depId === 'string' && /^\d+$/.test(depId)) {
        depId = Number(depId);
      }
      this.rule_id = this.$route.query.rule_id;
      this.is_error = this.$route.query.is_error;

      this.formData.startTime = this.$route.query.start_time;
      this.formData.endTime = this.$route.query.end_time;
      this.formData.doctor_id = doctorId;
      this.formData.dep_id = depId;
      this.selectInfo();
      this.funQuery();
      this.currentTab = '汇总';
    },
    // 导出
    onExport() {
      const { dep_id, startTime, endTime, sfzh, doctor_id, mzh } = this.formData;
      const params = {
        dep_id,
        start_time: startTime,
        end_time: endTime,
        sfzh,
        doctor_id,
        rule_id: this.rule_id,
        is_error: this.$route.query.is_error,
        is_export: 1,
        is_dep: 1, //新增is_dep，1就是导出科室纬度的统计（2.2），0就是导出缺陷列表的详情
        mzh,
        page: this.paginationData.currentPage,
        page_size: this.paginationData.pageSize,
      };
      outHospitalErrorListExport(params).then(res => {
        const content = res.data;
        const blob = new Blob([content]);
        const fileName = `门诊病例-汇总.csv`;
        if ('download' in document.createElement('a')) {
          const elink = document.createElement('a');
          elink.download = fileName;
          elink.style.display = 'none';
          elink.href = URL.createObjectURL(blob);
          document.body.appendChild(elink);
          elink.click();
          URL.revokeObjectURL(elink.href);
          document.body.removeChild(elink);
        } else {
          navigator.msSaveBlob(blob, fileName);
        }
      });
    },
    // 重置
    onReset() {
      this.formData = {
        dep_id: '',
        startTime: moment().startOf('month').format('YYYYMMDD'),
        endTime: moment().format('YYYYMMDD'),
        sfzh: '',
        doctor_id: '',
        mzh: '',
      };
      this.funQuery();
      // const container = document.querySelector('.tableBox');
      // container && (container.scrollTop = 0);
      this.scrollHelper.scrollToTop();
    },
    // 返回
    toBack() {
      this.$router.history.go(-1);
    },
    toBackDefect() {
      this.currentTab = '汇总';
    },
    // 跳转
    funGoto(dep_id) {
      // const container = document.querySelector('.dashboard-container');
      // if (container) {
      //   this.scrollPos = container.scrollTop; // 保存当前滚动距离
      //   console.log('this.scrollPos:', this.scrollPos);
      // }
      this.scrollHelper.saveScrollPos();
      let processedDepId = dep_id;
      if (typeof processedDepId === 'string' && /^\d+$/.test(processedDepId)) {
        processedDepId = Number(processedDepId);
      }
      this.$router.push({
        path: '/outpatientMedicalRecordDefectNumber',
        query: {
          pageType: 'summary',
          is_error: 1,
          rule_id: this.$route.query.rule_id,
          dep_id: processedDepId,
          doctor_id: this.formData.doctor_id,
          start_time: this.formData.startTime,
          end_time: this.formData.endTime,
        },
      });
    },
    selectInfo() {
      this.$axios.post('/get_omr_department_list').then(res => {
        this.departmentList = res.data;
        console.log(res.data);
      });
      this.$axios.post('/omr_zk/docker_list').then(res => {
        this.doctors = res.data;
      });
    },
    pageHasChanged() {
      this.funQuery();
    },
    handleSizeChange(size) {
      this.paginationData.currentPage = 1;
      this.paginationData.pageSize = size;
      this.funQuery();
    },
    funQuery() {
      //查询
      let pramse = {
        start_time: this.formData.startTime || '',
        end_time: this.formData.endTime || '',
        page: this.paginationData.currentPage,
        page_size: this.paginationData.pageSize,
        is_error: this.$route.query.is_error,
      };
      if (this.rule_id) {
        pramse.rule_id = this.rule_id;
      }
      if (this.formData.dep_id) {
        pramse.dep_id = this.formData.dep_id;
      }
      if (this.formData.sfzh) {
        pramse.sfzh = this.formData.sfzh;
      }
      if (this.formData.doctor_id) {
        pramse.doctor_id = this.formData.doctor_id;
      }
      if (this.formData.mzh) {
        pramse.mzh = this.formData.mzh;
      }

      this.$axios.post('/omr_zk/error_list', pramse).then(res => {
        this.paginationData.total = res.data.count;
        this.tableData = res.data.list;
        if (this.rule_id) {
          this.department_stats = res.data.department_stats;
        }
      });
      const container = document.querySelector('.dashboard-container');
      container && (container.scrollTop = 0);
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
.dashboard-container {
  overflow-y: auto;
  min-height: 100vh;
}
.tableBox {
  background: #fff;
  padding: 19px;
  border-radius: 5px;
  font-size: 12px;
}
.block {
  background: #fff;
  border-radius: 5px;
  padding: 0;
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
.width300 {
  width: 295px;
}
.link-text {
  text-decoration: underline solid #185da6 1px;
  text-underline-offset: 3px;
  cursor: pointer;
  transition: all 0.2s ease;
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
  height: 75px;
  margin-bottom: 20px;
  display: flex;
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
  