<template>
  <div class="dashboard-container">
    <div class="tableBox">
      <div style="overflow: hidden; margin-bottom: 16px">
        <el-radio-group v-if="$route.query.rule_id && pageType === 'summaryTab'" @input="changeTab" v-model="currentTab" style="float: left; margin-bottom: 16px">
          <el-radio-button label="汇总"></el-radio-button>
          <el-radio-button label="明细"></el-radio-button>
        </el-radio-group>
        <el-button v-if="pageType != 'summaryTab'" @click="toBack" style="float: right">返回</el-button>
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
        <el-form-item label="">
          <el-select v-model="formData.review_status" class="selects" filterable clearable placeholder="审核状态" style="width: 180px">
            <el-option v-for="(item, index) in review_status_options" :label="item.label" :value="item.value" :key="index"></el-option>
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
        <div>
          <el-table :data="tableData" style="width: 100%">
            <el-table-column type="index" label="序号"></el-table-column>
            <el-table-column v-if="this.$route.query.rule_id" prop="rule_notice" label="缺陷描述"></el-table-column>
            <el-table-column prop="jzsj" label="就诊时间">
              <template slot-scope="scope">
                <span>
                  {{ scope.row.jzsj }}
                </span>
              </template>
            </el-table-column>
            <el-table-column prop="" label="门诊号">
              <template slot-scope="scope">
                <span v-if="scope.row.BLBH" class="blue link-text" @click="funGoto(scope.row.BLBH, scope.row.rule_notice, '明细')">
                  {{ scope.row.mzh }}
                </span>
                <span v-else>
                  {{ scope.row.mzh }}
                </span>
              </template>
            </el-table-column>
            <el-table-column prop="xm" label="患者姓名">
              <template slot-scope="scope">
                <span>
                  {{ scope.row.xm }}
                </span>
              </template>
            </el-table-column>
            <el-table-column prop="dep_name" label="科室">
              <template slot-scope="scope">
                <span>
                  {{ scope.row.dep_name }}
                </span>
              </template>
            </el-table-column>
            <el-table-column prop="xb" label="性别">
              <template slot-scope="scope">
                <span>
                  {{ scope.row.xb }}
                </span>
              </template>
            </el-table-column>
            <el-table-column prop="nl" label="年龄">
              <template slot-scope="scope">
                <span>
                  {{ scope.row.nl }}
                </span>
              </template>
            </el-table-column>
            <el-table-column v-if="$route.query.rule_id" prop="review_doctor" label="质控审核医师">
              <template slot-scope="scope">
                <span>
                  {{ scope.row.review_doctor }}
                </span>
              </template>
            </el-table-column>
            <el-table-column v-if="$route.query.rule_id" prop="review_time" label="质控审核时间">
              <template slot-scope="scope">
                <span>
                  {{ scope.row.review_time }}
                </span>
              </template>
            </el-table-column>
            <el-table-column v-if="$route.query.rule_id" prop="review_status" label="审核状态">
              <template slot-scope="scope">
                <span>
                  {{ scope.row.review_status === '0' ? '未审核' : '已审核' }}
                </span>
              </template>
            </el-table-column>
            <el-table-column prop="cbzd" label="初步诊断">
              <template slot-scope="scope">
                <span>
                  {{ scope.row.cbzd }}
                </span>
              </template>
            </el-table-column>
            <el-table-column prop="SFZH" label="身份证号">
              <template slot-scope="scope">
                <span>
                  {{ scope.row.SFZH }}
                </span>
              </template>
            </el-table-column>
            <el-table-column prop="SXYS_NAME" label="医生签名">
              <template slot-scope="scope">
                <span>
                  {{ scope.row.SXYS_NAME }}
                </span>
              </template>
            </el-table-column>
          </el-table>
          <mPagination
            v-if="tableData && tableData.length !== 0"
            layout="sizes, prev, pager, next, slot"
            :data="paginationData"
            @pageChangeEvent="pageHasChanged"
            @sizeChange="handleSizeChange"
          ></mPagination>
        </div>
      </div>
    </div>
  </div>
</template>
  
  <script>
import Title from '@/components/Title';
import { mapGetters } from 'vuex';
import mPagination from '@/components/m-pagination';
import { outHospitalErrorListExport } from '@/api/excel';
import moment from 'moment/moment';
import useScrollPosition from '@/hooks/useScrollPosition.js';

export default {
  name: 'OutpatientMedicalRecordDefectNumber',
  components: {
    Title,
    mPagination,
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
        review_status: '',
        mzh: '',
      },
      review_status_options: [
        {
          label: '全部',
          value: '',
        },
        {
          label: '已审核',
          value: '1',
        },
        {
          label: '未审核',
          value: '0',
        },
      ],
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
      pageType: '-1',
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
  created() {
    this.scrollHelper = useScrollPosition(this, '.dashboard-container');
  },
  mounted() {
    this.initPageData();
  },
  watch: {},
  methods: {
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
      this.pageType = this.$route.query.pageType;
      this.selectInfo();
      this.funQuery();
      if (this.pageType === 'summaryTab') {
        this.currentTab = '明细';
      }
    },

    changeTab() {
      console.log('changeTab', this.currentTab);
      if (this.currentTab == '汇总') {
        // this.toBack();
        this.$router.push({
          path: '/outpatientMedicalSummaryDefectNumber',
          query: {
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
    onExport() {
      const { dep_id, startTime, endTime, sfzh, doctor_id, mzh, review_status } = this.formData;
      const params = {
        dep_id,
        start_time: startTime,
        end_time: endTime,
        sfzh,
        doctor_id,
        rule_id: this.rule_id,
        is_error: this.$route.query.is_error,
        is_export: 1,
        mzh,
        page: this.paginationData.currentPage,
        page_size: this.paginationData.pageSize,
      };
      if (this.$route.query.rule_id) {
        params.is_dep = 0; //新增is_dep，1就是导出科室纬度的统计（2.2），0就是导出缺陷列表的详情
        params.review_status = review_status;
      }

      outHospitalErrorListExport(params).then(res => {
        const content = res.data; // 后台返回二进制数据
        const blob = new Blob([content]);
        const fileName = `门诊病例.csv`;
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
          navigator.msSaveBlob(blob, fileName);
        }
      });
    },
    onReset() {
      this.formData = {
        dep_id: '',
        startTime: moment().startOf('month').format('YYYYMMDD'),
        endTime: moment().format('YYYYMMDD'),
        sfzh: '',
        doctor_id: '',
        review_status: '',
        mzh: '',
      };
      this.funQuery();

      // const container = document.querySelector('.tableBox');
      // container && (container.scrollTop = 0);
      this.scrollHelper.scrollToTop();
    },
    // 返回
    toBack() {
      // if (!this.$route.query.rule_id || this.$route.query.pageType == 'summary') {
      //   this.$router.history.go(-1);
      // } else {
      //   this.$emit('toBack');
      // }
      this.$router.history.go(-1);
    },
    // 跳转详情
    funGoto(id, rule_notice, type) {
      this.scrollHelper.saveScrollPos();
      if (type == '汇总') {
        this.$nextTick(() => {
          this.formData.dep_id = id;
          this.currentTab = '明细';
          this.funQuery();
        });
        // this.currentTab = '明细';
        // this.funQuery();
      } else {
        this.$router.push({ path: '/outpatientMedicalRecordDetail', query: { blbh: id, notice: rule_notice, pageType: 'outpatient' } });
      }
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

      if (this.$route.query.rule_id) {
        pramse.review_status = this.formData.review_status;
      }

      this.$axios.post('/omr_zk/error_list', pramse).then(res => {
        this.paginationData.total = res.data.count;
        this.tableData = res.data.list;
        if (this.rule_id) {
          this.department_stats = res.data.department_stats;
        }
      });

      // const container = document.querySelector('.tableBox');
      // container && (container.scrollTop = 0);
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
