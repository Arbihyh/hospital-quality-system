<template>
  <div class="dashboard-container">
    <div class="tableBox">
      <div class="block">
        <el-form v-if="$route.query.from != 'ZMBLZK_WTSL'" :model="formData" class="demo-form-inline" label-suffix=":" label-width="74px">
          <el-row :gutter="20">
            <el-col :span="6">
              <el-form-item label="出院时间">
                <el-date-picker
                  style="width: 94%"
                  v-model="formData.startTime"
                  type="date"
                  placeholder="出院开始日期"
                  value-format="yyyyMMdd"
                  format="yyyy年MM月dd日"
                ></el-date-picker>
              </el-form-item>
            </el-col>

            <el-col :span="6">
              <el-form-item style="text-align: center" label="至">
                <el-date-picker
                  style="width: 94%"
                  v-model="formData.endTime"
                  type="date"
                  placeholder="出院结束日期"
                  value-format="yyyyMMdd"
                  format="yyyy年MM月dd日"
                ></el-date-picker>
              </el-form-item>
            </el-col>
            <el-col :span="6">
              <el-form-item label="病案号">
                <el-input v-model="formData.AAA28" placeholder="病案号"></el-input>
              </el-form-item>
            </el-col>
            <el-col :span="6" v-if="sort">
              <el-form-item label="医师姓名">
                <el-select v-model="doctor_name" multiple collapse-tags filterable clearable placeholder="全部" style="width: 100%">
                  <el-option v-for="(item, index) of doctorList" :key="index" :label="item" :value="item"></el-option>
                </el-select>
              </el-form-item>
            </el-col>
            <el-col :span="6">
              <el-form-item label="出院科室">
                <el-select v-model="formData.AAC11N" clearable filterable placeholder="全部" style="width: 100%">
                  <el-option v-for="(item, index) in departmentList" :label="item.name" :value="item.name" :key="index"></el-option>
                </el-select>
              </el-form-item>
            </el-col>
            <el-col :span="6" v-if="error_rule">
              <el-form-item label="质控类型">
                <el-select v-model="formData.rule_type" clearable filterable class="selects" placeholder="全部" style="width: 100%">
                  <el-option label="时效性" value="时效性"></el-option>
                  <el-option label="专科质控" value="专科质控"></el-option>
                  <el-option label="内涵质控" value="内涵质控"></el-option>
                  <el-option label="检查报告质控" value="检查报告质控"></el-option>
                  <el-option label="检验报告质控" value="检验报告质控"></el-option>
                  <el-option label="专病质控" value="专病质控"></el-option>
                </el-select>
              </el-form-item>
            </el-col>
            <el-col :span="6" :offset="18">
              <el-form-item style="text-align: right">
                <el-button plain @click="handleReset" icon="el-icon-refresh">重置</el-button>
                <el-button type="primary" @click="onSearch" class="export-btn" icon="el-icon-search">查询</el-button>
              </el-form-item>
            </el-col>
          </el-row>
        </el-form>
        <!--终末病历质控-问题数量-->
        <el-form style="width: 100%" v-else ref="ZmblzkWtslForm" :model="formDataZmblzkWtsl" class="demo-form-inline" label-suffix=":" label-width="74px">
          <el-row :gutter="24">
            <el-col :span="7">
              <el-form-item label="所属院区" prop="YQ_CODE">
                <el-select style="width: 100%" placeholder="请选择所属院区" v-model="formDataZmblzkWtsl.YQ_CODE" multiple collapse-tags clearable filterable @change="yqChange">
                  <el-option v-for="(item, index) in searchOptions.yqArray" :key="index" :label="item.dep_name" :value="item.dep_id"></el-option>
                </el-select>
              </el-form-item>
            </el-col>
            <el-col :span="6">
              <el-form-item :label="isMiddleCaseControl ? '病人科室' : '出院科室'" prop="KS_CODE">
                <el-cascader
                  style="width: 100%"
                  placeholder="请选择科室"
                  v-model="formDataZmblzkWtsl.KS_CODE"
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
                  v-model="formDataZmblzkWtsl.BQ_CODE"
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
                <el-input style="width: 100%" v-model="formDataZmblzkWtsl.AAA28" placeholder="请输入病案号"></el-input>
              </el-form-item>
            </el-col>
          </el-row>
          <el-row :gutter="24">
            <el-col :span="7">
              <el-form-item :label="isMiddleCaseControl ? '入院时间' : '出院日期'">
                <div style="width: 100%; display: flex; gap: 5px">
                  <el-form-item prop="start_time">
                    <el-date-picker
                      style="width: 94%"
                      v-model="formDataZmblzkWtsl.start_time"
                      type="date"
                      placeholder="请选择开始时间"
                      value-format="yyyyMMdd"
                      format="yyyy年MM月dd日"
                    ></el-date-picker>
                  </el-form-item>
                  <el-form-item prop="end_time">
                    <el-date-picker
                      style="width: 100%"
                      v-model="formDataZmblzkWtsl.end_time"
                      type="date"
                      placeholder="请选择结束时间"
                      value-format="yyyyMMdd"
                      format="yyyy年MM月dd日"
                    ></el-date-picker>
                  </el-form-item>
                </div>
              </el-form-item>
            </el-col>
            <el-col :span="6">
              <el-form-item label="病案质量" prop="lb_level">
                <el-select v-model="formDataZmblzkWtsl.lb_level" clearable filterable placeholder="请选择病案质量" style="width: 100%">
                  <el-option v-for="(item, index) in searchOptions.bazlArray" :label="item.name" :value="item.id" :key="index"></el-option>
                </el-select>
              </el-form-item>
            </el-col>
            <el-col :span="6">
              <el-form-item label="离院方式" prop="AEM01C">
                <el-select v-model="formDataZmblzkWtsl.AEM01C" clearable filterable placeholder="请选择离院方式" style="width: 100%">
                  <el-option v-for="(item, index) in searchOptions.lyTypeArray" :label="item.name" :value="item.id" :key="index"></el-option>
                </el-select>
              </el-form-item>
            </el-col>
            <el-col :span="5">
              <el-form-item label="住院天数" prop="endDay1">
                <div style="width: 100%; display: flex; gap: 5px">
                  <el-form-item prop="endDay1">
                    <el-input style="width: 100%" v-model="formDataZmblzkWtsl.endDay1">
                      <template slot="append">天</template>
                    </el-input>
                  </el-form-item>
                  -
                  <el-form-item prop="endDay2">
                    <el-input style="width: 100%" v-model="formDataZmblzkWtsl.endDay2">
                      <template slot="append">天</template>
                    </el-input>
                  </el-form-item>
                </div>
              </el-form-item>
            </el-col>
          </el-row>
          <el-row :gutter="24">
            <el-col :span="7">
              <el-form-item label="问题描述" prop="rule_id">
                <el-select v-model="formDataZmblzkWtsl.rule_id" clearable filterable placeholder="请选择问题描述" style="width: 100%">
                  <el-option v-for="(item, key, index) in searchOptions.wtArray" :label="item" :value="key" :key="index"></el-option>
                </el-select>
              </el-form-item>
            </el-col>
            <el-col :span="6">
              <el-form-item label="规则类型" prop="rule_type">
                <el-select v-model="formDataZmblzkWtsl.rule_type" clearable filterable placeholder="请选择规则类型" style="width: 100%">
                  <el-option v-for="(item, index) in searchOptions.ruleTypeArray" :label="item.name" :value="item.name" :key="index"></el-option>
                </el-select>
              </el-form-item>
            </el-col>
            <el-col :span="6" v-if="pageType === pageTypeM()">
              <el-form-item label-width="80px" label="患者状态" prop="status">
                <el-select v-model="middleCaseControlStatus" placeholder="请选择" style="width: 100%">
                  <el-option v-for="item in patientStatus" :key="item.value" :label="item.label" :value="item.value"></el-option>
                </el-select>
              </el-form-item>
            </el-col>
            <!-- <el-col :span="10" :offset="14">
              <el-form-item style="float: right">
                <el-button type="primary" @click="funQuery()" class="export-btn" icon="el-icon-search">查询</el-button>
                <el-button plain @click="handleResetZmblzkWtsl" icon="el-icon-refresh">重置</el-button>
                <el-button type="primary" @click="exportData()" class="export-btn" icon="el-icon-download">导出数据</el-button>
                <el-button @click="toBack" style="float: right">返回</el-button>
              </el-form-item>
            </el-col> -->
          </el-row>
          <el-row :gutter="20">
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
            <el-col :span="10" :offset="isMiddleCaseControl ? '3' : 14">
              <el-form-item style="float: right">
                <el-button type="primary" @click="funQuery()" class="export-btn" icon="el-icon-search">查询</el-button>
                <el-button plain @click="handleResetZmblzkWtsl" icon="el-icon-refresh">重置</el-button>
                <el-button type="primary" @click="exportData()" class="export-btn" icon="el-icon-download">导出数据</el-button>
                <el-button @click="toBack">返回</el-button>
              </el-form-item>
            </el-col>
          </el-row>
        </el-form>
      </div>
      <el-table :data="tableData" style="width: 100%" v-if="$route.query.from != 'ZMBLZK_WTSL'">
        <el-table-column type="index" label="序号" width="80"></el-table-column>
        <el-table-column prop="AAC11N" label="出院科室" width="200"></el-table-column>
        <el-table-column prop="AAA28" label="病案号" width="160">
          <template slot-scope="scope">
            <span class="blue" @click="funGoto(scope.row.MED_REC_ID, scope.row.rule_id)">
              {{ scope.row.AAA28 }}
            </span>
          </template>
        </el-table-column>
        <el-table-column prop="AAC01" label="出院时间" width="160"></el-table-column>
        <el-table-column prop="AAA01" label="患者姓名" width="160" v-if="error_rule"></el-table-column>
        <el-table-column prop="AEE03" label="主治医师" v-if="!error_rule"></el-table-column>
        <el-table-column prop="notice" label="缺陷问题描述" show-overflow-tooltip></el-table-column>
        <!-- <el-table-column prop="grading_scale" label="评分等级" v-if="sort == 'doc_count'"></el-table-column> -->
        <el-table-column prop="" label="扣分" v-if="sort == 'score'">
          <template slot-scope="scope">
            <span>
              {{ Number(scope.row.score ? 100 - scope.row.score : 0) }}
            </span>
          </template>
        </el-table-column>
      </el-table>
      <!--终末病历质控-问题数量-->
      <el-table :data="tableData" ref="tableRef" style="width: 100%" v-else border @sort-change="handleSortChange" :row-class-name="tableRowClassName">
        <el-table-column type="index" label="序号" width="80"></el-table-column>
        <el-table-column prop="notice" label="问题描述" width="200" show-overflow-tooltip></el-table-column>
        <el-table-column prop="AAA28" label="病案号" width="120">
          <template slot-scope="scope">
            <span class="link" @click="funGoto(scope.row.MED_REC_ID, scope.row.rule_id)">
              {{ scope.row.AAA28 }}
            </span>
          </template>
        </el-table-column>
        <el-table-column prop="AAA01" label="患者姓名" width="120"></el-table-column>
        <el-table-column prop="AAC01" label="出院时间" width="160" sortable></el-table-column>
        <el-table-column prop="AAC11N" :label="isMiddleCaseControl ? '病人科室' : '出院科室'" width="150" show-overflow-tooltip></el-table-column>
        <el-table-column prop="AAC04" label="住院天数" width="80"></el-table-column>
        <el-table-column prop="AEE03" label="主治医师" width="120"></el-table-column>
        <el-table-column prop="AEM01C_MC" label="离院方式" width="150" show-overflow-tooltip></el-table-column>
        <el-table-column prop="AAB01" label="入院时间" width="160" sortable></el-table-column>
        <el-table-column prop="ICD10_NAME" label="主要诊断名称" width="160" show-overflow-tooltip></el-table-column>
        <el-table-column prop="ICD9_NAME" label="主要手术名称" width="160" show-overflow-tooltip></el-table-column>
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
import { qxBlNumberTableList } from '@/api/excel';
import { PageIndexName } from '@/utils/enums-utils';

let isClearStorage = true;
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
        problem: 'all',
        AAC11N: '',
        time: [],
        startTime: '',
        endTime: '',
        recordNum: '',
        rule_type: '',
      },
      middleCaseControlStatus: '1',
      cysj_start: query.cysj_start ? query.cysj_start : '',
      cysj_end: query.cysj_end ? query.cysj_end : '',
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
      doctor_name: '', // 医师姓名
      error_rule: '',
      score: '',
      tableData: [],
      // 分页数据
      paginationData: {
        total: 10,
        currentPage: 1,
        pageSize: 10,
      },
      levelList: [], //问题属性
      departmentList: [],
      doctorList: [], // 医师姓名列表
      sort: '',
      formDataZmblzkWtsl: {
        // 终末病历质控-问题数量
        YQ_CODE: query.YQ_CODE ? query.YQ_CODE.split(',') : [],
        KS_CODE: query.KS_CODE ? query.KS_CODE.split(',') : [],
        BQ_CODE: query.BQ_CODE ? query.BQ_CODE.split(',') : [],
        AAA28: query.AAA28 || '',
        start_time: query.startTime ? query.startTime : this.storageGet('start_time'),
        end_time: query.endTime ? query.endTime : this.storageGet('end_time'),
        lb_level: '',
        AEM01C: '',
        rule_id: query.rule_id || '',
        rule_type: query.rule_type,
        endDay1: '',
        endDay2: '',
        order_value: '',
        order_key: '',
      },
      pageType: query.pageType || 'terminally',

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
    };
  },
  mounted() {
    let query = this.$route.query;
    this.formData.AAA28 = query.AAA28; //病案号
    this.formData.YQ_CODE = query.YQ_CODE; //院区
    this.formData.KS_CODE = query.KS_CODE; //科室
    this.middleCaseControlStatus = query.status; //状态
    this.formData.BQ_CODE = query.BQ_CODE; //病区
    this.error_rule = this.$route.query.rule_id; // 规则ID
    this.sort = this.$route.query.sort;
    this.doctor_name = this.$route.query.doctor_name;
    this.formData.time = [this.storageGet('start_time'), this.storageGet('end_time')];
    this.formData.startTime = query.startTime ? query.startTime : this.storageGet('start_time');
    this.formData.endTime = query.endTime ? query.endTime : this.storageGet('end_time');
    this.selectInfo();
    // 获取医生列表
    this.getDoctorList();
    this.funQuery();
    if (query.from == 'ZMBLZK_WTSL') {
      // 终末病历质控-问题数量
      this.getSearchOptions();
    }
  },
  beforeRouteEnter(to, from, next) {
    isClearStorage = from.path != '/caseViews' ? true : false;
    next();
  },
  activated() {
    if (isClearStorage) {
      this.storageSet('getData', '');
      this.storageSet('getDataRule', '');
    }
    this.funQuery();
  },
  methods: {
    tableRowClassName({ row }) {
      if (row.selected) {
        return 'selected-row';
      }
      return '';
    },
    pageTypeM() {
      return PageIndexName.MEDICAL_RECORD_QUALITY_CONTROL;
    },
    handleSortChange(column) {
      const { prop, order } = column;
      if (order === 'descending') {
        this.formDataZmblzkWtsl.order_value = 'desc';
      } else if (order === 'ascending') {
        this.formDataZmblzkWtsl.order_value = 'asc';
      } else {
        this.formDataZmblzkWtsl.order_value = 'desc';
      }
      this.formDataZmblzkWtsl.order_key = prop;
      this.funQuery();
    },
    // 获取医生列表
    getDoctorList() {
      this.$axios2.post('/case-quality/doctor_list').then(res => {
        this.doctorList = res.data;
      });
    },
    toBack() {
      this.$router.history.go(-1);
    },
    funGoto(ZYH, ruleId) {
      this.storageSet('getData', ZYH);
      this.storageSet('getDataRule', ruleId);
      localStorage.setItem('isControl', true);
      if (this.$route.query.from != 'ZMBLZK_WTSL') {
        this.goto('/caseViews');
      } else {
        this.goto(`/caseViews?pageType=${this.pageType}`);
      }
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
    handleReset() {
      this.formData = {
        problem: 'all',
        AAC11N: '',
        time: '',
        startTime: '',
        endTime: '',
        recordNum: '',
        rule_type: '',
      };
      this.doctor_name = ''; // 医师姓名
    },
    onSearch() {
      this.paginationData.currentPage = 1;
      this.funQuery();
    },
    exportData() {
      let pramse =
        this.$route.query.from == 'ZMBLZK_WTSL'
          ? {
              ...this.formDataZmblzkWtsl,
              YQ_CODE: this.formDataZmblzkWtsl.YQ_CODE.join(','),
              KS_CODE: this.formDataZmblzkWtsl.KS_CODE.join(','),
              BQ_CODE: this.formDataZmblzkWtsl.BQ_CODE.join(','),
              page: this.paginationData.currentPage,
              limit: this.paginationData.pageSize,
            }
          : {
              start_time: this.formData.startTime || '',
              end_time: this.formData.endTime || '',
              level: this.formData.level,
              page: this.paginationData.currentPage,
              limit: this.paginationData.pageSize,
              //AAA28: this.formData.recordNum, //住院号
              AAC11N: this.formData.AAC11N, //科室
              rule_type: this.formData.rule_type, //质控类型
              YQ_CODE: this.formData.YQ_CODE, //质控类型
              KS_CODE: this.formData.KS_CODE, //质控类型
              BQ_CODE: this.formData.BQ_CODE, //质控类型
              AAA28: this.formData.AAA28, //病案号
            };
      if (this.error_rule) {
        pramse.error_rule = this.error_rule; // 规则ID
      }
      if (this.isMiddleCaseControl) {
        pramse.cysj_start = this.cysj_start;
      }
      if (this.isMiddleCaseControl) {
        pramse.cysj_end = this.cysj_end;
      }
      if (this.isMiddleCaseControl) {
        pramse.status = this.middleCaseControlStatus;
      }
      if (this.doctor_name) {
        pramse.doctor_name = this.doctor_name; // 医师姓名
      }
      if (pramse.start_time == 'null' || pramse.end_time == 'null') {
        pramse.start_time = '';
        pramse.end_time = '';
      }
      pramse.is_export = 1;

      qxBlNumberTableList(pramse).then(res => {
        const blob = new Blob([res.data]);
        const fileName = `缺陷病历.csv`;
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
    funQuery() {
      //查询 来源是否终末病历质控-问题数量
      let pramse =
        this.$route.query.from == 'ZMBLZK_WTSL'
          ? {
              ...this.formDataZmblzkWtsl,
              YQ_CODE: this.formDataZmblzkWtsl.YQ_CODE.join(','),
              KS_CODE: this.formDataZmblzkWtsl.KS_CODE.join(','),
              BQ_CODE: this.formDataZmblzkWtsl.BQ_CODE.join(','),
              page: this.paginationData.currentPage,
              limit: this.paginationData.pageSize,
            }
          : {
              start_time: this.formData.startTime || '',
              end_time: this.formData.endTime || '',
              level: this.formData.level,
              page: this.paginationData.currentPage,
              limit: this.paginationData.pageSize,
              //AAA28: this.formData.recordNum, //住院号
              AAC11N: this.formData.AAC11N, //科室
              rule_type: this.formData.rule_type, //质控类型
              YQ_CODE: this.formData.YQ_CODE, //质控类型
              KS_CODE: this.formData.KS_CODE, //质控类型
              BQ_CODE: this.formData.BQ_CODE, //质控类型
              AAA28: this.formData.AAA28, //病案号
            };
      if (this.error_rule) {
        pramse.error_rule = this.error_rule; // 规则ID
      }
      if (this.doctor_name) {
        pramse.doctor_name = this.doctor_name; // 医师姓名
      }
      if (pramse.start_time == 'null' || pramse.end_time == 'null') {
        pramse.start_time = '';
        pramse.end_time = '';
      }
      if (this.isMiddleCaseControl) {
        pramse.cysj_start = this.cysj_start;
      }
      if (this.isMiddleCaseControl) {
        pramse.cysj_end = this.cysj_end;
      }
      if (this.isMiddleCaseControl) {
        pramse.status = this.middleCaseControlStatus;
      }
      pramse.is_export = 0;
      this.$axios.post('CaseHistory/Terminal/qxBlNumberTableList', pramse).then(res => {
        this.paginationData.total = res.data.count;
        this.tableData = res.data.data;
        // 切换选中状态
        let currentRuleId = this.storageGet('getDataRule');
        Array.isArray(this.tableData) &&
          this.tableData.map(item => {
            item.selected = item.rule_id == currentRuleId ? true : false;
          });
      });
    },
    handleResetZmblzkWtsl() {
      this.$refs.ZmblzkWtslForm.resetFields();
      this.formDataZmblzkWtsl.order_value = '';
      this.formDataZmblzkWtsl.order_key = '';
      this.$refs.tableRef.clearSort();
      this.funQuery();
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
