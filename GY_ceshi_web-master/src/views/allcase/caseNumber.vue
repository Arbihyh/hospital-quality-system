<template>
  <div class="dashboard-container">
    <div class="tableBox">
      <div class="block">
        <el-form style="width: 100%" ref="filterListFormRef" :model="formData" class="demo-form-inline" label-suffix=":" label-width="74px">
          <el-row :gutter="24">
            <el-col :span="7">
              <el-form-item label="所属院区" prop="YQ_CODE">
                <el-select style="width: 100%" placeholder="请选择所属院区" v-model="formData.YQ_CODE" multiple collapse-tags clearable filterable @change="yqChange">
                  <el-option v-for="(item, index) in searchOptions.yqArray" :key="index" :label="item.dep_name" :value="item.dep_id"></el-option>
                </el-select>
              </el-form-item>
            </el-col>
            <el-col :span="6">
              <el-form-item :label="isMiddleCaseControl ? '病人科室' : '出院科室'" prop="KS_CODE">
                <el-cascader
                  style="width: 100%"
                  placeholder="请选择科室"
                  v-model="formData.KS_CODE"
                  :options="searchOptions.ksArray"
                  filterable
                  :props="searchOptions.cascaderProps"
                  clearable
                  collapse-tags
                  @change="ksChange"
                ></el-cascader>
              </el-form-item>
            </el-col>
            <el-col :span="6" v-if="!isMiddleCaseControl">
              <el-form-item label="出院病区" prop="BQ_CODE">
                <el-cascader
                  style="width: 100%"
                  placeholder="请选择病区"
                  v-model="formData.BQ_CODE"
                  :options="searchOptions.bqArray"
                  filterable
                  :props="searchOptions.cascaderProps"
                  clearable
                  collapse-tags
                ></el-cascader>
              </el-form-item>
            </el-col>
            <el-col :span="5">
              <el-form-item label="病案号" prop="AAA28">
                <el-input style="width: 100%" v-model="formData.AAA28" placeholder="请输入病案号"></el-input>
              </el-form-item>
            </el-col>
          </el-row>
          <el-row :gutter="24">
            <el-col :span="8">
              <el-form-item :label="isMiddleCaseControl ? '入院时间' : '出院日期'">
                <div style="width: 100%; display: flex; gap: 5px">
                  <el-form-item prop="start_time">
                    <el-date-picker
                      style="width: 100%"
                      v-model="formData.start_time"
                      type="date"
                      placeholder="请选择开始时间"
                      value-format="yyyyMMdd"
                      format="yyyy年MM月dd日"
                    ></el-date-picker>
                  </el-form-item>
                  <el-form-item prop="end_time">
                    <el-date-picker
                      style="width: 100%"
                      v-model="formData.end_time"
                      type="date"
                      placeholder="请选择结束时间"
                      value-format="yyyyMMdd"
                      format="yyyy年MM月dd日"
                    ></el-date-picker>
                  </el-form-item>
                </div>
              </el-form-item>
            </el-col>
            <el-col :span="5">
              <el-form-item label="病案质量" prop="lb_level">
                <el-select v-model="formData.lb_level" clearable filterable placeholder="请选择病案质量" style="width: 100%">
                  <el-option v-for="(item, index) in searchOptions.bazlArray" :label="item.name" :value="item.id" :key="index"></el-option>
                </el-select>
              </el-form-item>
            </el-col>
            <el-col :span="5">
              <el-form-item label="离院方式" prop="AEM01C">
                <el-select v-model="formData.AEM01C" clearable filterable placeholder="请选择离院方式" style="width: 100%">
                  <el-option v-for="(item, index) in searchOptions.lyTypeArray" :label="item.name" :value="item.id" :key="index"></el-option>
                </el-select>
              </el-form-item>
            </el-col>
            <el-col :span="5">
              <el-form-item label="住院天数" prop="endDay1">
                <div style="width: 100%; display: flex; gap: 5px">
                  <el-form-item prop="endDay1">
                    <el-input style="width: 100%" v-model="formData.endDay1">
                      <template slot="append">天</template>
                    </el-input>
                  </el-form-item>
                  -
                  <el-form-item prop="endDay2">
                    <el-input style="width: 100%" v-model="formData.endDay2">
                      <template slot="append">天</template>
                    </el-input>
                  </el-form-item>
                </div>
              </el-form-item>
            </el-col>
          </el-row>
          <el-row :gutter="24">
            <el-col :span="8" v-if="isMiddleCaseControl">
              <el-form-item label="出院时间" prop="cysj_start">
                <div style="width: 100%; display: flex; gap: 5px">
                  <el-form-item prop="cysj_start">
                    <el-date-picker
                      style="width: 100%"
                      v-model="cysj_start"
                      type="date"
                      placeholder="出院开始日期"
                      :picker-options="cysjPickerOptions"
                      value-format="yyyyMMdd"
                      format="yyyy年MM月dd日"
                    ></el-date-picker>
                  </el-form-item>
                  <el-form-item prop="cysj_end">
                    <el-date-picker
                      style="width: 100%"
                      v-model="cysj_end"
                      type="date"
                      placeholder="出院结束日期"
                      :picker-options="[]"
                      value-format="yyyyMMdd"
                      format="yyyy年MM月dd日"
                    ></el-date-picker>
                  </el-form-item>
                </div>
              </el-form-item>
            </el-col>
            <el-col :span="6" v-if="isMiddleCaseControl">
              <el-form-item label-width="80px" label="患者状态" prop="status">
                <el-select v-model="middleCaseControlStatus" placeholder="请选择" style="width: 100%">
                  <el-option v-for="item in patientStatus" :key="item.value" :label="item.label" :value="item.value"></el-option>
                </el-select>
              </el-form-item>
            </el-col>
            <el-col :span="10" :offset="isMiddleCaseControl ? '0' : '14'">
              <el-form-item style="text-align: right">
                <el-button type="primary" @click="handleSearch" class="export-btn" icon="el-icon-search">查询</el-button>
                <el-button plain @click="handleReset" icon="el-icon-refresh">重置</el-button>
                <el-button type="primary" @click="exportData()" icon="el-icon-download" class="export-btn">导出数据</el-button>
                <el-button @click="toBack">返回</el-button>
              </el-form-item>
            </el-col>
          </el-row>
        </el-form>
      </div>
      <!-- <Title :title="'病案列表'" /> -->
      <el-table :data="tableData" style="width: 100%">
        <el-table-column type="index" label="序号" width="80"></el-table-column>
        <el-table-column prop="score_lv" label="病案质量" width="120">
          <template slot-scope="scope">
            <div v-html="scope.row.score_lv" />
          </template>
        </el-table-column>
        <el-table-column prop="AAA28" label="病案号" width="120">
          <template slot-scope="scope">
            <span class="link" @click="funGoto(scope.row.MED_REC_ID)">
              {{ scope.row.AAA28 }}
            </span>
          </template>
        </el-table-column>
        <el-table-column prop="AAA01" label="患者姓名" width="120"></el-table-column>
        <el-table-column prop="AAC01" label="出院时间" width="160"></el-table-column>
        <el-table-column prop="AAC11N" :label="isMiddleCaseControl ? '病人科室' : '出院科室'" width="150" show-overflow-tooltip></el-table-column>
        <el-table-column prop="AAC04" label="住院天数" width="80"></el-table-column>
        <el-table-column prop="AEE03" label="主治医师" width="120"></el-table-column>
        <el-table-column prop="AEM01C_MC" label="离院方式" width="150" show-overflow-tooltip></el-table-column>
        <el-table-column prop="AAB01" label="入院时间" width="160"></el-table-column>
        <el-table-column prop="ICD10_NAME" label="主要诊断名称" show-overflow-tooltip></el-table-column>
        <el-table-column prop="ICD9_NAME" label="主要手术名称" show-overflow-tooltip></el-table-column>
      </el-table>
      <!-- 分页控制 -->
      <mPagination
        v-if="tableData && tableData.length !== 0"
        layout="sizes, prev, pager, next, slot"
        :data="paginationData"
        @sizeChange="handleSizeChange"
        @pageChangeEvent="pageHasChanged"
      ></mPagination>
    </div>
  </div>
</template>

<script>
import Title from '@/components/Title';
import { mapGetters } from 'vuex';
import mPagination from '@/components/m-pagination';
import { blNumberTableList } from '@/api/excel';
export default {
  name: 'Dashboard',
  components: {
    Title,
    mPagination,
  },
  computed: {
    ...mapGetters(['name']),
    isMiddleCaseControl() {
      return this.pageType === 'middleCaseControl';
    },
  },
  data() {
    let query = this.$route.query;
    return {
      formData: {
        // 终末病历质控-问题数量
        is_qx: query.is_qx,
        YQ_CODE: query.YQ_CODE ? (Array.isArray(query.YQ_CODE) ? query.YQ_CODE[1].split(',') : query.YQ_CODE.split(',')) : [],
        KS_CODE: query.KS_CODE ? (Array.isArray(query.KS_CODE) ? query.KS_CODE[1].split(',') : query.KS_CODE.split(',')) : [],
        BQ_CODE: query.BQ_CODE ? (Array.isArray(query.BQ_CODE) ? query.BQ_CODE[1].split(',') : query.BQ_CODE.split(',')) : [],
        AAA28: query.AAA28 || '',
        start_time: query.startTime ? query.startTime : this.storageGet('start_time'),
        end_time: query.endTime ? query.endTime : this.storageGet('end_time'),
        lb_level: '',
        AEM01C: '',
        endDay1: '',
        endDay2: '',
      },
      cysj_start: query.cysj_start ? query.cysj_start : '',
      cysj_end: query.cysj_end ? query.cysj_end : '',
      middleCaseControlStatus: query.status ? query.status : '1',
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
        cascaderProps: {
          multiple: true, // 开启多选模式
          label: 'dep_name',
          value: 'dep_id',
          children: 'children',
          checkStrictly: true, // 允许独立选择任意层级
          emitPath: false, // 是否返回完整路径（true 返回路径数组，false 只返回末节点值）
        },
      },
      pageType: query.pageType || 'terminally',
      tableData: [],
      // 分页数据
      paginationData: {
        total: 10,
        currentPage: 1,
        pageSize: 10,
      },
      departmentList: [],
    };
  },
  mounted() {
    this.getSearchOptions();
    this.funQuery();
  },
  methods: {
    toBack() {
      this.$router.history.go(-1);
    },
    funGoto(val) {
      this.storageSet('getData', val);
      this.storageSet('getDataRule', '');
      localStorage.setItem('isControl', true);
      this.goto(`/caseViews?pageType=${this.pageType}`);
    },
    pageHasChanged() {
      this.funQuery();
    },
    handleSizeChange(size) {
      this.paginationData.currentPage = 1;
      this.paginationData.pageSize = size;
      this.funQuery();
    },
    handleSearch() {
      this.paginationData.currentPage = 1;
      this.funQuery();
    },
    exportData() {
      const params = {
        ...this.formData,
        start_time: this.formData.start_time,
        end_time: this.formData.end_time,
        is_export: 1,
        YQ_CODE: this.formData.YQ_CODE.join(','),
        KS_CODE: this.formData.KS_CODE.join(','),
        BQ_CODE: this.formData.BQ_CODE.join(','),
        page: this.paginationData.currentPage,
        limit: this.paginationData.pageSize,
      };

      if (this.formData.start_time === 'null' || this.formData.start_time === 'undefined' || this.formData.start_time === null || this.formData.start_time === undefined) {
        params.start_time = '';
      }
      if (this.formData.end_time === 'null' || this.formData.end_time === 'undefined' || this.formData.end_time === null || this.formData.end_time === undefined) {
        params.end_time = '';
      }

      if (this.isMiddleCaseControl) {
        params.cysj_start = this.cysj_start;
      }
      if (this.isMiddleCaseControl) {
        params.cysj_end = this.cysj_end;
      }
      if (this.isMiddleCaseControl) {
        params.status = this.middleCaseControlStatus;
      }
      blNumberTableList(params).then(res => {
        const content = res.data;
        const blob = new Blob([content]);
        const fileName = `质控病历.csv`;
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
    funQuery() {
      console.log('this.formData', this.formData);
      const params = {
        ...this.formData,
        is_export: 0,
        start_time: this.formData.start_time,
        end_time: this.formData.end_time,
        YQ_CODE: this.formData.YQ_CODE.join(','),
        KS_CODE: this.formData.KS_CODE.join(','),
        BQ_CODE: this.formData.BQ_CODE.join(','),
        page: this.paginationData.currentPage,
        limit: this.paginationData.pageSize,
      };
      console.log('funQuery', params);
      if (this.formData.start_time === 'null' || this.formData.start_time === 'undefined' || this.formData.start_time === null || this.formData.start_time === undefined) {
        params.start_time = '';
      }
      if (this.formData.end_time === 'null' || this.formData.end_time === 'undefined' || this.formData.end_time === null || this.formData.end_time === undefined) {
        params.end_time = '';
      }

      if (this.isMiddleCaseControl) {
        params.cysj_start = this.cysj_start;
      }
      if (this.isMiddleCaseControl) {
        params.cysj_end = this.cysj_end;
      }
      if (this.isMiddleCaseControl) {
        params.status = this.middleCaseControlStatus;
      }
      //查询
      this.$axios.post('CaseHistory/Terminal/blNumberTableList', params).then(res => {
        this.paginationData.total = res.data.count;
        this.tableData = res.data.data;
      });
    },
    handleReset() {
      this.$refs.filterListFormRef.resetFields();
    },
    getSearchOptions() {
      this.$axios.post('CaseHistory/Terminal/getQxBlSearchOptions', {}).then(res => {
        this.searchOptions.yqArray = res.data.yqArray; //院区
        this.searchOptions.ksArray = this.cancelChildren(res.data.ksArray); //科室
        this.searchOptions.bqArray = this.cancelChildren(res.data.bqArray); //病区
        this.searchOptions.bazlArray = res.data.bazlArray;
        this.searchOptions.lyTypeArray = res.data.lyTypeArray;
        this.searchOptions.wtArray = res.data.wtArray;
        this.searchOptions.ruleTypeArray = res.data.ruleTypeArray;
      });
    },
    //  将下拉框为空的children属性设置为undefined
    cancelChildren(arr) {
      if (!arr) {
        return [];
      }
      // return arr.map(item => {
      //   if (item.children.length == 0) {
      //     item.children = undefined;
      //   } else {
      //     item.children.map(childreItem => {
      //       if (childreItem.children.length == 0) {
      //         childreItem.children = undefined;
      //       }
      //       return childreItem
      //     })
      //   }
      //   return item
      // })
      return arr;
    },
    //院区change事件
    yqChange() {
      this.formData.KS_CODE = [];
      this.formData.BQ_CODE = [];
      this.$axios.post('CaseHistory/Terminal/getKsOptions', { YQ_CODE: this.formData.YQ_CODE }).then(res => {
        this.searchOptions.ksArray = this.cancelChildren(res.data.ksArray); //科室
        this.searchOptions.bqArray = this.cancelChildren(res.data.bqArray); //病区
      });
    },
    //科室change事件
    ksChange() {
      this.formData.BQ_CODE = [];
      this.$axios.post('CaseHistory/Terminal/getBqOptions', { KS_CODE: this.formData.KS_CODE }).then(res => {
        this.searchOptions.bqArray = this.cancelChildren(res.data.bqArray); //病区
      });
    },
  },
};
</script>
<style lang="scss" scoped>
.tableBox {
  background: #fff;
  padding: 19px;
  border-radius: 5px;
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
}
</style>
