<!-- 运行病历报表 -->
<template>
  <div class="report-container">
    <div class="section section--first" v-if="showIndexCoreReport">
      <el-form :model="searchForm" label-width="100px" ref="searchFormRef">
        <el-row style="align-items: center">
          <el-col :span="6">
            <el-form-item label="规则名称" prop="rule_id">
              <el-select
                v-model="searchForm.rule_id"
                clearable
                filterable
                multiple
                collapse-tags
                placeholder="规则名称"
                style="width: 100%"
              >
                <el-option
                  v-for="(item, key, index) in searchOptions.wtArray"
                  :label="item"
                  :value="key"
                  :key="index"
                ></el-option>
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="6">
            <el-form-item label="住院医师" prop="resident_doctor">
              <el-input v-model="searchForm.resident_doctor" placeholder="医师名称"></el-input>
            </el-form-item>
          </el-col>

          <el-col :span="6">
            <el-form-item label="所属科室" prop="department">
              <el-select
                style="width: 100%"
                v-model="searchForm.department"
                filterable
                clearable
                multiple
                collapse-tags
                placeholder="请选择"
              >
                <el-option
                  v-for="item of departmentList"
                  :key="`${Date.now()}${Math.random()}${item.name}`"
                  :label="item.name"
                  :value="item.dep_id"
                />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="6">
            <el-form-item label="是否出院" prop="is_discharge">
              <el-select
                style="width: 100%"
                v-model="searchForm.is_discharge"
                filterable
                clearable
                placeholder="请选择"
              >
                <el-option
                  v-for="item of isDischargeOptions"
                  :key="`${Date.now()}${Math.random()}${item.id}`"
                  :label="item.name"
                  :value="item.id"
                />
              </el-select>
            </el-form-item>
          </el-col>
        </el-row>
        <el-row>
          <el-col :span="6">
            <el-form-item label="病案号" prop="AAA28">
              <el-input v-model="searchForm.AAA28" placeholder="病案号"></el-input>
            </el-form-item>
          </el-col>

          <el-col :span="6">
            <el-form-item label="问题级别" prop="rule_type">
              <el-select
                style="width: 100%"
                v-model="searchForm.rule_type"
                clearable
                filterable
                placeholder="请选择"
              >
                <el-option
                  v-for="(item, index) in ruleTypeOptions"
                  :key="index"
                  :label="item.name"
                  :value="item.id"
                ></el-option>
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="6">
            <el-form-item label="文书类型" prop="document_type">
              <el-select
                style="width: 100%"
                v-model="searchForm.document_type"
                clearable
                filterable
                collapse-tags
                multiple
                placeholder="请选择"
              >
                <el-option
                  v-for="(item, index) in documentTypeOptions"
                  :key="index"
                  :label="item"
                  :value="item"
                ></el-option>
              </el-select>
            </el-form-item>
          </el-col>

          <el-col :span="6">
            <el-form-item label="整改状态" prop="correction_status">
              <el-select
                style="width: 100%"
                v-model="searchForm.correction_status"
                clearable
                filterable
                placeholder="请选择"
              >
                <el-option
                  v-for="(item, index) in correctionStatusOptions"
                  :key="index"
                  :label="item"
                  :value="item"
                ></el-option>
              </el-select>
            </el-form-item>
          </el-col>
        </el-row>
        <el-row>
          <el-col :span="8">
            <el-form-item label="质控时间">
              <div style="display: flex; gap: 10px;">
                <el-date-picker
                  v-model="searchForm.start_time"
                  type="date"
                  :picker-options="pickerOptions"
                  placeholder="开始日期"
                  value-format="yyyyMMdd"
                  format="yyyy年MM月dd日"
                />
                <el-date-picker
                  v-model="searchForm.end_time"
                  type="date"
                  :picker-options="[]"
                  placeholder="结束日期"
                  value-format="yyyyMMdd"
                  format="yyyy年MM月dd日"
                />
              </div>
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="入院时间">
              <div style="display: flex; gap: 10px;">
                <el-date-picker
                  v-model="searchForm.admission_start_time"
                  type="date"
                  :picker-options="pickerOptionsAdmission"
                  placeholder="开始日期"
                  value-format="yyyyMMdd"
                  format="yyyy年MM月dd日"
                />
                <el-date-picker
                  v-model="searchForm.admission_end_time"
                  type="date"
                  :picker-options="[]"
                  placeholder="结束日期"
                  value-format="yyyyMMdd"
                  format="yyyy年MM月dd日"
                />
              </div>
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="出院时间">
              <div style="display: flex; gap: 10px;">
                <el-date-picker
                  v-model="searchForm.discharge_start_time"
                  type="date"
                  :picker-options="pickerOptionsDischarge"
                  placeholder="开始日期"
                  value-format="yyyyMMdd"
                  format="yyyy年MM月dd日"
                />
                <el-date-picker
                  v-model="searchForm.discharge_end_time"
                  type="date"
                  :picker-options="[]"
                  placeholder="结束日期"
                  value-format="yyyyMMdd"
                  format="yyyy年MM月dd日"
                />
              </div>
            </el-form-item>
          </el-col>
        </el-row>
        <el-row>
          <el-col :span="6">
            <el-form-item label="规则类型" prop="rule_nature">
              <el-select
                style="width: 100%"
                v-model="searchForm.rule_nature"
                clearable
                filterable
                multiple
                collapse-tags
                placeholder="请选择"
              >
                <el-option
                  v-for="(item, index) in ruleNatureOptions"
                  :key="index"
                  :label="item"
                  :value="item"
                ></el-option>
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="18">
            <el-form-item>
              <div style="display: flex; justify-content: flex-end">
                <el-button style="background-color: #1b64b0; color: #fff" @click="loadTabData">查询</el-button>
                <el-button @click="handleReset">重置</el-button>
              </div>
            </el-form-item>
          </el-col>
        </el-row>
      </el-form>
    </div>

    <div class="section section--first" v-if="showDeptStatistic">
      <el-form :model="searchForm" label-width="100px" ref="searchFormRef">
        <el-row>
          <el-col :span="8">
            <el-form-item label="质控时间">
              <div style="display: flex; gap: 10px;">
                <el-date-picker
                  v-model="searchForm.start_time"
                  type="date"
                  :picker-options="pickerOptions"
                  placeholder="开始日期"
                  value-format="yyyyMMdd"
                  format="yyyy年MM月dd日"
                />
                <el-date-picker
                  v-model="searchForm.end_time"
                  type="date"
                  :picker-options="[]"
                  placeholder="结束日期"
                  value-format="yyyyMMdd"
                  format="yyyy年MM月dd日"
                />
              </div>
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="入院时间">
              <div style="display: flex; gap: 10px;">
                <el-date-picker
                  v-model="searchForm.admission_start_time"
                  type="date"
                  :picker-options="pickerOptionsAdmission"
                  placeholder="开始日期"
                  value-format="yyyyMMdd"
                  format="yyyy年MM月dd日"
                />
                <el-date-picker
                  v-model="searchForm.admission_end_time"
                  type="date"
                  :picker-options="[]"
                  placeholder="结束日期"
                  value-format="yyyyMMdd"
                  format="yyyy年MM月dd日"
                />
              </div>
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="出院时间">
              <div style="display: flex; gap: 10px;">
                <el-date-picker
                  v-model="searchForm.discharge_start_time"
                  type="date"
                  :picker-options="pickerOptionsDischarge"
                  placeholder="开始日期"
                  value-format="yyyyMMdd"
                  format="yyyy年MM月dd日"
                />
                <el-date-picker
                  v-model="searchForm.discharge_end_time"
                  type="date"
                  :picker-options="[]"
                  placeholder="结束日期"
                  value-format="yyyyMMdd"
                  format="yyyy年MM月dd日"
                />
              </div>
            </el-form-item>
          </el-col>
        </el-row>
        <el-row style="align-items: center">
          <el-col :span="6">
            <el-form-item label="所属科室" prop="department">
              <el-select
                style="width: 100%"
                v-model="searchForm.department"
                filterable
                clearable
                multiple
                collapse-tags
                placeholder="请选择"
              >
                <el-option
                  v-for="item of departmentList"
                  :key="`${Date.now()}${Math.random()}${item.name}`"
                  :label="item.name"
                  :value="item.dep_id"
                />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="18">
            <el-form-item>
              <div style="display: flex; justify-content: flex-end">
                <el-button style="background-color: #1b64b0; color: #fff" @click="loadTabData">查询</el-button>
                <el-button @click="handleReset">重置</el-button>
              </div>
            </el-form-item>
          </el-col>
        </el-row>
      </el-form>
    </div>

    <div class="section section--first" v-if="showRuleStatistic">
      <el-form :model="searchForm" label-width="100px" ref="searchFormRef">
        <el-row>
          <el-col :span="8">
            <el-form-item label="质控时间">
              <div style="display: flex; gap: 10px;">
                <el-date-picker
                  v-model="searchForm.start_time"
                  type="date"
                  :picker-options="pickerOptions"
                  placeholder="开始日期"
                  value-format="yyyyMMdd"
                  format="yyyy年MM月dd日"
                />
                <el-date-picker
                  v-model="searchForm.end_time"
                  type="date"
                  :picker-options="[]"
                  placeholder="结束日期"
                  value-format="yyyyMMdd"
                  format="yyyy年MM月dd日"
                />
              </div>
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="入院时间">
              <div style="display: flex; gap: 10px;">
                <el-date-picker
                  v-model="searchForm.admission_start_time"
                  type="date"
                  :picker-options="pickerOptionsAdmission"
                  placeholder="开始日期"
                  value-format="yyyyMMdd"
                  format="yyyy年MM月dd日"
                />
                <el-date-picker
                  v-model="searchForm.admission_end_time"
                  type="date"
                  :picker-options="[]"
                  placeholder="结束日期"
                  value-format="yyyyMMdd"
                  format="yyyy年MM月dd日"
                />
              </div>
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="出院时间">
              <div style="display: flex; gap: 10px;">
                <el-date-picker
                  v-model="searchForm.discharge_start_time"
                  type="date"
                  :picker-options="pickerOptionsDischarge"
                  placeholder="开始日期"
                  value-format="yyyyMMdd"
                  format="yyyy年MM月dd日"
                />
                <el-date-picker
                  v-model="searchForm.discharge_end_time"
                  type="date"
                  :picker-options="[]"
                  placeholder="结束日期"
                  value-format="yyyyMMdd"
                  format="yyyy年MM月dd日"
                />
              </div>
            </el-form-item>
          </el-col>
        </el-row>

        <el-row style="align-items: center">
          <el-col :span="6">
            <el-form-item label="规则名称" prop="rule_id">
              <el-select
                v-model="searchForm.rule_id"
                clearable
                filterable
                multiple
                collapse-tags
                placeholder="规则名称"
                style="width: 100%"
              >
                <el-option
                  v-for="(item, key, index) in searchOptions.wtArray"
                  :label="item"
                  :value="key"
                  :key="index"
                ></el-option>
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="6">
            <el-form-item label="问题级别" prop="rule_type">
              <el-select
                style="width: 100%"
                v-model="searchForm.rule_type"
                clearable
                filterable
                placeholder="请选择"
              >
                <el-option
                  v-for="(item, index) in ruleTypeOptions"
                  :key="index"
                  :label="item.name"
                  :value="item.id"
                ></el-option>
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="6">
            <el-form-item label="所属科室" prop="department">
              <el-select
                style="width: 100%"
                v-model="searchForm.department"
                filterable
                clearable
                multiple
                collapse-tags
                placeholder="请选择"
              >
                <el-option
                  v-for="item of departmentList"
                  :key="`${Date.now()}${Math.random()}${item.name}`"
                  :label="item.name"
                  :value="item.dep_id"
                />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="6">
            <el-form-item label="规则类型" prop="rule_nature">
              <el-select
                style="width: 100%"
                v-model="searchForm.rule_nature"
                clearable
                filterable
                multiple
                collapse-tags
                placeholder="请选择"
              >
                <el-option
                  v-for="(item, index) in ruleNatureOptions"
                  :key="index"
                  :label="item"
                  :value="item"
                ></el-option>
              </el-select>
            </el-form-item>
          </el-col>
        </el-row>
        <el-row>
          <el-col :span="24">
            <el-form-item>
              <div style="display: flex; justify-content: flex-end">
                <el-button style="background-color: #1b64b0; color: #fff" @click="loadTabData">查询</el-button>
                <el-button @click="handleReset">重置</el-button>
              </div>
            </el-form-item>
          </el-col>
        </el-row>
      </el-form>
    </div>

    <div class="section section--second">
      <ReportHeader :basic-info="basic_info" :isShow="0" />
      <TabNav :tabs="tabList" :active-tab="activeTab" @tab-change="handleTabChange" />
      <div class="content-wrap">
        <div v-show="activeTab === 'indexCoreReport'" class="content-item">
          <IndexCoreReport ref="indexCoreReportRef" @search="searchQueryM" />
        </div>
        <div v-show="activeTab === 'dept_statistic'" class="content-item">
          <DeptStatistics ref="departmentRef" @update:tab="updateTabDataChange" />
        </div>
        <div v-show="activeTab === 'rule_statistic'" class="content-item">
          <RuleStatistics ref="ruleStatisticRef" @update:tab="updateTabDataChange" />
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import moment from 'moment/moment';

import ReportHeader from '@/views/report/comp/ReportHeader.vue';
import TabNav from '@/views/report/comp/TabNav.vue';
import DeptStatistics from './comp/DeptStatistic.vue';
import RuleStatistics from './comp/RuleStatistic.vue';
import IndexCoreReport from './comp/IndexCoreReport.vue';

export default {
  name: 'IssueReportReport',
  components: {
    ReportHeader,
    TabNav,
    DeptStatistics,
    IndexCoreReport,
    RuleStatistics,
  },
  data() {
    const that = this;
    return {
      searchForm: {
        start_time: moment().startOf('year').format('YYYYMMDD'),
        end_time: moment().endOf('year').format('YYYYMMDD'),
        admission_start_time: '',
        admission_end_time: '',
        discharge_start_time: '',
        discharge_end_time: '',
        rule_id: [],
        resident_doctor: '',
        department: [],
        is_discharge: '',
        AAA28: '',
        rule_type: '',
        document_type: '',
        correction_status: '',
        page: 1,
        page_size: 10,
        order_key: '',
        order_type: '',
        rule_nature: [],
      },
      ruleTypeOptions: [
        { id: '1', name: '强制' },
        { id: '2', name: '建议' },
      ],
      departmentList: [],
      documentTypeOptions: [],
      correctionStatusOptions: [],
      isDischargeOptions: [
        { id: '1', name: '已出院' },
        { id: '0', name: '未出院' },
      ],

      pickerOptions: {
        disabledDate: time => {
          if (that.searchForm.end_time != '') {
            return time.getTime() > Date.now();
          }
        },
        shortcuts: [
          {
            text: '今天',
            onClick(picker) {
              picker.$emit('pick', moment().format('YYYYMMDD'));
              that.searchForm.end_time = moment().format('YYYYMMDD');
            },
          },
          {
            text: '近7天',
            onClick(picker) {
              picker.$emit('pick', moment().subtract(7, 'days').format('YYYYMMDD'));
              that.searchForm.end_time = moment().format('YYYYMMDD');
            },
          },
          {
            text: '近30天',
            onClick(picker) {
              picker.$emit('pick', moment().subtract(30, 'days').format('YYYYMMDD'));
              that.searchForm.end_time = moment().format('YYYYMMDD');
            },
          },
          {
            text: '近3年',
            onClick(picker) {
              picker.$emit('pick', moment().subtract(3, 'years').format('YYYYMMDD'));
              that.searchForm.end_time = moment().format('YYYYMMDD');
            },
          },
          {
            text: '一季度',
            onClick(picker) {
              picker.$emit('pick', moment().startOf('year').format('YYYYMMDD'));
              that.searchForm.end_time = moment().startOf('year').add(3, 'M').subtract(1, 'days').format('YYYYMMDD');
            },
          },
          {
            text: '二季度',
            onClick(picker) {
              picker.$emit('pick', moment().startOf('year').add(3, 'M').format('YYYYMMDD'));
              that.searchForm.end_time = moment().startOf('year').add(6, 'M').subtract(1, 'days').format('YYYYMMDD');
            },
          },
          {
            text: '三季度',
            onClick(picker) {
              picker.$emit('pick', moment().startOf('year').add(6, 'M').format('YYYYMMDD'));
              that.searchForm.end_time = moment().startOf('year').add(9, 'M').subtract(1, 'days').format('YYYYMMDD');
            },
          },
          {
            text: '四季度',
            onClick(picker) {
              picker.$emit('pick', moment().startOf('year').add(9, 'M').format('YYYYMMDD'));
              that.searchForm.end_time = moment().startOf('year').add(12, 'M').subtract(1, 'days').format('YYYYMMDD');
            },
          },
          {
            text: moment().add(-2, 'Y').format('YYYY'),
            onClick(picker) {
              picker.$emit('pick', moment().add(-2, 'Y').startOf('year').format('YYYYMMDD'));
              that.searchForm.end_time = moment().add(-2, 'Y').endOf('year').format('YYYYMMDD');
            },
          },
          {
            text: moment().add(-1, 'Y').format('YYYY'),
            onClick(picker) {
              picker.$emit('pick', moment().add(-1, 'Y').startOf('year').format('YYYYMMDD'));
              that.searchForm.end_time = moment().add(-1, 'Y').endOf('year').format('YYYYMMDD');
            },
          },
          {
            text: moment().format('YYYY'),
            onClick(picker) {
              picker.$emit('pick', moment().startOf('year').format('YYYYMMDD'));
              console.log(moment().endOf('year').format('YYYYMMDD'));
              that.searchForm.end_time = moment().endOf('year').format('YYYYMMDD');
            },
          },
        ],
      },
      pickerOptionsAdmission: {
        disabledDate: time => {
          if (that.searchForm.admission_end_time != '') {
            return time.getTime() > Date.now();
          }
        },
        shortcuts: [
          {
            text: '今天',
            onClick(picker) {
              picker.$emit('pick', moment().format('YYYYMMDD'));
              that.searchForm.admission_end_time = moment().format('YYYYMMDD');
            },
          },
          {
            text: '近7天',
            onClick(picker) {
              picker.$emit('pick', moment().subtract(7, 'days').format('YYYYMMDD'));
              that.searchForm.admission_end_time = moment().format('YYYYMMDD');
            },
          },
          {
            text: '近30天',
            onClick(picker) {
              picker.$emit('pick', moment().subtract(30, 'days').format('YYYYMMDD'));
              that.searchForm.admission_end_time = moment().format('YYYYMMDD');
            },
          },
          {
            text: '近3年',
            onClick(picker) {
              picker.$emit('pick', moment().subtract(3, 'years').format('YYYYMMDD'));
              that.searchForm.admission_end_time = moment().format('YYYYMMDD');
            },
          },
          {
            text: '一季度',
            onClick(picker) {
              picker.$emit('pick', moment().startOf('year').format('YYYYMMDD'));
              that.searchForm.admission_end_time = moment().startOf('year').add(3, 'M').subtract(1, 'days').format('YYYYMMDD');
            },
          },
          {
            text: '二季度',
            onClick(picker) {
              picker.$emit('pick', moment().startOf('year').add(3, 'M').format('YYYYMMDD'));
              that.searchForm.admission_end_time = moment().startOf('year').add(6, 'M').subtract(1, 'days').format('YYYYMMDD');
            },
          },
          {
            text: '三季度',
            onClick(picker) {
              picker.$emit('pick', moment().startOf('year').add(6, 'M').format('YYYYMMDD'));
              that.searchForm.admission_end_time = moment().startOf('year').add(9, 'M').subtract(1, 'days').format('YYYYMMDD');
            },
          },
          {
            text: '四季度',
            onClick(picker) {
              picker.$emit('pick', moment().startOf('year').add(9, 'M').format('YYYYMMDD'));
              that.searchForm.admission_end_time = moment().startOf('year').add(12, 'M').subtract(1, 'days').format('YYYYMMDD');
            },
          },
          {
            text: moment().add(-2, 'Y').format('YYYY'),
            onClick(picker) {
              picker.$emit('pick', moment().add(-2, 'Y').startOf('year').format('YYYYMMDD'));
              that.searchForm.admission_end_time = moment().add(-2, 'Y').endOf('year').format('YYYYMMDD');
            },
          },
          {
            text: moment().add(-1, 'Y').format('YYYY'),
            onClick(picker) {
              picker.$emit('pick', moment().add(-1, 'Y').startOf('year').format('YYYYMMDD'));
              that.searchForm.admission_end_time = moment().add(-1, 'Y').endOf('year').format('YYYYMMDD');
            },
          },
          {
            text: moment().format('YYYY'),
            onClick(picker) {
              picker.$emit('pick', moment().startOf('year').format('YYYYMMDD'));
              console.log(moment().endOf('year').format('YYYYMMDD'));
              that.searchForm.admission_end_time = moment().endOf('year').format('YYYYMMDD');
            },
          },
        ],
      },
      pickerOptionsDischarge: {
        disabledDate: time => {
          if (that.searchForm.discharge_end_time != '') {
            return time.getTime() > Date.now();
          }
        },
        shortcuts: [
          {
            text: '今天',
            onClick(picker) {
              picker.$emit('pick', moment().format('YYYYMMDD'));
              that.searchForm.discharge_end_time = moment().format('YYYYMMDD');
            },
          },
          {
            text: '近7天',
            onClick(picker) {
              picker.$emit('pick', moment().subtract(7, 'days').format('YYYYMMDD'));
              that.searchForm.discharge_end_time = moment().format('YYYYMMDD');
            },
          },
          {
            text: '近30天',
            onClick(picker) {
              picker.$emit('pick', moment().subtract(30, 'days').format('YYYYMMDD'));
              that.searchForm.discharge_end_time = moment().format('YYYYMMDD');
            },
          },
          {
            text: '近3年',
            onClick(picker) {
              picker.$emit('pick', moment().subtract(3, 'years').format('YYYYMMDD'));
              that.searchForm.discharge_end_time = moment().format('YYYYMMDD');
            },
          },
          {
            text: '一季度',
            onClick(picker) {
              picker.$emit('pick', moment().startOf('year').format('YYYYMMDD'));
              that.searchForm.discharge_end_time = moment().startOf('year').add(3, 'M').subtract(1, 'days').format('YYYYMMDD');
            },
          },
          {
            text: '二季度',
            onClick(picker) {
              picker.$emit('pick', moment().startOf('year').add(3, 'M').format('YYYYMMDD'));
              that.searchForm.discharge_end_time = moment().startOf('year').add(6, 'M').subtract(1, 'days').format('YYYYMMDD');
            },
          },
          {
            text: '三季度',
            onClick(picker) {
              picker.$emit('pick', moment().startOf('year').add(6, 'M').format('YYYYMMDD'));
              that.searchForm.discharge_end_time = moment().startOf('year').add(9, 'M').subtract(1, 'days').format('YYYYMMDD');
            },
          },
          {
            text: '四季度',
            onClick(picker) {
              picker.$emit('pick', moment().startOf('year').add(9, 'M').format('YYYYMMDD'));
              that.searchForm.discharge_end_time = moment().startOf('year').add(12, 'M').subtract(1, 'days').format('YYYYMMDD');
            },
          },
          {
            text: moment().add(-2, 'Y').format('YYYY'),
            onClick(picker) {
              picker.$emit('pick', moment().add(-2, 'Y').startOf('year').format('YYYYMMDD'));
              that.searchForm.discharge_end_time = moment().add(-2, 'Y').endOf('year').format('YYYYMMDD');
            },
          },
          {
            text: moment().add(-1, 'Y').format('YYYY'),
            onClick(picker) {
              picker.$emit('pick', moment().add(-1, 'Y').startOf('year').format('YYYYMMDD'));
              that.searchForm.discharge_end_time = moment().add(-1, 'Y').endOf('year').format('YYYYMMDD');
            },
          },
          {
            text: moment().format('YYYY'),
            onClick(picker) {
              picker.$emit('pick', moment().startOf('year').format('YYYYMMDD'));
              console.log(moment().endOf('year').format('YYYYMMDD'));
              that.searchForm.discharge_end_time = moment().endOf('year').format('YYYYMMDD');
            },
          },
        ],
      },
      basic_info: {
        title: '运行病历报表',
      },
      activeTab: 'indexCoreReport',
      tabList: [
        { key: 'indexCoreReport', name: '质控明细' },
        { key: 'rule_statistic', name: '按规则统计' },
        { key: 'dept_statistic', name: '按科室统计' },
      ],
      tableData: {
        count: 0,
        list: [],
      },
      searchOptions: {
        wtArray: [],
      },
      ruleNatureOptions: [],
    };
  },
  computed: {
    showIndexCoreReport() {
      return this.activeTab === 'indexCoreReport';
    },
    showRuleStatistic() {
      return this.activeTab === 'rule_statistic';
    },
    showDeptStatistic() {
      return this.activeTab === 'dept_statistic';
    },
  },
  activated() {},
  mounted() {
    this.getSearchOptions();
    this.getRuleNatureOptions();
    this.getDeportmentList();
    this.getDocumentTypeList();
    this.getCorrectionStatusList();
    this.loadTabData();
  },
  methods: {
    handleTabChange(tabKey) {
      this.handleReset();
      this.activeTab = tabKey;
      this.loadTabData();
    },

    updateTabDataChange(row, searchQuery, type) {
      console.log('handleTabChange====>>type', row, searchQuery, type);
      this.activeTab = 'indexCoreReport';
      if (type === 'rule') {
        if (row?.rule_id != null && row.rule_id !== '') {
          this.searchForm.rule_id = [String(row.rule_id)];
        } else {
          this.searchForm.rule_id = [];
        }
      } else {
        if (row?.department_id != null && row.department_id !== '') {
          this.searchForm.department = [row.department_id];
        } else {
          this.searchForm.department = [];
        }
      }
      this.loadTabData();
    },

    getSearchOptions() {
      this.$axios.post('CaseHistory/Terminal/getQxBlSearchOptions', { is_tj: 1 }).then(res => {
        this.searchOptions.wtArray = res.data.wtArray;
      });
    },
    getRuleNatureOptions() {
      this.$axios2.post('/case-quality/rule_nature_list').then(res => {
        this.ruleNatureOptions = res.data.list;
      });
    },
    getDeportmentList() {
      this.$axios
        .post('/get_department_list')
        .then(res => {
          const { data } = res;
          this.departmentList = data;
        })
        .catch(error => {
          console.log(error);
        });
    },

    getDocumentTypeList() {
      this.$axios2
        .post('/case-quality/document_type_list')
        .then(res => {
          const { data } = res;
          this.documentTypeOptions = data.list;
        })
        .catch(error => {
          console.log(error);
        });
    },

    getCorrectionStatusList() {
      this.$axios2
        .post('/case-quality/correction_status_list')
        .then(res => {
          const { data } = res;
          this.correctionStatusOptions = data.list;
        })
        .catch(error => {
          console.log(error);
        });
    },

    handleReset() {
      this.searchForm = {
        start_time: moment().startOf('year').format('YYYYMMDD'),
        end_time: moment().endOf('year').format('YYYYMMDD'),
        admission_start_time: '',
        admission_end_time: '',
        discharge_start_time: '',
        discharge_end_time: '',
        rule_id: [],
        resident_doctor: '',
        department: [],
        is_discharge: '',
        AAA28: '',
        rule_type: '',
        document_type: '',
        correction_status: '',
        page: 1,
        page_size: 10,
        order_key: '',
        order_type: '',
        rule_nature: [],
      };
      // this.activeTab = 'indexCoreReport';
      this.loadTabData();
    },

    async loadTabData() {
      try {
        const params = {
          ...this.searchForm,
          is_export: 0,
        };

        switch (this.activeTab) {
          case 'indexCoreReport':
            const resCore = await this.$axios2.post('/case-quality/shizhong_quality_records', params);
            this.tableData = resCore.data || { count: 0, list: [] };
            this.$nextTick(() => {
              this.$refs.indexCoreReportRef?.initData(this.searchForm, this.tableData);
            });
            break;
          case 'rule_statistic':
            const resRule = await this.$axios2.post('/case-quality/shizhong_quality_rule_statistics', params);
            this.tableData = resRule.data || { count: 0, list: [] };
            // console.log("resRule",resRule);
            this.$nextTick(() => {
              this.$refs.ruleStatisticRef?.initData(this.searchForm, this.tableData);
            });
            break;
          case 'dept_statistic':
            const resDept = await this.$axios2.post('/case-quality/shizhong_quality_department_statistics', params);
            this.tableData = resDept.data || { count: 0, list: [] };
            this.$nextTick(() => {
              this.$refs.departmentRef?.initData(this.searchForm, this.tableData);
            });
            break;
        }
      } catch (err) {
        console.error('tab数据加载失败', err);
      }
    },
    searchQueryM(pageParams, queryParams) {
      this.searchForm = { ...queryParams };
      this.searchForm.page = pageParams.currentPage;
      this.searchForm.page_size = pageParams.pageSize;
      this.loadTabData();
    },
  },
};
</script>

<style lang="scss" scoped>
.report-container {
  margin: 0 auto;
  padding: 20px;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'PingFang SC', 'Hiragino Sans GB', 'Microsoft YaHei', sans-serif;
  background: #f0f2f5;
  min-height: 100vh;
  box-sizing: border-box;

  .section {
    background-color: #fff;
    border-radius: 6px;
    border: 1px solid #e5e7eb;
    margin-bottom: 4px;
    padding: 16px;

    &--first {
      margin-bottom: 4px;
    }

    &--second {
      margin-bottom: 0;
    }
  }

  .filter-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    margin-bottom: 16px;

    .filter-btn {
      display: flex;
      align-items: center;
      gap: 8px;
      padding: 8px 16px;
      border: 1px solid #e5e7eb;
      background-color: #fff;
      cursor: pointer;
      font-size: 14px;
      color: #374151;
      border-radius: 4px;
      transition: all 0.2s;

      &:hover {
        background-color: #f9fafb;
        border-color: #d1d5db;
      }
    }

    .toggle-arrow {
      transition: transform 0.2s;

      &.active {
        transform: rotate(180deg);
      }
    }

    .export-bar {
      display: flex;
      align-items: center;
      gap: 8px;

      .export-btn {
        background: #185da6;
        color: #fff;
        border: none;
        padding: 8px 20px;
        border-radius: 4px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 500;
        transition: background 0.3s;

        &:hover {
          background: #134a85;
        }
      }
    }
  }

  .content-wrap {
    margin-top: 20px;

    .content-item {
      width: 100%;
    }
  }
}
</style>