<template>
  <div class="medical-records-container">
    <div class="medical-records-container_wrapper">
      <div
        class="medical-records-container_header"
        style="display: flex; align-items: center; justify-content: space-between"
      >
        <Title :title="'病历列表'" style="margin-top: 8px" />
        <span style="float: right">
          <el-button
            type="primary"
            icon="el-icon-download"
            class="export-btn"
            @click="onExport"
          >导出数据</el-button>
          <el-button @click="toBack">返回</el-button>
        </span>
      </div>
      <div class="medical-records-container_header">
        <el-form :model="searchData" class="demo-form-inline" label-width="90px">
          <el-row :gutter="24">
            <el-col :span="6">
              <el-form-item label="病案号">
                <el-input
                  v-model="searchData.case_number"
                  clearable
                  placeholder="请输入"
                  style="width: 100%"
                ></el-input>
              </el-form-item>
            </el-col>
            <el-col :span="6">
              <el-form-item label="所属院区">
                <el-select
                  style="width: 100%"
                  placeholder="请选择所属院区"
                  v-model="searchData.campus"
                  @change="yqChange"
                  multiple
                  collapse-tags
                  clearable
                  filterable
                >
                  <el-option
                    v-for="(item, index) in searchOptions.yqArray"
                    :key="index"
                    :label="item.dep_name"
                    :value="item.dep_id"
                  ></el-option>
                </el-select>
              </el-form-item>
            </el-col>
            <el-col :span="6">
              <el-form-item label="入院科室">
                <el-cascader
                  style="width: 100%"
                  placeholder="请选择科室"
                  v-model="searchData.admission_departments"
                  :options="searchOptions.ksArray"
                  filterable
                  :props="searchOptions.cascaderProps"
                  clearable
                  collapse-tags
                  @change="ksChange('in')"
                ></el-cascader>
              </el-form-item>
            </el-col>
            <el-col :span="6">
              <el-form-item label="是否编码">
                <el-select
                  v-model="searchData.catalog_status"
                  clearable
                  placeholder="请选择"
                  style="width: 100%"
                >
                  <el-option label="全部" value="全部"></el-option>
                  <el-option label="已编目" value="已编目"></el-option>
                  <el-option label="未编目" value="未编目"></el-option>
                  <el-option label="空" value="空"></el-option>
                </el-select>
              </el-form-item>
            </el-col>
            <!-- <el-col :span="6">
              <el-form-item label="入院病区">
                <el-cascader
                  style="width: 100%"
                  placeholder="请选择病区"
                  v-model="searchData.admission_wards"
                  :options="searchOptions.bqArray"
                  filterable
                  :props="searchOptions.cascaderProps"
                  clearable
                  collapse-tags
                ></el-cascader>
              </el-form-item>
            </el-col>-->
          </el-row>

          <el-row :gutter="24">
            <el-col :span="6">
              <el-form-item label="病历等级">
                <el-select
                  v-model="searchData.record_levels"
                  multiple
                  filterable
                  clearable
                  collapse-tags
                  placeholder="请选择"
                  style="width: 100%"
                >
                  <el-option label="甲" value="甲"></el-option>
                  <el-option label="乙" value="乙"></el-option>
                  <el-option label="丙" value="丙"></el-option>
                </el-select>
              </el-form-item>
            </el-col>
            <el-col :span="6">
              <el-form-item label="患者状态">
                <el-select
                  v-model="searchData.patient_status"
                  clearable
                  placeholder="请选择"
                  style="width: 100%"
                >
                  <el-option label="全部" value="全部"></el-option>
                  <el-option label="在院" value="在院"></el-option>
                  <el-option label="出院" value="出院"></el-option>
                  <el-option label="空" value="空"></el-option>
                </el-select>
              </el-form-item>
            </el-col>
            <el-col :span="6">
              <el-form-item label="出院科室">
                <el-cascader
                  style="width: 100%"
                  placeholder="请选择科室"
                  v-model="searchData.discharge_departments"
                  :options="searchOptions.ksArray"
                  filterable
                  :props="searchOptions.cascaderProps"
                  clearable
                  collapse-tags
                  @change="ksChange('out')"
                ></el-cascader>
              </el-form-item>
            </el-col>
            <el-col :span="6">
              <el-form-item label="单否问题">
                <el-select
                  v-model="searchData.is_danfou"
                  clearable
                  placeholder="请选择"
                  style="width: 100%"
                >
                  <el-option label="全部" value="全部"></el-option>
                  <el-option label="有" value="有"></el-option>
                  <el-option label="无" value="无"></el-option>
                </el-select>
              </el-form-item>
            </el-col>
            <!-- <el-col :span="6">
              <el-form-item label="出院病区">
                <el-cascader
                  style="width: 100%"
                  placeholder="请选择病区"
                  v-model="searchData.discharge_wards"
                  :options="searchOptions.bqArray"
                  filterable
                  :props="searchOptions.cascaderProps"
                  clearable
                  collapse-tags
                ></el-cascader>
              </el-form-item>
            </el-col>-->
          </el-row>

          <el-row :gutter="24">
            <el-col :span="8">
              <DateRangePicker
                v-model="searchData"
                labelText="入院日期"
                startKey="admission_date_start"
                endKey="admission_date_end"
                startPlaceholder="入院开始日期"
                endPlaceholder="入院结束日期"
              />
            </el-col>
            <el-col :span="8">
              <DateRangePicker
                v-model="searchData"
                labelText="出院日期"
                startKey="discharge_date_start"
                endKey="discharge_date_end"
                startPlaceholder="出院开始日期"
                endPlaceholder="出院结束日期"
              />
            </el-col>
          </el-row>
          <el-row :gutter="24" v-show="expand">
            <el-col
              :span="8"
              v-show="pageType === 'overview-defect-control' || pageType === 'distribution-defect-control' || pageType === 'overview-defect-control-num' || pageType === 'distribution-defect-control-case'"
            >
              <el-form-item label="缺陷描述" prop="rule_id">
                <el-select
                  v-model="searchData.rule_id"
                  multiple
                  filterable
                  clearable
                  collapse-tags
                  placeholder="请选择问题描述"
                  style="width: 100%"
                >
                  <el-option
                    v-for="(value, key) in searchOptions.wtArray"
                    :label="value"
                    :value="Number(key)"
                    :key="key"
                  />
                </el-select>
              </el-form-item>
            </el-col>
            <el-col
              :span="6"
              v-show="pageType === 'distribution-defect-control' || pageType === 'distribution-defect-control-case'"
            >
              <el-form-item label="缺陷类型">
                <el-select
                  v-model="searchData.rule_type"
                  clearable
                  placeholder="请选择"
                  style="width: 100%"
                >
                  <el-option label="病历" value="1"></el-option>
                  <el-option label="首页" value="2"></el-option>
                </el-select>
              </el-form-item>
            </el-col>
          </el-row>

          <el-row :gutter="24" style="margin-top: 10px">
            <el-col :span="12">
              <el-form-item label>
                <el-button
                  type="text"
                  :icon="`el-icon-arrow-${expand ? 'up' : 'down'}`"
                  @click="expand = !expand"
                >{{ expand ? '收起' : '展开' }}</el-button>
              </el-form-item>
            </el-col>
            <el-col :span="12" style="text-align: right">
              <el-form-item label>
                <el-button class="export-btn" type="primary" @click="onSearch">查询</el-button>
                <el-button @click="reset">重置</el-button>
              </el-form-item>
            </el-col>
          </el-row>
        </el-form>
      </div>

      <el-table :data="tableData" @sort-change="handleSortChange" style="width: 100%">
        <el-table-column type="index" label="序号" align="center" width="80">
          <template slot-scope="scope">
            <span>{{ scope.$index + 1 + (paginationData.currentPage - 1) * paginationData.pageSize }}</span>
          </template>
        </el-table-column>
        <el-table-column
          v-if="pageType === 'distribution-defect-control' || pageType === 'distribution-defect-control-case'"
          prop="rule_name"
          label="缺陷描述"
          width="120"
        ></el-table-column>
        <el-table-column prop="name" label="姓名" width="100"></el-table-column>
        <el-table-column prop="age" label="年龄" width="100">
          <template slot-scope="scope">
            <span>{{ scope.row.age || '--' }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="case_number" label="病案号" width="120">
          <template slot-scope="scope">
            <span
              v-if="scope.row.case_number"
              class="value-text-blue value-underline"
              @click="handleClick(scope.row)"
            >{{ scope.row.case_number }}</span>
            <span v-else>--</span>
          </template>
        </el-table-column>
        <el-table-column label="病案得分" width="120">
          <template slot-scope="scope">
            <span
              v-if="scope.row.record_grade"
            >{{ scope.row.record_grade }} | {{ scope.row.record_grade_score || '--' }}</span>
            <span v-else>--</span>
          </template>
        </el-table-column>
        <el-table-column prop="case_score" label="事中得分" width="120">
          <template slot-scope="scope">
            <span>{{ scope.row.case_score || '--' }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="home_score" label="首页得分" width="120">
          <template slot-scope="scope">
            <span>{{ scope.row.home_score || '--' }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="admission_date" label="入院日期" width="120">
          <template slot-scope="scope">
            <span>{{ scope.row.admission_date ? formatDate(scope.row.admission_date) : '--' }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="admission_department" label="入院科室" width="120"></el-table-column>
        <!-- <el-table-column prop="admission_ward" label="入院病区" width="120"></el-table-column> -->
        <el-table-column prop="discharge_date" label="出院日期" width="120">
          <template slot-scope="scope">
            <span>{{ scope.row.discharge_date ? formatDate(scope.row.discharge_date) : '--' }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="discharge_department" label="出院科室" width="120"></el-table-column>
        <!-- <el-table-column prop="discharge_ward" label="出院病区" width="120"></el-table-column> -->
        <el-table-column prop="hospital_days" label="住院天数" width="100"></el-table-column>
        <el-table-column prop="admission_status" label="入院时情况" width="150"></el-table-column>
        <el-table-column prop="diagnosis_code" label="出院诊断ICD" width="100"></el-table-column>
        <el-table-column prop="diagnosis_name" label="出院诊断" width="150"></el-table-column>
        <el-table-column prop="discharge_status" label="出院情况" width="120"></el-table-column>
        <el-table-column prop="chief_physician" label="科主任" width="120"></el-table-column>
        <el-table-column prop="deputy_chief_physician" label="主副主任医师" width="120"></el-table-column>
        <el-table-column prop="attending_physician" label="主治医师" width="120"></el-table-column>
        <el-table-column prop="resident_physician" label="住院医师" width="120"></el-table-column>
        <el-table-column prop="coder" label="编码员" width="120"></el-table-column>
        <el-table-column prop="operation_code" label="手术编码" width="120"></el-table-column>
        <el-table-column prop="operation_name" label="手术名称" width="150"></el-table-column>
        <el-table-column prop="operation_date" label="手术日期" width="120">
          <template slot-scope="scope">
            <span>{{ scope.row.operation_date ? formatDate(scope.row.operation_date) : '--' }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="surgeon" label="术者" width="120"></el-table-column>
        <el-table-column prop="first_assistant" label="一助" width="120"></el-table-column>
        <el-table-column prop="second_assistant" label="二助" width="120"></el-table-column>
        <el-table-column prop="anesthesia_method" label="麻醉方式" width="120"></el-table-column>
        <el-table-column prop="wound_healing_grade" label="愈合等级" width="120"></el-table-column>
        <el-table-column prop="record_grade" label="病历等级" width="100"></el-table-column>
        <el-table-column prop="is_danfou" label="单否问题" width="100"></el-table-column>
        <el-table-column prop="patient_status" label="患者状态" width="100"></el-table-column>
        <el-table-column prop="catalog_status" label="编码状态" width="100"></el-table-column>
      </el-table>

      <el-pagination
        v-if="tableData && tableData.length !== 0"
        @size-change="SizeChangeEvent"
        @current-change="pageHasChanged"
        :total="paginationData.total"
        background
        class="table-pagination"
        style="margin: 30px 0px; float: right"
        :page-size="paginationData.pageSize"
        :current-page.sync="paginationData.currentPage"
        layout="total, sizes, prev, pager, next, jumper"
      ></el-pagination>
    </div>
  </div>
</template>

<script>
import Title from '@/components/Title';
import domMessage from '@/utils/messageOnce';
import { totalCasesDrillDown } from '@/api/excel';
import moment from 'moment';
import DateRangePicker from '@/components/DateRangePicker/index-v2.vue';

const messageOnce = new domMessage();

export default {
  components: {
    Title,
    DateRangePicker,
  },
  data() {
    let { query } = this.$route;
    return {
      expand: false,
      pageType: query.type || 'all',
      // 搜索条件 - 完全匹配接口字段
      searchData: {
        // 出院相关
        discharge_date_start: query.startTime || '', // 出院日期起始
        discharge_date_end: query.endTime || '', // 出院日期截止 (yyyyMMdd)
        discharge_departments: query.dep_id || [], // 出院科室，多选数组
        discharge_wards: [], // 出院病区，多选数组
        // 基础信息
        case_number: query.case_number || '', // 病案号，精确匹配
        // 入院相关
        admission_date_start: '', // 入院日期起始
        admission_date_end: '', // 入院日期截止
        admission_departments: [], // 入院科室，多选数组
        admission_wards: [], // 入院病区，多选数组
        // 其他筛选条件
        campus: [], // 院区代码，多选数组
        record_levels: query.record_levels || '', // 病历等级筛选数组 ["甲","乙","丙"]
        patient_status: '全部', // 患者状态：在院 / 出院 / 全部
        catalog_status: '全部', // 编码状态：已编目 / 未编目 / 全部
        is_danfou: query.is_danfou || '全部', // 是否有单否问题：有 / 无 / 全部
        resident_doctor: query.resident_doctor || [],
        // 排序相关
        order: '',
        order_sort: '',
        rule_id: query.rule_id || [],
        rule_type: query.rule_type || '',
      },

      // 列表数据
      tableData: [],

      paginationData: {
        total: 0,
        currentPage: 1,
        pageSize: 10, // 接口默认20条/页
      },
      apiMap: {
        all: '/quality_report/quality_report_drill/totalCasesDrillDown',
        'quality-control': '/quality_report/quality_report_drill/totalCasesDrillDown',
        'overview-defect-control': '/quality_report/quality_report_drill/defectCasesDrillDown',
        'overview-defect-control-num': '/quality_report/quality_report_drill/defectCountDrillDown',
        'distribution-defect-control': '/quality_report/quality_report_drill/defectCasesDrillDown',
        'appeal-doctors': '/quality_report/quality_report_drill/appealCasesDrillDown',
        'rejected-count': '/quality_report/quality_report_drill/appealCasesDrillDown',
        'dept-statistic': '/quality_report/quality_report_drill/defectCasesDrillDown',
        'doctor-statistic': '/quality_report/quality_report_drill/defectCasesDrillDown',
        'distribution-defect-control-case': '/quality_report/quality_report_drill/totalCasesDrillDown',
      },
      // 下拉选项列表
      deptList: [], // 科室列表
      wardList: [], // 病区列表
      areaList: [], // 院区列表
      doctorList: [], // 住院医师列表
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
  watch: {
    $route(to, from) {
      if (to.query) {
        // 出院日期
        if (to.query.discharge_date_start) this.searchData.discharge_date_start = to.query.discharge_date_start;
        if (to.query.discharge_date_end) this.searchData.discharge_date_end = to.query.discharge_date_end;

        // 出院科室
        if (to.query.discharge_departments) {
          this.searchData.discharge_departments = Array.isArray(to.query.discharge_departments) ? to.query.discharge_departments : [to.query.discharge_departments];
        }
      }
    },
  },
  created() {
    this.getSearchOptions();
    this.getList();
  },
  methods: {
    formatDate(dateStr) {
      if (!dateStr) return '--';
      if (dateStr.length === 8 && /^\d{8}$/.test(dateStr)) {
        return `${dateStr.substring(0, 4)} 年 ${dateStr.substring(4, 6)} 月 ${dateStr.substring(6, 8)} 日`;
      }
      return moment(dateStr).format('YYYY 年 MM 月 DD 日');
    },
    handleClick(row) {
      console.log('handleClick', row);
      this.storageSet('getData', row.zyh);
      // this.storageSet('getDataRule', this.searchData.rule_id);
      localStorage.setItem('isControl', true);
      this.$router.push({
        name: 'caseViews',
        query: {
          pageType: 'middleCaseControl',
        },
      });
    },

    yqChange() {
      this.searchData.discharge_departments = []; // 出院科室，多选数组
      this.searchData.discharge_wards = []; // 出院病区，多选数组
      this.searchData.admission_departments = []; // 入院科室，多选数组
      this.searchData.admission_wards = []; // 入院病区，多选数组
      this.$axios.post('CaseHistory/Terminal/getKsOptions', { YQ_CODE: this.searchData.campus }).then(res => {
        this.searchOptions.ksArray = this.cancelChildren(res.data.ksArray); //科室
        this.searchOptions.bqArray = this.cancelChildren(res.data.bqArray); //病区
      });
    },

    ksChange(type) {
      if (type === 'in') {
        this.searchData.admission_wards = [];
        this.$axios.post('CaseHistory/Terminal/getBqOptions', { KS_CODE: this.searchData.admission_departments }).then(res => {
          this.searchOptions.bqArray = this.cancelChildren(res.data.bqArray); //病区
        });
      } else if (type === 'out') {
        this.searchData.discharge_wards = [];
        this.$axios.post('CaseHistory/Terminal/getBqOptions', { KS_CODE: this.searchData.discharge_departments }).then(res => {
          this.searchOptions.bqArray = this.cancelChildren(res.data.bqArray); //病区
        });
      }
    },

    handleSortChange(column) {
      const { prop, order } = column;
      if (!prop) return;

      this.searchData.order = prop;
      this.searchData.order_sort = order === 'descending' ? 'desc' : 'asc';
      this.getList();
    },

    getSearchOptions() {
      this.$axios
        .post('CaseHistory/Terminal/getQxBlSearchOptions', {})
        .then(res => {
          this.searchOptions.yqArray = res.data.yqArray; //院区
          this.searchOptions.ksArray = this.cancelChildren(res.data.ksArray); //科室
          this.searchOptions.bqArray = this.cancelChildren(res.data.bqArray); //病区
          this.searchOptions.bazlArray = res.data.bazlArray;
          this.searchOptions.lyTypeArray = res.data.lyTypeArray;
          this.searchOptions.wtArray = res.data.wtArray;
          this.searchOptions.ruleTypeArray = res.data.ruleTypeArray;
        })
        .catch(err => {
          console.error('获取搜索选项失败:', err);
        });
    },
    cancelChildren(arr) {
      if (!arr) {
        return [];
      }
      return arr;
    },

    getList() {
      const params = {
        page: this.paginationData.currentPage,
        limit: this.paginationData.pageSize,
        ...this.searchData,
      };

      // Object.keys(params).forEach(key => {
      //   if (params[key] === '' || params[key] === undefined || (Array.isArray(params[key]) && params[key].length === 0)) {
      //     delete params[key];
      //   }
      //   if (params[key] === '全部') {
      //     delete params[key];
      //   }
      // });
      // if (params.discharge_date_start) {
      //   params.rule_type = this.rule_type;
      // }
      const apiUrl = this.apiMap[this.pageType] || this.apiMap['all'];
      this.$axios
        .post(apiUrl, params)
        .then(res => {
          this.tableData = res.data?.list || [];
          this.paginationData.total = res.data?.total || 0;
          console.log('表格数据:', this.tableData);
        })
        .catch(err => {
          console.error('获取病历列表失败:', err);
          messageOnce.error('数据加载失败，请重试');
          this.tableData = [];
          this.paginationData.total = 0;
        });
    },

    toBack() {
      this.$router.back();
    },

    reset() {
      this.searchData = {
        discharge_date_start: '',
        discharge_date_end: '',
        discharge_departments: [],
        discharge_wards: [],
        case_number: '',
        admission_date_start: '',
        admission_date_end: '',
        admission_departments: [],
        admission_wards: [],
        campus: [],
        record_levels: [],
        patient_status: '全部',
        catalog_status: '全部',
        is_danfou: '全部',
        resident_doctor: [],
        order: '',
        order_sort: '',
        rule_id: [],
      };
      this.paginationData.currentPage = 1;
      this.expand = false;
    },

    onSearch() {
      this.paginationData.currentPage = 1;
      this.getList();
    },

    onExport() {
      const params = {
        ...this.searchData,
        is_export: 1,
      };
      const apiUrl = this.apiMap[this.pageType] || this.apiMap['all'];
      totalCasesDrillDown(apiUrl, params)
        .then(res => {
          const content = res.data;
          const blob = new Blob([content]);
          const fileName = `病历信息列表_${moment().format('YYYYMMDDHHmmss')}.csv`;

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
          messageOnce.success('导出成功');
        })
        .catch(err => {
          console.error('导出失败:', err);
        });
    },

    SizeChangeEvent(val) {
      this.paginationData.pageSize = val;
      this.getList();
    },

    pageHasChanged(val) {
      this.paginationData.currentPage = val;
      this.getList();
    },
  },
};
</script>

<style lang="scss" scoped>
.medical-records-container {
  padding: 0 16px 16px 16px;

  .medical-records-container_wrapper {
    padding: 16px;
    background: #fff;
    border-radius: 5px;
    height: calc(100vh - 82px);
    overflow: auto;

    .medical-records-container_header {
      overflow: hidden;
      margin-bottom: 16px;
    }
  }
}

::v-deep.el-table .el-table__header tr th {
  background: #f1f6ff;
  color: #13171e;
  border-bottom: 0px;
}

::v-deep.el-table .el-table__row td {
  color: #7e8bab;
  border-bottom: 1px solid #f4f4f4;
}

::v-deep.el-table .el-table__header tr th:first-child {
  border-radius: 5px 0px 0px 5px;
}

::v-deep.el-select .el-tag {
  margin: 2px 0;
}

.table-pagination {
  margin-top: 20px;
}

.export-btn {
  margin-right: 10px;
}

.value-text-blue {
  color: #185da6;
  cursor: pointer;
}

.value-text-red {
  color: #ef4444;
  cursor: pointer;
}

.value-underline {
  text-decoration: underline;
  text-underline-offset: 4px;
  text-decoration-color: currentColor;
  text-decoration-thickness: 2px;
}
</style>