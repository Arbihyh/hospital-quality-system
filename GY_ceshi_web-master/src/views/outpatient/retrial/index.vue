<!-- 复审查询 -->
<template>
  <div class="retrial-container">
    <div class="tableBox">
      <el-form :inline="true" :model="formData" class="demo-form-inline">
        <el-row style="align-items: center">
          <el-col :span="8">
            <el-form-item label="发送时间">
              <el-date-picker
                v-model="formData.start_time"
                type="date"
                :picker-options="pickerOptions"
                placeholder="发送时间-开始"
                value-format="yyyyMMdd"
                format="yyyy年MM月dd日"
              />
              <el-date-picker v-model="formData.end_time" type="date" :picker-options="[]" placeholder="发送时间-结束" value-format="yyyyMMdd" format="yyyy年MM月dd日" />
            </el-form-item>
          </el-col>
          <el-col :span="5">
            <el-form-item label="整改状态">
              <el-select style="width: 100%" v-model="formData.rectifyStatus" class="selects" filterable clearable placeholder="整改状态">
                <el-option label="已整改" value="1" key="1"></el-option>
                <el-option label="未整改" value="0" key="0"></el-option>
              </el-select>
            </el-form-item>
          </el-col>

          <el-col :span="6">
            <el-form-item label="就诊科室">
              <el-select style="width: 100%" v-model="formData.dep_id" class="selects" filterable clearable placeholder="就诊科室">
                <el-option v-for="(item, index) in departmentList" :label="item.name" :value="item.id" :key="index"></el-option>
              </el-select>
            </el-form-item>
          </el-col>
        </el-row>

        <el-row>
          <el-col :span="5">
            <el-form-item label="接诊医师">
              <el-select style="width: 100%" v-model="formData.doctor_id" class="selects" filterable clearable placeholder="接诊医师">
                <el-option v-for="(item, index) in doctors" :label="item.name" :value="item.id" :key="index"></el-option>
              </el-select>
            </el-form-item>
          </el-col>

          <el-col :span="7">
            <el-form-item style="width: 100%" label="门诊号">
              <el-input v-model="formData.mzh" placeholder="门诊号"></el-input>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item>
              <el-button type="primary" @click="funQuery">查询</el-button>
            </el-form-item>
            <el-form-item>
              <el-button @click="onReset">重置条件</el-button>
            </el-form-item>
            <el-form-item style="margin-right: 0; float: right">
              <el-button @click="onExport" type="primary" icon="el-icon-download" style="float: right; margin-bottom: 16px">导出数据</el-button>
            </el-form-item>
          </el-col>
        </el-row>
      </el-form>
      <div>
        <el-table :data="tableData" style="width: 100%">
          <el-table-column type="index" label="序号"></el-table-column>
          <el-table-column prop="status_text" label="整改状态" width="100">
            <template slot-scope="scope">
              <span :style="{ color: scope.row.status_text === '未整改' ? '#67c23a' : '#f56c6c' }">
                {{ scope.row.status_text || '-' }}
              </span>
            </template>
          </el-table-column>
          <el-table-column prop="rule_description" label="缺陷描述"></el-table-column>
          <el-table-column prop="jzsj" label="就诊时间"></el-table-column>
          <el-table-column prop="ks_name" label="就诊科室"></el-table-column>
          <el-table-column prop="ys_name" label="接诊医师"></el-table-column>
          <el-table-column prop="mzh" label="门诊号">
            <template slot-scope="scope">
              <span v-if="scope.row.blbh" class="blue link-text" @click="funGoto(scope.row.blbh)">
                {{ scope.row.mzh }}
              </span>
              <span v-else>
                {{ scope.row.mzh }}
              </span>
            </template>
          </el-table-column>
          <el-table-column prop="xm" label="患者姓名"></el-table-column>
          <el-table-column prop="xb" label="性别"></el-table-column>
          <el-table-column prop="nl" label="年龄"></el-table-column>
          <el-table-column prop="fssj" label="发送时间"></el-table-column>
          <el-table-column prop="fsr" label="发送人"></el-table-column>
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
</template>

<script>
import { mapGetters } from 'vuex';
import mPagination from '@/components/m-pagination';
import moment from 'moment/moment';
import useScrollPosition from '@/hooks/useScrollPosition.js';
import { mzFeedbackListExport } from '@/api/excel';

export default {
  name: 'outpatientMedicalRetrialNumber',
  components: {
    mPagination,
  },
  computed: {
    ...mapGetters(['name']),
  },
  data() {
    const that = this;

    return {
      formData: {
        rectifyStatus: '', // 整改状态
        start_time: moment().startOf('month').format('YYYYMMDD'),
        end_time: moment().format('YYYYMMDD'),
        dep_id: '', // 科室ID
        doctor_id: '', // 医师ID
        mzh: '', // 门诊号
      },
      pickerOptions: {
        disabledDate: time => {
          if (this.formData.end_time != '') {
            return time.getTime() > Date.now();
          }
        },
        shortcuts: [
          {
            text: '今天',
            onClick(picker) {
              picker.$emit('pick', moment().format('YYYYMMDD'));
              that.formData.end_time = moment().format('YYYYMMDD');
            },
          },
          {
            text: '近7天',
            onClick(picker) {
              picker.$emit('pick', moment().subtract(7, 'days').format('YYYYMMDD'));
              that.formData.end_time = moment().format('YYYYMMDD');
            },
          },
          {
            text: '近30天',
            onClick(picker) {
              picker.$emit('pick', moment().subtract(30, 'days').format('YYYYMMDD'));
              that.formData.end_time = moment().format('YYYYMMDD');
            },
          },
          {
            text: '近3年',
            onClick(picker) {
              picker.$emit('pick', moment().subtract(3, 'years').format('YYYYMMDD'));
              that.formData.end_time = moment().format('YYYYMMDD');
            },
          },
          {
            text: '一季度',
            onClick(picker) {
              picker.$emit('pick', moment().startOf('year').format('YYYYMMDD'));
              that.formData.end_time = moment().startOf('year').add(3, 'M').subtract(1, 'days').format('YYYYMMDD');
            },
          },
          {
            text: '二季度',
            onClick(picker) {
              picker.$emit('pick', moment().startOf('year').add(3, 'M').format('YYYYMMDD'));
              that.formData.end_time = moment().startOf('year').add(6, 'M').subtract(1, 'days').format('YYYYMMDD');
            },
          },
          {
            text: '三季度',
            onClick(picker) {
              picker.$emit('pick', moment().startOf('year').add(6, 'M').format('YYYYMMDD'));
              that.formData.end_time = moment().startOf('year').add(9, 'M').subtract(1, 'days').format('YYYYMMDD');
            },
          },
          {
            text: '四季度',
            onClick(picker) {
              picker.$emit('pick', moment().startOf('year').add(9, 'M').format('YYYYMMDD'));
              that.formData.end_time = moment().startOf('year').add(12, 'M').subtract(1, 'days').format('YYYYMMDD');
            },
          },
          {
            text: moment().add(-2, 'Y').format('YYYY'),
            onClick(picker) {
              picker.$emit('pick', moment().add(-2, 'Y').startOf('year').format('YYYYMMDD'));
              that.formData.end_time = moment().add(-2, 'Y').endOf('year').format('YYYYMMDD');
            },
          },
          {
            text: moment().add(-1, 'Y').format('YYYY'),
            onClick(picker) {
              picker.$emit('pick', moment().add(-1, 'Y').startOf('year').format('YYYYMMDD'));
              that.formData.end_time = moment().add(-1, 'Y').endOf('year').format('YYYYMMDD');
            },
          },
          {
            text: moment().format('YYYY'),
            onClick(picker) {
              console.log(that.formData);
              picker.$emit('pick', moment().startOf('year').format('YYYYMMDD'));
              console.log(moment().endOf('year').format('YYYYMMDD'));
              that.formData.end_time = moment().endOf('year').format('YYYYMMDD');
            },
          },
        ],
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
  created() {
    this.scrollHelper = useScrollPosition(this, '.retrial-container');
  },
  activated() {
    this.initPageData();
  },
  mounted() {
    this.initPageData();
  },
  methods: {
    initPageData() {
      this.selectInfo();
      this.funQuery();
    },
    selectInfo() {
      this.$axios
        .post('/get_omr_department_list')
        .then(res => {
          this.departmentList = res.data;
        })
        .catch(err => {
          console.error('获取科室列表失败：', err);
        });
      this.$axios
        .post('/omr_zk/docker_list')
        .then(res => {
          this.doctors = res.data;
        })
        .catch(err => {
          console.error('获取医师列表失败：', err);
        });
    },
    // 查询数据
    funQuery() {
      const params = {
        rectifyStatus: this.formData.rectifyStatus,
        start_time: this.formData.start_time,
        end_time: this.formData.end_time,
        dep_id: this.formData.dep_id,
        doctor_id: this.formData.doctor_id,
        mzh: this.formData.mzh,
        page: this.paginationData.currentPage,
        page_size: this.paginationData.pageSize,
      };

      this.$axios2
        .post('/mz_feedback_list', params)
        .then(res => {
          this.paginationData.total = res.data.total;
          this.tableData = res.data.list;
        })
        .catch(err => {
          console.error('查询数据失败：', err);
        });
    },
    onReset() {
      this.formData = {
        rectifyStatus: '',
        start_time: moment().startOf('month').format('YYYYMMDD'),
        end_time: moment().format('YYYYMMDD'),
        dep_id: '',
        doctor_id: '',
        mzh: '',
      };
      this.paginationData.currentPage = 1;
      this.funQuery();
    },
    onExport() {
      const params = {
        ...this.formData,
        is_export: 1,
        page: this.paginationData.currentPage,
        page_size: this.paginationData.pageSize,
      };

      mzFeedbackListExport(params)
        .then(res => {
          const content = res.data;
          const blob = new Blob([content]);
          const fileName = `复审问题查询_${moment().format('YYYYMMDDHHmmss')}.csv`;

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
        })
        .catch(err => {
          console.error('导出数据失败：', err);
        });
    },
    toBack() {
      this.$router.go(-1);
    },
    funGoto(id) {
      this.scrollHelper.saveScrollPos();

      this.$router.push({ path: '/outpatientMedicalRecordDetail', query: { blbh: id } });
    },
    pageHasChanged() {
      this.funQuery();
    },
    handleSizeChange(size) {
      this.paginationData.currentPage = 1;
      this.paginationData.pageSize = size;
      this.funQuery();
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
.retrial-container {
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