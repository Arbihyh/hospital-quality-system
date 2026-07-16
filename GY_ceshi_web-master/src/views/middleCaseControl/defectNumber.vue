<template>
  <div class="dashboard-container">
    <div class="tableBox">
      <div class="block">
        <!-- <div class="blockCon">
          <div class="selectDns"></div>
          <el-input v-model="formData.recordNum" placeholder="病案号"></el-input>
          <span class="kong"></span>
          <el-select v-model="formData.AAC11N" clearable filterable class="selects" placeholder="入院科室">
            <el-option v-for="(item, index) in departmentList" :label="item.name" :value="item.name" :key="index"></el-option>
          </el-select>
          <span class="kong"></span>
          <el-select v-model="formData.in_hospital" clearable filterable class="selects" placeholder="是否在院">
            <el-option label="在院" value="1"></el-option>
            <el-option label="出院" value="2"></el-option>
          </el-select>
          <span class="kong"></span>
          <el-date-picker v-model="formData.startTime" class="selects" type="date" format="yyyy 年 MM 月 dd 日" value-format="yyyyMMdd" placeholder="开始日期"></el-date-picker>
          <el-date-picker
            v-model="formData.endTime"
            type="date"
            class="selects"
            style="margin-left: 10px"
            format="yyyy 年 MM 月 dd 日"
            value-format="yyyyMMdd"
            placeholder="结束日期"
          ></el-date-picker>
          <span class="kong"></span>
          <el-button type="primary" @click="funQuery">查询</el-button>
        </div> -->
        <!-- <el-button @click="toBack" style="position: absolute; right: 35px;">返回</el-button> -->

        <el-form :model="formData" ref="filterFormRef">
          <el-row>
            <el-col :span="7">
              <el-form-item label-width="80px" label="患者状态" prop="status">
                <el-select v-model="formData.status" placeholder="请选择患者状态" style="width: 380px; margin-left: 20px">
                  <el-option style="width: 100%" v-for="item in patientStatus" :key="item.value" :label="item.label" :value="item.value"></el-option>
                </el-select>
              </el-form-item>
            </el-col>

            <el-col :span="7">
              <el-form-item label-width="80px" label="病案号" prop="bah">
                <el-input style="width: 94%" placeholder="请输入病案编号" v-model="formData.bah" clearable></el-input>
              </el-form-item>
            </el-col>

            <el-col :span="7">
              <el-form-item label-width="80px" label="病人科室" prop="KS_CODE">
                <el-cascader
                  style="width: 94%"
                  placeholder="请选择科室"
                  v-model="formData.KS_CODE"
                  :options="ksArray"
                  filterable
                  :props="cascaderProps"
                  clearable
                  collapse-tags
                  @change="ksChange"
                ></el-cascader>
              </el-form-item>
            </el-col>

            <!-- <el-col :span="9" >
                <el-form-item>
                  <div style="margin-left: 20px; width: 94%; display: flex; justify-content:space-between">
                    <div>
                      <el-button class="btn1" type="primary" @click="funQuery">查询</el-button>
                      <el-button @click="reset">重置</el-button>
                      <el-button type="primary" icon="el-icon-download" class="export-btn">导出数据</el-button>
                    </div>
                    <el-button @click="toBack" style="float: right;">返回</el-button>
                  </div>
                </el-form-item>
              </el-col> -->
          </el-row>
          <el-row>
            <el-col :span="7">
              <el-form-item label-width="100px" label="缺陷问题描述" prop="rule_id">
                <el-select v-model="formData.rule_id" clearable filterable placeholder="请选择问题描述" style="width: 100%">
                  <el-option v-for="(item, key, index) in searchOptions.wtArray" :label="item" :value="key" :key="index"></el-option>
                </el-select>
              </el-form-item>
            </el-col>

            <el-col :span="17">
              <el-form-item>
                <div style="margin-left: 660px; width: 94%; display: flex; justify-content: space-between">
                  <div>
                    <el-button class="btn1" type="primary" @click="funQuery">查询</el-button>
                    <el-button @click="reset">重置</el-button>
                    <el-button type="primary" icon="el-icon-download" class="export-btn" @click="onExport">导出数据</el-button>
                  </div>
                  <el-button @click="toBack" style="float: right; margin-right: 600px">返回</el-button>
                </div>
              </el-form-item>
            </el-col>
          </el-row>
        </el-form>
      </div>
      <el-table :data="tableData" align="center" header-align="center" style="width: 100%" @sort-change="handleSortChange">
        <el-table-column type="index" label="序号"></el-table-column>
        <el-table-column prop="rule_notice" label="缺陷问题描述" width="180px" sortable></el-table-column>
        <el-table-column prop="AAC11N" label="病人科室" sortable></el-table-column>
        <el-table-column prop="AAA28" label="病案号" sortable>
          <template slot-scope="scope">
            <span class="blue" @click="funGoto(scope.row.MED_REC_ID)">
              {{ scope.row.AAA28 }}
            </span>
          </template>
        </el-table-column>
        <el-table-column prop="score" label="病历评分" sortable>
          <template slot-scope="scope">
            <span
              :style="{
                color: scope.row.score_lv === '甲' ? 'green' : scope.row.score_lv === '乙' ? 'orange' : 'red',
              }"
            >
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
        <!-- <el-table-column prop="ABC01N" label="主要诊断"></el-table-column>
        <el-table-column prop="ICD9_NAME" label="主要手术"></el-table-column> -->
      </el-table>
      <!-- 分页控制 -->
      <mPagination v-if="tableData && tableData.length !== 0" :data="paginationData" @pageChangeEvent="pageHasChanged"></mPagination>
    </div>
  </div>
</template>

<script>
import { errorDataListExport } from '@/api/excel';
import Title from '@/components/Title';
import { mapGetters } from 'vuex';
import mPagination from '@/components/m-pagination';

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
      searchOptions: {
        yqArray: [], //院区options
        ksArray: [], //科室options
        bqArray: [], //病区options
        bazlArray: [], //病案质量
        lyTypeArray: [], //离院方式
        wtArray: [], // 问题描述
        ruleTypeArray: [], // 规则类型
      },
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
        rule_id: this.$route.query.rule_id || '',
        status: '',
        bah: '',
        KS_CODE: [],
        in_hospital: '1',
        problem: 'all',
        AAC11N: '',
        startTime: '',
        endTime: '',
        recordNum: '',
      },
      error_rule: '',
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
  mounted() {
    this.getSearchOptions();
  },
  activated() {
    // this.formData.startTime = this.$route.query.startTime;
    // this.formData.endTime = this.$route.query.endTime;
    // this.formData.rule_id = this.storageGet('rule_id');
    this.formData.endTime = this.storageGet('end_time');
    this.formData.startTime = this.storageGet('start_time');
    this.formData.endTime = this.storageGet('end_time');
    this.formData.status = this.storageGet('caseControlStatus');
    this.formData.bah = this.storageGet('caseControlBah');
    this.formData.KS_CODE = this.storageGet('caseControlKsCode');
    this.selectInfo();
    this.funQuery();
    this.getSearchOptions();
    console.log('this.formData', this.formData);
  },

  methods: {
    onExport() {
      let pramse = {
        rule_id: this.formData.rule_id,
        status: this.formData.status,
        bah: this.formData.bah,
        KS_CODE: this.formData.KS_CODE,
        start_time: this.formData.startTime || '',
        end_time: this.formData.endTime || '',
        level: this.formData.level,
        page: this.paginationData.currentPage,
        limit: this.paginationData.pageSize,
        AAA28: this.formData.recordNum,
        AAC11N: this.formData.AAC11N,
        in_hospital: this.formData.in_hospital,
        order_value: this.formData.order_value,
        order_key: this.formData.order_key,
        is_export: 1,
      };
      errorDataListExport(pramse).then(res => {
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
      this.$axios.post('CaseHistory/Terminal/getQxBlSearchOptions', {}).then(res => {
        this.ksArray = res.data.ksArray;
        this.searchOptions.wtArray = res.data.wtArray;
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
      this.formData.order_key = prop;
      this.funQuery();
    },
    toBack() {
      this.$router.history.go(-1);
    },
    funGoto(val) {
      this.storageSet('getData', val);
      this.goto('/caseViews?type_v=v2&topBtn=top');
    },
    selectInfo() {
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
      let pramse = {
        rule_id: this.formData.rule_id,
        status: this.formData.status,
        bah: this.formData.bah,
        KS_CODE: this.formData.KS_CODE,
        start_time: this.formData.startTime || '',
        end_time: this.formData.endTime || '',
        level: this.formData.level,
        page: this.paginationData.currentPage,
        limit: this.paginationData.pageSize,
        AAA28: this.formData.recordNum,
        AAC11N: this.formData.AAC11N,
        in_hospital: this.formData.in_hospital,
        order_value: this.formData.order_value,
        order_key: this.formData.order_key,
      };
      // if (this.error_rule) {
      //   pramse.rule_id = this.error_rule;
      // }
      this.$axios2.post('/errorDataList', pramse).then(res => {
        this.paginationData.total = res.data.count;
        this.tableData = res.data.list;
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

.blue {
  color: #185da6;
  cursor: pointer;
}

// .block {
//   background: #fff;
//   align-items: center;
//   border-radius: 5px;
//   height: 100px;
//   margin-bottom: 20px;
//   padding: 0;
//   display: flex;
//   box-sizing: border-box;
//   .blockCon {
//     display: flex;
//     align-items: center;
//     .selectDns {
//       span {
//         margin-right: 5px;
//       }
//     }
//     .demonstration {
//       margin-left: 10px;
//     }
//     .pickers {
//       margin-left: 5px;
//     }
//     .lsxd {
//       margin-left: 20px;
//     }
//     .ins {
//       width: 150px;
//       margin: 0 10px;
//     }
//   }
//   .sc {
//     background: #185da6;
//     color: #fff;
//   }
// }
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
  padding: 0 10px;
}
</style>