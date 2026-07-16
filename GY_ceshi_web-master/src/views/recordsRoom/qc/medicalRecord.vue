<template>
  <div class="app-container">
    <!-- <div class="title-content">
      <el-image class="zsIcon" :src="require('../../.././assets/images/zsicon.png')" fit="contain"></el-image>
      <div class="title-contentIcon">病历智审结果</div>
    </div> -->
    <!-- 查询表单 -->
    <div class="filter-list-form">
      <el-form ref="form" :model="form" label-width="80px">
        <!-- 院区和科室在同一行 -->
        <el-row class="custom-row">
          <el-col :span="5">
            <el-form-item label="院区">
              <el-select v-model="form.region" placeholder="请选择院区">
                <el-option v-for="campus in campusList" :key="campus.id" :label="campus.name"
                  :value="campus.name"></el-option>
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="5">
            <el-form-item label="科室">
              <el-select v-model="form.department" placeholder="请选择科室">
                <el-option v-for="item of deportments" :key="item.id" :label="item.name" :value="item.name" />
              </el-select>
            </el-form-item>
          </el-col>
        </el-row>

        <!-- 展开部分 -->
        <div v-if="isExpanded">
          <el-row>
            <el-col :span="5">
              <el-form-item label="病区">
                <el-select v-model="form.ward" placeholder="请选择病区">
                  <el-option v-for="item of deportments" :key="item.id" :label="item.name" :value="item.name" />
                </el-select>
              </el-form-item>
            </el-col>
            <el-col :span="5">
              <el-form-item label="管床">
                <el-select v-model="form.bedManager" placeholder="请选择管床">
                  <el-option v-for="pipe in pipeList" :key="pipe.id" :label="pipe.name" :value="pipe.name" />
                </el-select>
              </el-form-item>
            </el-col>
            <el-col :span="5">
              <el-form-item label="床号">
                <el-input v-model="form.CWH" placeholder="请输入床号" />
              </el-form-item>
            </el-col>
          </el-row>
        </div>
      </el-form>

      <!-- 查询按钮和展开/收起按钮 -->
      <el-row style="padding-bottom: 22px;">
        <el-col :span="18" style="text-align: right">
          <el-button type="primary" @click="onSubmit">查询</el-button>
          <el-button type="text" @click="toggleExpand">
            {{ isExpanded ? '收起' : '展开' }}
            <i :class="isExpanded ? 'el-icon-arrow-up' : 'el-icon-arrow-down'" />
          </el-button>
        </el-col>
      </el-row>
    </div>

    <!-- 数据表格 -->
    <el-table class="filter-list-action" :data="queryTableData" tooltip-effect="dark" @change="handleSizeChange" style="width: 100%"
      v-if="!showQueryTable">
      <el-table-column type="index" label="序号" width="50" v-loading="loading" />
      <el-table-column prop="medicalRecordNumber" label="病案号">
        <template #default="{ row }">
          <el-button type="text" @click="openCaseQualityBox2(row)"
            style="height: 32px; border-bottom: 2px solid rgb(64, 158, 255)">
            {{ row.medicalRecordNumber }}
          </el-button>
        </template>
      </el-table-column>
      <el-table-column prop="bedNumber" label="床号" />
      <el-table-column prop="admissionCount" label="住院次数" />
      <el-table-column prop="homePageIssue" label="问题数量">
        <template #default="{ row }">
          <div class="message-badge">
            <!-- 显示病历问题的值 -->
            <span>{{ row.count }}</span>
            <!-- 消息提示标识 -->
            <span v-if="row.counts" class="dot">{{ row.counts }}</span>
          </div>
        </template>
      </el-table-column>
      <el-table-column prop="status" label="状态" width="50" />
    </el-table>

    <!-- 查询表格 -->
    <el-table class="filter-list-action" :data="queryTableData" tooltip-effect="dark" @change="handleSizeChange" style="width: 100%" v-else>
      <el-table-column type="index" label="序号" width="50" />
      <el-table-column prop="medicalRecordNumber" label="病案号">
        <template #default="{ row }">
          <el-button type="text" @click="openCaseQualityBox2(row)"
            style="height: 32px; border-bottom: 2px solid rgb(64, 158, 255)">
            {{ row.medicalRecordNumber }}
          </el-button>
        </template>
      </el-table-column>
      <el-table-column prop="bedNumber" label="床号"/>
      <el-table-column prop="admissionCount" label="住院次数"/>
      <el-table-column prop="homePageIssue" label="问题数量">
        <template #default="{ row }">
          <div class="message-badge">
            <!-- 显示病历问题的值 -->
            <span>{{ row.count }}</span>
            <!-- 消息提示标识 -->
            <span v-if="row.counts" class="dot">{{ row.counts }}</span>
          </div>
        </template>
      </el-table-column>
      <el-table-column prop="status" label="状态"/>
    </el-table>

    <el-dialog :visible.sync="caseShow" custom-class="my-dialog" :modal="false" :show-close="false" width="420">
      <CaseQualityBox v-if="showCaseDetails" :resultsList="results" :MED_REC_ID="selectedMedicalRecordNumber"
        :CWH="selectedBedNumber" :in_hospital="selectedAdmissionCount" :AAA29="selectedAdmissionCount" @close="handleClose"
        :caseShow="caseShow" ref="child" />
    </el-dialog>
  </div>
</template>

<script>
import { getStaffListData, getCampusArea, getPipeBedding, getNumberInfo, getCaseResultData, getCaseQuality } from '@/api/qc';
import CaseQualityBox from './components/CaseQualityBox2';

export default {
  components: {
    CaseQualityBox,
  },
  data() {
    return {
      dialogVisible: false,
      loading: false, // 新增：加载状态
      isExpanded: false, // 控制展开和折叠
      form: {
        region: '', // 院区
        department: '', // 科室
        ward: '', // 病区
        bedManager: '', // 管床
        CWH: '', // 床号
      },
      tableData: [],
      showCaseDetails: false, // 控制 CaseQualityBox2 组件的显示
      selectedMedicalRecordNumber: '', // 选中的病案号
      selectedBedNumber: '', // 选中的床位号
      selectedAdmissionCount: '', // 选中的住院次数
      selectedStatus: '', // 选中的状态
      employeeInformation: [],
      multipleSelection: [],
      tableHeight: 0, // 表格高度
      campusList: [], // 存储院区信息的数组
      pipeList: [], // 存储管床信息的数组
      deportments: [], // 存储科室信息的数组
      currentPage: 1,
      pageSize: 10,
      total: 0,
      queryTableData: [], // 查询表格的数据
      showQueryTable: false, // 是否显示查询表格
      caseShow: false, // 是否显示病案详情
      medicalRecord: '', // 消息内容
      errorV2: '',
    };
  },
  created() {
    this.initData();
    this.loadData({}); // 加载默认数据
    // window.addEventListener('resize', this.handleWindowResize);
    // this.handleWindowResize();
    getCaseResultData().then(res => {
      if (res.code === 200) {
        this.tableData.forEach(row => {
          this.getCaseQualityResults(row);
        });
      }
    });
  },
  mounted() {
    // const observer = new MutationObserver(mutationsList => {
    //   let sidebarFound = false;
    //   let navbarFound = false;

    //   for (const mutation of mutationsList) {
    //     if (mutation.type === 'childList') {
    //       const sidebar = document.querySelector('.sidebar-container');
    //       const navbar = document.querySelector('.navbar');
    //       const appMain = document.querySelector('.app-main');
    //       const breadcrumbContainer = document.querySelector('.breadcrumb-container');

    //       if (sidebar && !sidebarFound) {
    //         sidebar.style.display = 'none';
    //         sidebarFound = true;
    //       }

    //       if (navbar && !navbarFound) {
    //         navbar.style.display = 'none';
    //         navbarFound = true;
    //       }

    //       if (appMain) {
    //         appMain.style.width = '100%';
    //         appMain.style.paddingTop = '0px';
    //         appMain.style.padding = '10px 10%';
    //         appMain.style.background = '#fff';
    //       }

    //       if (breadcrumbContainer) {
    //         breadcrumbContainer.style.display = 'none';
    //       }

    //       if (sidebarFound && navbarFound) {
    //         observer.disconnect(); // 停止监听
    //       }
    //     }
    //   }
    // });
    // observer.observe(document.documentElement, { childList: true, subtree: true });
    this.getCaseQualityResults();
  },
  beforeDestroy() {
    // window.removeEventListener('resize', this.handleWindowResize);
  },
  methods: {
    // 初始化数据
    initData() {
      this.getStaff();
      this.getCampus();
      this.getPipe();
      this.message();
      this.getDeportmentList();
    },
    // 切换展开/折叠状态
    toggleExpand() {
      this.isExpanded = !this.isExpanded;
      this.handleWindowResize();
    },
    // 提交查询表单
    onSubmit() {
      const params = {
        YQ: this.form.region,
        KS: this.form.department,
        BQ: this.form.ward,
        GC: this.form.bedManager,
        CH: this.form.CWH,
        page: this.currentPage,
        pageSize: this.pageSize,
      };
      this.loadData(params); // 重新加载数据
    },
    // 加载数据
    loadData(params) {
      this.loading = true; // 显示加载状态
      getCaseResultData(params)
        .then(res => {
          if (res.code === 200) {
            // 将接口返回的数据映射到表格数据结构
            this.queryTableData = res.data.list.map(item => ({
              medicalRecordNumber: item.MED_REC_ID, // 病案号
              bedNumber: item.CWH, // 床号
              admissionCount: item.AAA29, // 住院次数
              count: item.count, // 问题数量
              counts: item.counts, // 人工质控问题数量
              status: item.in_hospital, // 状态
            }));
            this.total = res.data.count; // 更新总条数
          } else {
            this.$message.error(res.msg || '获取数据失败');
          }
        })
        .catch(error => {
          console.error('查询失败:', error);
          this.$message.error('查询失败，请稍后重试');
        })
        .finally(() => {
          this.loading = false; // 隐藏加载状态
        });
    },

    // 每页条数变化
    handleSizeChange(size) {
      this.pageSize = size;
      this.loadData({ page: 1, pageSize: this.pageSize }); // 直接加载数据，重置为第一页
    },
    // 处理排序
    handleSortChange(column) {
      console.log('排序字段:', column.prop, '排序方式:', column.order);
      // 如果需要根据排序字段重新加载数据，可以在这里调用接口
    },

    // 打开病案详情组件
    openCaseQualityBox2(row) {
      var that = this;
      this.$axios.post('/home_quality/getQualityResult', {
        id: row.medicalRecordNumber
      }).then(res => {
        that.$nextTick(() => {
          that.results = res.data;
          that.$forceUpdate(); // 强制更新组件
        });
      })
      this.selectedMedicalRecordNumber = row.medicalRecordNumber;
      const MEDRECID = this.selectedMedicalRecordNumber;
      localStorage.setItem('MEDRECID', MEDRECID);
      this.selectedBedNumber = row.bedNumber;
      this.selectedAdmissionCount = row.admissionCount;
      this.selectedStatus = row.status;
      this.showCaseDetails = true;
      this.caseShow = true;
      this.$nextTick(() => {
        if (this.$refs.child) {
          this.$refs.child.getData(row.medicalRecordNumber);
          this.$refs.child.qualityBazb(row.medicalRecordNumber);
        }
      });
    },
    // 获取员工信息
    getStaff() {
      getStaffListData().then(res => {
        if (res.code === 200) {
          this.employeeInformation = res.data;
        }
      });
    },
    // 获取院区信息
    getCampus() {
      getCampusArea().then(res => {
        const { data } = res;
        // 直接遍历数组
        data.forEach(campusName => {
          this.campusList.push({
            id: campusName,
            name: campusName
          });
        });
      });
    },
    // 获取管床信息
    getPipe() {
      getPipeBedding().then(res => {
        const { data } = res;
        console.log(data, "data222");
        data.forEach(pipeList => {
          this.pipeList.push({
            id: pipeList,
            name: pipeList
          });
        });
      });
    },
    // 获取科室信息
    getDeportmentList() {
      this.$axios.get('/user/depDropDown').then(res => {
        const { data } = res;
        if (data.length) {
          data.forEach(ele => {
            this.deportments.push({
              id: ele.dep_id,
              name: ele.name,
            });
          });
        }
      });
    },
    // 获取消息信息
    message() {
      getNumberInfo().then(res => {
        console.log(res, 'res消息');
        this.medicalRecord = res.data.medicalRecord;
        this.errorV2 = res.data.errorV2;
      });
    },
    // 处理窗口大小改变事件
    handleWindowResize() {
      const formHeight = this.$el.querySelector('.inputS').offsetHeight;
      this.tableHeight = window.innerHeight - formHeight - 40;
    },
    handleClose() {
      this.caseShow = false; // 关闭弹框
    }
  },
};
</script>

<style scoped lang="scss">
.container {
  // position: fixed;
 // left: 0;
 // top: 0;
 // display: flex;
 // flex-direction: column;
 // height: 100vh;
 // width: 420px;
}

.title-content {
  display: flex;
  align-items: center;
  padding: 12px;
  font-size: 14px;

  .zsIcon {
    margin-right: 10px;
    width: 37px;
    height: 37px;
  }

  .title-contentIcon {
    font-size: 16px;
  }
}

::v-deep .my-dialog {
  position: absolute;
  left: 0;
  top: 0;
  width: 420px !important;
  height: auto;
  margin-top: 0px !important;
}

::v-deep .title-content {
  padding: 0px 0px;
}

::v-deep .el-dialog__body {
  margin-top: -20px;
}

::v-deep .el-col-12 {
  width: 42%;
}

::v-deep .el-col-18 {
  width: 100%;
}

::v-deep .el-tabs {
  margin-top: 20px;
}

::v-deep .el-row {
  // display: flex;
  // margin-top: 30px;
  // margin-left: -37px;
}

::v-deep .el-dialog__body {
  /* padding: 0px 0px; */
  //   margin-top: -40px;
  color: #606266;
  font-size: 14px;
  word-break: break-all;
}

.inputS {
  margin-bottom: 50px;
}

.el-table {
  flex: 1;
}

.dot {
  display: inline-block;
  width: 20px;
  height: 20px;
  line-height: 23px;
  background-color: red;
  border-radius: 50%;
  color: #fff;
  text-align: center;
  margin: -10px 5px;
}

.el-form-item {
  // margin-bottom: 0px;
}

.el-form-item__label {
  color: #000;
}

.el-form-item__content {
  color: #000;
}

.el-form-item__label {
  color: #000;
}

.el-form-item__content {
  color: #000;
}
</style>
