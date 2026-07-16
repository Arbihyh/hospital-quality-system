<template>
  <div class="dashboard-container">
    <div class="tableBox">
      <div style="overflow: hidden; margin-bottom: 16px">
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
            placeholder="开始时间"
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
            placeholder="结束时间"
            style="width: 180px"
          ></el-date-picker>
        </el-form-item>
        <el-form-item label="">
          <el-select v-model="formData.dep_id" class="selects" filterable clearable placeholder="科室" style="width: 180px">
            <el-option v-for="(item, index) in departmentList" :label="item.name" :value="item.id" :key="index"></el-option>
          </el-select>
        </el-form-item>
        <el-form-item label="">
          <el-select v-model="formData.doctor_id" class="selects" filterable clearable placeholder="医生签名" style="width: 180px">
            <el-option v-for="(item, index) in doctors" :label="item.name" :value="item.id" :key="index"></el-option>
          </el-select>
        </el-form-item>
        <!-- 移除：身份证号、门诊号（新接口无对应参数） -->
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
        <el-table :data="tableData" style="width: 100%">
          <el-table-column type="index" label="序号"></el-table-column>
          <el-table-column prop="rule_notice" label="缺陷描述"></el-table-column>
          <el-table-column prop="jzsj" label="就诊时间"></el-table-column>
          <el-table-column prop="" label="门诊号">
            <template slot-scope="scope">
              <span v-if="scope.row.BLBH" class="blue link-text" @click="funGoto(scope.row.BLBH)">
                {{ scope.row.mzh }}
              </span>
              <span v-else>
                {{ scope.row.mzh }}
              </span>
            </template>
          </el-table-column>
          <el-table-column prop="xm" label="患者姓名"></el-table-column>
          <el-table-column prop="dep_name" label="科室"></el-table-column>
          <el-table-column prop="xb" label="性别"></el-table-column>
          <el-table-column prop="nl" label="年龄"></el-table-column>
          <el-table-column prop="cbzd" label="初步诊断"></el-table-column>
          <el-table-column prop="SFZH" label="身份证号"></el-table-column>
          <el-table-column prop="SXYS_NAME" label="医生签名"></el-table-column>
        </el-table>
      </div>
      <!-- 分页控制 -->
      <mPagination
        v-if="tableData && tableData.length !== 0"
        layout="sizes, prev, pager, next, slot"
        :data="paginationData"
        @pageChangeEvent="pageHasChanged"
        @sizeChange="handleSizeChange"
      ></mPagination>
    </div>
  </div>
</template>
  
<script>
import Title from '@/components/Title';
import { mapGetters } from 'vuex';
import mPagination from '@/components/m-pagination';
import { outHospitalExport } from '@/api/excel';
import useScrollPosition from '@/hooks/useScrollPosition.js';


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
        dep_id: '', // 科室id
        startTime: '', // 开始时间
        endTime: '', // 结束时间
        doctor_id: '', // 医师id
        // 移除：sfzh、mzh（新接口无对应参数）
      },
      tableData: [],
      // 分页数据
      paginationData: {
        total: 0,
        currentPage: 1,
        pageSize: 10,
      },
      departmentList: [],
      doctors: [],
      scrollHelper: null,
    };
  },
  mounted() {
    // 移除：rule_id、路由参数相关（新接口无该参数）
    // this.formData.startTime = this.storageGet('start_time');
    // this.formData.endTime = this.storageGet('end_time');
    this.formData.startTime = this.$route.query.start_time;
    this.formData.endTime = this.$route.query.end_time;
    this.formData.doctor_id = this.$route.query.doctor_id;
    this.formData.dep_id = this.$route.query.dep_id;
    this.selectInfo();
    this.funQuery();
  },
  watch: {
    // $route(to, from) {
    //   if (to.path === '/outpatientMedicalRecordDefectNumber' && from.path === '/outpatientControl') {
    //     this.formData = {
    //       dep_id: '',
    //       startTime: '',
    //       endTime: '',
    //       doctor_id: '',
    //     };
    //     // this.formData.startTime = this.storageGet('start_time');
    //     // this.formData.endTime = this.storageGet('end_time');
    //     this.paginationData.currentPage = 1;
    //     this.paginationData.pageSize = 10;
    //     this.funQuery();
    //   }
    // },
  },
  created() {
    this.scrollHelper = useScrollPosition(this, '.dashboard-container');
  },
  methods: {
    storageGet(key) {
      return localStorage.getItem(key) || '';
    },
    onExport() {
      const { dep_id, startTime, endTime, doctor_id } = this.formData;
      const params = {
        dep_id,
        start_time: startTime,
        end_time: endTime,
        doctor_id,
        is_export: 1,
        page: this.paginationData.currentPage,
        page_size: this.paginationData.pageSize,
      };
      outHospitalExport(params)
        .then(res => {
          const content = res.data; // 后台返回二进制数据
          const blob = new Blob([content]);
          const fileName = `存在问题.csv`;
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
        })
        .catch(error => {
          console.error('导出数据失败：', error);
        });
    },
    // 重置
    onReset() {
      this.formData = {
        dep_id: '',
        startTime: moment().startOf('month').format('YYYYMMDD'),
        endTime: moment().format('YYYYMMDD'),
        doctor_id: '',
      };
      this.funQuery();
      this.scrollHelper.scrollToTop();
    },
    // 返回
    toBack() {
      this.$router.history.go(-1);
    },
    // 跳转详情
    funGoto(blbh) {
      this.scrollHelper.saveScrollPos();
      this.$router.push({ path: '/outpatientMedicalRecordDetail', query: { blbh, pageType: 'outpatient'} });
    },
    // 获取部门和医生select
    selectInfo() {
      this.$axios
        .post('/get_omr_department_list')
        .then(res => {
          this.departmentList = res.data || [];
        })
        .catch(error => {
          console.error('获取科室列表失败：', error);
          this.departmentList = [];
        });
      this.$axios
        .post('/omr_zk/docker_list')
        .then(res => {
          this.doctors = res.data || [];
        })
        .catch(error => {
          console.error('获取医生列表失败：', error);
          this.doctors = [];
        });
    },
    // 分页页码变更
    pageHasChanged() {
      this.funQuery();
    },
    // 分页每页条数变更
    handleSizeChange(size) {
      this.paginationData.currentPage = 1;
      this.paginationData.pageSize = size;
      this.funQuery();
    },
    // 核心：查询方法（适配新接口、新参数、新返回数据）
    funQuery() {
      // 构造新接口要求的参数
      const params = {
        start_time: this.formData.startTime || '',
        end_time: this.formData.endTime || '',
        page: this.paginationData.currentPage,
        page_size: this.paginationData.pageSize,
        dep_id: this.formData.dep_id || '',
        doctor_id: this.formData.doctor_id || '',
      };

      this.$axios
        .post('/omr_zk/defect_issues_all', params)
        .then(res => {
          // 校验接口返回有效性（适配新返回格式）
          if (res?.code === 200 && res?.data) {
            this.paginationData.total = res.data.count || 0;
            this.tableData = res.data.list || [];
          } else {
            this.paginationData.total = 0;
            this.tableData = [];
            console.warn('接口返回数据格式异常');
          }
        })
        .catch(error => {
          console.error('获取缺陷列表数据失败：', error);
          this.paginationData.total = 0;
          this.tableData = [];
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