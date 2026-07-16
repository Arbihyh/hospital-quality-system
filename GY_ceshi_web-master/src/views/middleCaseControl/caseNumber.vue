<template>
  <div class="dashboard-container">
    <div class="tableBox">
      <div class="block">
        <!-- <div class="blockCon">
          <div class="selectDns"></div>
          <el-input v-model="formData.recordNum" class="width150" placeholder="病案号"></el-input>
          <span class="kong"></span>
          <el-select v-model="formData.problem" filterable placeholder="请选择">
            <el-option v-for="(item, index) in departmentList" :label="item.name" :value="item.id" :key="index"></el-option>
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
          <el-button type="primary" icon="el-icon-download" class="export-btn">导出数据</el-button>
          <el-button @click="toBack" style="float: right;">返回</el-button>
        </div> -->

        <el-form :model="formData" ref="filterFormRef">
          <el-row>
            
            <el-col :span="5">
              <el-form-item label-width="80px" label="患者状态" prop="status">
                <el-select v-model="formData.status" placeholder="请选择">
                  <el-option v-for="item in patientStatus" :key="item.value" :label="item.label"
                    :value="item.value"></el-option>
                </el-select>
              </el-form-item>
            </el-col>

            <el-col :span="5">
              <el-form-item label-width="80px" label="病案号" style="margin-left: -30px" prop="bah">
                <el-input style="width: 94%" placeholder="请输入病案编号" v-model="formData.bah" clearable></el-input>
              </el-form-item>
            </el-col>
            <el-col :span="5">
              <el-form-item label-width="80px" label="病人科室" prop="KS_CODE">
                <el-cascader style="width: 94%" placeholder="请选择科室" v-model="formData.KS_CODE" :options="ksArray"
                  filterable :props="cascaderProps" clearable collapse-tags @change="ksChange"></el-cascader>
              </el-form-item>
            </el-col>

          </el-row>
          <el-row >
            <el-col :span="14">
              <el-form-item label-width="80px" label="入院时间" prop="startTime">
                <el-date-picker v-model="formData.startTime" type="date" format="yyyy 年 MM 月 dd 日"
                  value-format="yyyyMMdd" placeholder="开始日期"></el-date-picker>

                <el-date-picker v-model="formData.endTime" type="date" style="margin-left: 10px"
                  format="yyyy 年 MM 月 dd 日" value-format="yyyyMMdd" placeholder="结束日期"></el-date-picker>
              </el-form-item>
            </el-col>
            <el-col :span="10" >
              <el-form-item>
                <div style="margin-left: 200px; width: 60%; display: flex; justify-content: space-between">
                  <div>
                    <el-button class="btn1" type="primary" @click="funQuery">查询</el-button>
                    <el-button @click="reset">重置</el-button>
                    <el-button type="primary" icon="el-icon-download" class="export-btn"
                      @click="onExport">导出数据</el-button>
                  </div>
                  <el-button @click="toBack" style="float: right">返回</el-button>
                </div>
              </el-form-item>
            </el-col>
          </el-row>
        </el-form>
      </div>
      <!-- <Title :title="'病案列表'" /> -->
      <el-table :data="tableData" align="center" header-align="center" style="width: 100%"
        :default-sort="{ prop: 'AAC11N', order: 'descending' }" @sort-change="handleSortChange">
        <!-- <el-table-column type="index" label="序号"></el-table-column>
        <el-table-column prop="AAC11N" label="出院科室"></el-table-column>
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
<el-table-column prop="AAC01" label="出院时间"></el-table-column>
<el-table-column prop="AAA01" label="患者姓名"></el-table-column>
<el-table-column prop="ABC01N" label="主要诊断"></el-table-column>
<el-table-column prop="ICD9_NAME" label="主要手术"></el-table-column> -->
        <el-table-column type="index" label="序号"></el-table-column>
        <el-table-column prop="AAC11N" label="病人科室" sortable></el-table-column>
        <el-table-column prop="AAA28" label="病案号" sortable>
          <template slot-scope="scope">
            <span class="blue" style="cursor: pointer" @click="funGoto(scope.row.MED_REC_ID)">
              {{ scope.row.AAA28 }}
            </span>
          </template>
        </el-table-column>
        <el-table-column prop="score" label="病历评分" sortable>
          <template slot-scope="scope">
            <span :style="{
              color: scope.row.score_lv === '甲' ? 'green' : scope.row.score_lv === '乙' ? 'orange' : 'red',
            }">
              {{ scope.row.score }} | {{ scope.row.score_lv }}
            </span>
          </template>
        </el-table-column>
        <el-table-column prop="AAA01" label="患者姓名" sortable></el-table-column>
        <el-table-column prop="AAB01" label="入院时间" width="180px" sortable></el-table-column>
        <el-table-column prop="CH" label="床位号" sortable></el-table-column>
        <el-table-column prop="AAC01" label="出院时间" width="180px" sortable></el-table-column>
        <el-table-column prop="GCYSMC" label="管床医生" sortable></el-table-column>
        <el-table-column prop="ZZYSMC" label="主治医师" sortable></el-table-column>
        <el-table-column prop="ZLZZMC" label="诊疗组长" sortable></el-table-column>
        <el-table-column prop="ZRYS_MC" label="科主任" sortable></el-table-column>
      </el-table>
      <!-- 分页控制 -->
      <mPagination v-if="tableData && tableData.length !== 0" layout="sizes, prev, pager, next, slot"
        :data="paginationData" @sizeChange="handleSizeChange" @pageChangeEvent="pageHasChanged"></mPagination>
    </div>
  </div>
</template>

<script>
import Title from '@/components/Title';
import { mapGetters } from 'vuex';
import mPagination from '@/components/m-pagination';
import { exportFile } from '@/utils/export-file';
import { errorDataListExport } from '@/api/excel';

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
      patientStatus: [
        {
          value: '1',
          label: '全部（在院 + 当天出院）',
        },
        {
          value: '2',
          label: '在院',
        },
        {
          value: '3',
          label: '当天出院',
        },
        {
          value: '4',
          label: '常规出院（非当日）',
        },
      ],
      cascaderProps: {
        multiple: true, // 开启多选模式
        label: 'dep_name',
        value: 'dep_id',
        children: 'children',
        checkStrictly: true, // 允许独立选择任意层级
        emitPath: false, // 是否返回完整路径（true 返回路径数组，false 只返回末节点值）
      },
      ksArray: [],
      formData: {
        status: '',
        bah: '',
        KS_CODE: [],
        // rangeDate: [],
        recordNum: '',
        startTime: '',
        endTime: '',
        problem: 'all',
      },
      tableData: [],
      tableData_1: {
        AAC11N: '内科',
        AAA28: '001',
        MED_REC_ID: 'sl_001',
        AAC01: '-',
        AAA01: '张三',
        ABC01N: '肝脾失调',
        ICD9_NAME: '无',
      },
      // 分页数据
      paginationData: {
        total: 10,
        currentPage: 1,
        pageSize: 10,
      },
      departmentList: [],
    };
  },

  activated() {
    this.formData.is_defect = this.$route.query.is_defect;
    this.formData.startTime = this.storageGet('start_time');
    this.formData.endTime = this.storageGet('end_time');
    this.formData.status = this.storageGet('caseControlStatus');
    this.formData.bah = this.storageGet('caseControlBah');
    this.formData.KS_CODE = this.storageGet('caseControlKsCode');
    this.funQuery();
    this.selectInfo();
    this.getSearchOptions();
  },

  mounted() {
    this.formData.is_defect = this.$route.query.is_defect;
    this.formData.startTime = this.storageGet('start_time');
    this.formData.endTime = this.storageGet('end_time');
    this.formData.status = this.storageGet('caseControlStatus');
    this.formData.bah = this.storageGet('caseControlBah');
    this.formData.KS_CODE = this.storageGet('caseControlKsCode');
    this.funQuery();
    this.selectInfo();
    this.getSearchOptions();
  },
  methods: {

    onExport() {
      let params = {
        // AAC01: this.formData.rangeDate, //出院时间
        status: this.formData.status,
        AAA28: this.formData.bah,
        dep_id: this.formData.KS_CODE,
        is_defect: this.formData.is_defect,
        order_value: this.formData.order_value,
        order_key: this.formData.order_key,
        is_export: 1,
        // AAC01_start_date: this.formData.startTime || '',
        // AAC01_end_date: this.formData.endTime || '',
        start_time: this.formData.startTime, //开始时间
        end_time: this.formData.endTime, //结束时间
        // AAA28: this.formData.recordNum,
        page: this.paginationData.currentPage, //页码
        limit: this.paginationData.pageSize, //条数
      };
      errorDataListExport(params).then(res => {
        const content = res.data; // 后台返回二进制数据
        const blob = new Blob([content]);
        const fileName = `病历数据.csv`;
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
      });
    },

    handleSortChange(column) {
      const { prop, order } = column;
      if (order === 'descending') {
        this.formData.order_value = 'desc';
      } else if (order === 'ascending') {
        this.formData.order_value = 'asc';
      } else {
        this.formData.order_value = 'desc';
      }
      this.formData.order_key = prop
      this.funQuery();
    },
    reset() {
      this.formData.status = '1';
      this.formData.bah = '';
      this.formData.KS_CODE = [];
      this.storageSet('caseControlStatus', '1');
      this.storageSet('caseControlBah', '');
      this.storageSet('caseControlKsCode', []);
      this.storageSet('homeFrom', '');
    },
    getSearchOptions() {
      this.$axios.post('CaseHistory/Terminal/getSearchOptions', {}).then(res => {
        this.ksArray = res.data.ksArray;
      });
    },
    toBack() {
      this.$router.history.go(-1);
    },
    funGoto(val) {
      this.storageSet('getData', val);
      this.goto('/caseViews?topBtn=top');
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
    handleSizeChange(size) {
      this.paginationData.currentPage = 1;
      this.paginationData.pageSize = size;
      this.funQuery();
    },
    selectInfo() {
      this.$axios.post('/selectInfo').then(res => {
        this.departmentList = res.data.department;
      });
    },
    funQuery() {
      //查询
      let pramse = {
        // AAC01: this.formData.rangeDate, //出院时间
        status: this.formData.status,
        AAA28: this.formData.bah,
        dep_id: this.formData.KS_CODE,
        is_defect: this.formData.is_defect,
        order_value: this.formData.order_value,
        order_key: this.formData.order_key,
        // AAC01_start_date: this.formData.startTime || '',
        // AAC01_end_date: this.formData.endTime || '',
        start_time: this.formData.startTime, //开始时间
        end_time: this.formData.endTime, //结束时间
        // AAA28: this.formData.recordNum,
        page: this.paginationData.currentPage, //页码
        limit: this.paginationData.pageSize, //条数
      };
      this.$axios2
        .post('/errorDataList', pramse)
        .then(res => {
          console.log(res);
          this.paginationData.total = res.data.count;
          this.tableData = res.data.list;
          // this.tableData.unshift(this.tableData_1)
        })
        .catch(e => {
          // this.tableData.unshift(this.tableData_1)
        });
    },
    reset() {
      // 重置数据
      this.paginationData.currentPage = 1; //页码
      this.paginationData.pageSize = 10; //条数
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

.width130 {
  width: 120px;
}

.blue {
  color: #185da6;
}

.block {
  background: #fff;
  width: 100%;
  align-items: center;
  border-radius: 5px;
  height: 75px;
  padding-left: 10px;
  margin-bottom: 20px;
  padding-left: 0;
  padding-right: 0;

  .blockCon {
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
  padding: 0 5px;
}
</style>