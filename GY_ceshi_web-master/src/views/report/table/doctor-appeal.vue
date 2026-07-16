<!-- 医师申诉 -->
 <template>
  <div class="medical-records-container">
    <div class="medical-records-container_wrapper">
      <div
        class="medical-records-container_header"
        style="display: flex; align-items: center; justify-content: space-between"
      >
        <Title :title="'医师申诉'" style="margin-top: 8px" />
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

      <!-- 查询条件区域 -->
      <div class="medical-records-container_header">
        <el-form :model="searchData" class="demo-form-inline" label-width="90px">
          <!-- 第一行：基础核心条件（默认显示） -->
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
              <el-form-item label="院区">
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
            </el-col>
          </el-row>
          <el-row :gutter="24">
            <!-- 第四行：申诉相关条件 -->
            <el-col :span="6">
              <el-form-item label="申诉医师">
                <el-select
                  v-model="searchData.appeal_doctor"
                  multiple
                  filterable
                  clearable
                  collapse-tags
                  placeholder="请选择"
                  style="width: 100%"
                >
                  <el-option
                    v-for="(item, index) in searchOptions.appealDoctorArray"
                    :key="index"
                    :label="item.name"
                    :value="item.id"
                  ></el-option>
                </el-select>
              </el-form-item>
            </el-col>
            <el-col :span="6">
              <el-form-item label="申诉问题">
                <el-select
                  v-model="searchData.defect_content"
                  multiple
                  filterable
                  clearable
                  collapse-tags
                  placeholder="请选择"
                  style="width: 100%"
                >
                  <el-option
                    v-for="(item, index) in searchOptions.defectContentArray"
                    :key="index"
                    :label="item"
                    :value="item"
                  ></el-option>
                </el-select>
              </el-form-item>
            </el-col>
            <el-col :span="6">
              <el-form-item label="审核医师">
                <el-select
                  v-model="searchData.case_doctor"
                  multiple
                  filterable
                  clearable
                  collapse-tags
                  placeholder="请选择"
                  style="width: 100%"
                >
                  <el-option
                    v-for="(item, index) in searchOptions.caseDoctorArray"
                    :key="index"
                    :label="item.name"
                    :value="item.id"
                  ></el-option>
                </el-select>
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
          </el-row>

          <el-row :gutter="24">
            <el-col :span="6">
              <el-form-item label="审核状态">
                <el-select
                  v-model="searchData.appeal_status"
                  filterable
                  clearable
                  placeholder="请选择"
                  style="width: 100%"
                >
                  <el-option label="待审核" value="0"></el-option>
                  <el-option label="已通过" value="1"></el-option>
                  <el-option label="未通过" value="2"></el-option>
                  <el-option label="已整改" value="3"></el-option>
                </el-select>
              </el-form-item>
            </el-col>
            <el-col :span="6">
              <el-form-item label="质控类型">
                <el-select
                  v-model="searchData.quality_type"
                  multiple
                  filterable
                  clearable
                  collapse-tags
                  placeholder="请选择"
                  style="width: 100%"
                >
                  <el-option label="运行病历" value="运行病历"></el-option>
                  <el-option label="运行首页" value="运行首页"></el-option>
                  <el-option label="编码首页" value="编码首页"></el-option>
                </el-select>
              </el-form-item>
            </el-col>
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
          </el-row>

          <el-row :gutter="24" v-show="expand">
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
            <el-col :span="8">
              <DateRangePicker
                v-model="searchData"
                labelText="申诉时间"
                startKey="appeal_time_start"
                endKey="appeal_time_end"
                startPlaceholder="申诉开始时间"
                endPlaceholder="申诉结束时间"
              />
            </el-col>
            <el-col :span="8">
              <DateRangePicker
                v-model="searchData"
                labelText="审核时间"
                startKey="examine_time_start"
                endKey="examine_time_end"
                startPlaceholder="审核开始时间"
                endPlaceholder="审核结束时间"
              />
            </el-col>
          </el-row>

          <!-- 第二行：日期+科室条件（默认显示） -->
          <el-row :gutter="24" v-show="expand">
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
          </el-row>

          <el-row :gutter="24" v-show="expand">
            <!-- 第六行：其他条件 -->

            <el-col :span="6">
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
            </el-col>
            <el-col :span="8">
              <el-form-item label="通过/驳回">
                <el-input
                  v-model="searchData.reject_content"
                  clearable
                  placeholder="请输入"
                  style="width: 100%"
                ></el-input>
              </el-form-item>
            </el-col>
          </el-row>

          <!-- 折叠/展开 + 操作按钮 -->
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

      <!-- 表格列表 -->
      <el-table
        :data="tableData"
        @sort-change="handleSortChange"
        style="width: 100%"
        :default-sort="{ prop: 'discharge_date', order: 'descending' }"
      >
        <el-table-column type="index" label="序号" align="center" width="80">
          <template slot-scope="scope">
            <span>{{ scope.$index + 1 + (paginationData.currentPage - 1) * paginationData.pageSize }}</span>
          </template>
        </el-table-column>
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
            <span v-if="scope.row.record_grade">
              {{ scope.row.record_grade }} |
              {{ scope.row.record_grade === '甲' ? 100 : scope.row.record_grade === '乙' ? 90 : 20 }}
            </span>
            <span v-else>--</span>
          </template>
        </el-table-column>
        <el-table-column label="事中得分" width="120">
          <template slot-scope="scope">
            <span>{{ scope.row.case_score || '--' }}分</span>
          </template>
        </el-table-column>
        <el-table-column label="运行首页得分" width="120">
          <template slot-scope="scope">
            <span>{{ scope.row.home_score || '--' }}分</span>
          </template>
        </el-table-column>
        <el-table-column label="入院日期" width="120">
          <template slot-scope="scope">
            <span>{{ scope.row.admission_date ? formatDate(scope.row.admission_date) : '--' }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="admission_department" label="入院科室" width="120"></el-table-column>
        <el-table-column prop="admission_ward" label="入院病区" width="120"></el-table-column>
        <el-table-column label="出院日期" width="120">
          <template slot-scope="scope">
            <span>{{ scope.row.discharge_date ? formatDate(scope.row.discharge_date) : '--' }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="discharge_department" label="出院科室" width="120"></el-table-column>
        <el-table-column prop="discharge_ward" label="出院病区" width="120"></el-table-column>
        <el-table-column prop="hospital_days" label="住院天数" width="100"></el-table-column>
        <el-table-column prop="admission_status" label="入院时情况" width="150"></el-table-column>
        <el-table-column label="出院诊断ICD" width="100">
          <template slot-scope="scope">
            <span>{{ scope.row.diagnosis_code ? scope.row.diagnosis_code.substring(0, 3) : '--' }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="diagnosis_name" label="出院诊断" width="150"></el-table-column>
        <el-table-column prop="discharge_status" label="出院情况" width="120"></el-table-column>
        <el-table-column prop="chief_physician" label="科主任" width="120"></el-table-column>
        <el-table-column prop="deputy_chief_physician" label="主副主任医师" width="120"></el-table-column>
        <el-table-column prop="attending_physician" label="主治医师" width="120"></el-table-column>
        <el-table-column prop="resident_physician" label="住院医师" width="120"></el-table-column>
        <el-table-column prop="coder" label="编码员" width="120"></el-table-column>
        <el-table-column prop="operation_code" label="手术编码" width="120"></el-table-column>
        <el-table-column prop="operation_name" label="手术名称" width="150"></el-table-column>
        <el-table-column label="手术日期" width="120">
          <template slot-scope="scope">
            <span>{{ scope.row.operation_date ? formatDateTime(scope.row.operation_date) : '--' }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="surgeon" label="术者" width="120"></el-table-column>
        <el-table-column prop="first_assistant" label="一助" width="120"></el-table-column>
        <el-table-column prop="second_assistant" label="二助" width="120"></el-table-column>
        <el-table-column prop="anesthesia_method" label="麻醉方式" width="120"></el-table-column>
        <el-table-column prop="wound_healing_grade" label="切口愈合等级" width="120"></el-table-column>
        <el-table-column prop="record_grade" label="病历等级" width="100"></el-table-column>
        <el-table-column prop="is_danfou" label="单否问题" width="100"></el-table-column>
        <el-table-column prop="patient_status" label="患者状态" width="100"></el-table-column>
        <el-table-column prop="catalog_status" label="是否编码" width="100"></el-table-column>
        <!-- 申诉相关字段 -->
        <el-table-column prop="appeal_status_name" label="审核状态" width="100"></el-table-column>
        <el-table-column prop="appeal_docter" label="申诉医师" width="120"></el-table-column>
        <el-table-column prop="defect_content" label="申诉问题" width="150"></el-table-column>
        <el-table-column label="申诉时间" width="180">
          <template slot-scope="scope">
            <span>{{ scope.row.appeal_time || '--' }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="case_docter" label="质控审核医师" width="150"></el-table-column>
        <el-table-column label="审核时间" width="180">
          <template slot-scope="scope">
            <span>{{ scope.row.examine_time || '--' }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="reject_content" label="通过/驳回原因" width="150"></el-table-column>
        <el-table-column prop="quality_type_name" label="质控类型" width="120"></el-table-column>
      </el-table>

      <!-- 分页组件 -->
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
import { appealCasesDrillDown } from '@/api/excel';
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
    console.log(query.resident_doctor);
    return {
      expand: false, // 默认折叠
      pageType: query.type || 'all',
      // 搜索条件 - 完整包含所有新增字段
      searchData: {
        // 基础字段
        case_number: '', // 病案号
        campus: [], // 院区
        record_levels: [], // 病历等级
        patient_status: '全部', // 患者状态
        catalog_status: '全部', // 是否编码
        is_danfou: '全部', // 单否问题

        // 日期字段
        discharge_date_start: query.startTime || '', // 出院开始日期（上级带入）
        discharge_date_end: query.endTime || '', // 出院结束日期（上级带入）
        admission_date_start: '', // 入院开始日期
        admission_date_end: '', // 入院结束日期
        appeal_time_start: '', // 申诉开始时间
        appeal_time_end: '', // 申诉结束时间
        examine_time_start: '', // 审核开始时间
        examine_time_end: '', // 审核结束时间

        // 科室/病区字段
        discharge_departments: query.discharge_departments ? (Array.isArray(query.discharge_departments) ? query.discharge_departments : [query.discharge_departments]) : [], // 出院科室（上级带入）
        discharge_wards: [], // 出院病区
        admission_departments: query.dep_id || [], // 入院科室
        admission_wards: [], // 入院病区

        // 申诉/审核相关字段
        appeal_status: query.appeal_status || '', // 审核状态
        quality_type: [], // 质控类型
        appeal_doctor: [], // 申诉医师
        defect_content: [], // 申诉问题
        case_doctor: [], // 质控审核医师
        reject_content: '', // 通过/驳回原因

        // 排序字段
        order: '',
        order_sort: '',
      },

      // 列表数据
      tableData: [],

      // 分页数据
      paginationData: {
        total: 0,
        currentPage: 1,
        pageSize: 10,
      },

      // 下拉选项配置
      searchOptions: {
        yqArray: [], // 院区
        ksArray: [], // 科室
        bqArray: [], // 病区
        appealDoctorArray: [], // 申诉医师
        defectContentArray: [], // 申诉问题
        caseDoctorArray: [], // 质控审核医师
        cascaderProps: {
          multiple: true,
          label: 'dep_name',
          value: 'dep_id',
          children: 'children',
          checkStrictly: true,
          emitPath: false,
        },
      },
    };
  },
  watch: {
    $route(to, from) {
      if (to.query) {
        // 从路由参数带入出院相关条件
        if (to.query.discharge_date_start) this.searchData.discharge_date_start = to.query.discharge_date_start;
        if (to.query.discharge_date_end) this.searchData.discharge_date_end = to.query.discharge_date_end;
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
    // 格式化日期（yyyyMMdd → yyyy 年 MM 月 dd 日）
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

    // 格式化时间（包含时分秒）
    formatDateTime(dateStr) {
      if (!dateStr) return '--';
      return moment(dateStr).format('YYYY 年 MM 月 DD 日 HH:mm:ss');
    },

    // 院区变更事件
    yqChange() {
      // 重置科室/病区选择
      this.searchData.discharge_departments = [];
      this.searchData.discharge_wards = [];
      this.searchData.admission_departments = [];
      this.searchData.admission_wards = [];

      this.$axios.post('CaseHistory/Terminal/getKsOptions', { YQ_CODE: this.searchData.campus }).then(res => {
        this.searchOptions.ksArray = this.cancelChildren(res.data.ksArray);
        this.searchOptions.bqArray = this.cancelChildren(res.data.bqArray);
      });
    },

    // 科室变更事件
    ksChange(type) {
      if (type === 'in') {
        this.searchData.admission_wards = [];
        this.$axios.post('CaseHistory/Terminal/getBqOptions', { KS_CODE: this.searchData.admission_departments }).then(res => {
          this.searchOptions.bqArray = this.cancelChildren(res.data.bqArray);
        });
      } else if (type === 'out') {
        this.searchData.discharge_wards = [];
        this.$axios.post('CaseHistory/Terminal/getBqOptions', { KS_CODE: this.searchData.discharge_departments }).then(res => {
          this.searchOptions.bqArray = this.cancelChildren(res.data.bqArray);
        });
      }
    },

    // 表格排序事件
    handleSortChange(column) {
      const { prop, order } = column;
      if (!prop) return;

      this.searchData.order = prop;
      this.searchData.order_sort = order === 'descending' ? 'desc' : 'asc';
      this.getList();
    },

    // 获取下拉选项
    getSearchOptions() {
      this.$axios
        .post('CaseHistory/Terminal/getQxBlSearchOptions', {})
        .then(res => {
          this.searchOptions.yqArray = res.data.yqArray;
          this.searchOptions.ksArray = this.cancelChildren(res.data.ksArray);
          this.searchOptions.bqArray = this.cancelChildren(res.data.bqArray);
          this.searchOptions.appealDoctorArray = res.data.appealDoctorArray || []; // 申诉医师
          this.searchOptions.defectContentArray = res.data.defectContentArray || []; // 申诉问题
          this.searchOptions.caseDoctorArray = res.data.caseDoctorArray || []; // 质控审核医师
        })
        .catch(err => {
          console.error('获取搜索选项失败:', err);
        });
    },

    // 清理children属性（保持原有逻辑）
    cancelChildren(arr) {
      if (!arr) return [];
      return arr;
    },

    // 获取列表数据
    getList() {
      const params = {
        page: this.paginationData.currentPage,
        limit: this.paginationData.pageSize,
        ...this.searchData,
      };

      // 清理空参数
      //   Object.keys(params).forEach(key => {
      //     if (params[key] === '' || params[key] === undefined || (Array.isArray(params[key]) && params[key].length === 0)) {
      //       delete params[key];
      //     }
      //     if (params[key] === '全部') {
      //       delete params[key];
      //     }
      //   });

      //   // 处理时间参数（补全时分秒）
      //   const timeFields = ['appeal_time_start', 'appeal_time_end', 'examine_time_start', 'examine_time_end'];
      //   timeFields.forEach(field => {
      //     if (params[field] && params[field].length === 8) {
      //       params[field] = `${params[field]} 00:00:00`;
      //     }
      //   });

      this.$axios
        .post('/quality_report/quality_report_drill/defectCasesDrillDown', params)
        .then(res => {
          this.tableData = res.data?.list || [];
          this.paginationData.total = res.data?.total || 0;
          console.log('表格数据:', this.tableData);
        })
        .catch(err => {
          console.error('获取病历列表失败:', err);
          this.tableData = [];
          this.paginationData.total = 0;
        });
    },

    // 返回上一页
    toBack() {
      this.$router.back();
    },

    // 重置查询条件
    reset() {
      const { query } = this.$route;
      this.searchData = {
        case_number: '',
        campus: [],
        record_levels: [],
        patient_status: '全部',
        catalog_status: '全部',
        is_danfou: '全部',
        discharge_date_start: query.discharge_date_start || '',
        discharge_date_end: query.discharge_date_end || '',
        discharge_departments: query.discharge_departments ? (Array.isArray(query.discharge_departments) ? query.discharge_departments : [query.discharge_departments]) : [],
        discharge_wards: [],
        admission_date_start: '',
        admission_date_end: '',
        admission_departments: [],
        admission_wards: [],
        appeal_status: '',
        quality_type: [],
        appeal_doctor: [],
        defect_content: [],
        appeal_time_start: '',
        appeal_time_end: '',
        case_doctor: [],
        examine_time_start: '',
        examine_time_end: '',
        reject_content: '',
        order: '',
        order_sort: '',
      };
      this.paginationData.currentPage = 1;
      this.expand = false; // 重置后默认折叠
    },

    // 查询事件
    onSearch() {
      this.paginationData.currentPage = 1;
      this.getList();
    },

    // 导出数据
    onExport() {
      const params = {
        ...this.searchData,
        is_export: 1,
      };

      // 清理导出参数
      Object.keys(params).forEach(key => {
        if (params[key] === '' || params[key] === undefined || (Array.isArray(params[key]) && params[key].length === 0)) {
          delete params[key];
        }
        if (params[key] === '全部') {
          delete params[key];
        }
      });

      appealCasesDrillDown(params)
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
          messageOnce.error('导出失败，请重试');
        });
    },

    // 分页大小变更
    SizeChangeEvent(val) {
      this.paginationData.pageSize = val;
      this.getList();
    },

    // 页码变更
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