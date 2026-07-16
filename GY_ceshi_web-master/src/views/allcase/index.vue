<template>
  <div class="pages">
    <div class="bg-card" style="margin-bottom: 16px">
      <el-form :model="formData" label-width="80px" ref="filterFormRef">
        <el-row>
          <el-col :span="7">
            <el-form-item label="所属院区" prop="YQ_CODE">
              <el-select
                style="width: 94%"
                placeholder="请选择所属院区"
                v-model="formData.YQ_CODE"
                multiple
                collapse-tags
                clearable
                filterable
                @change="yqChange"
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
            <el-form-item label="出院科室" prop="KS_CODE">
              <el-cascader
                style="width: 94%"
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

          <el-col :span="6">
            <el-form-item label="出院病区" prop="BQ_CODE">
              <el-cascader
                style="width: 94%"
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
              <el-input style="width: 94%" placeholder="请输入病案编号" v-model="formData.AAA28" clearable></el-input>
            </el-form-item>
          </el-col>
        </el-row>
        <el-row>
          <el-col :span="7">
            <el-form-item label="出院日期">
              <div style="width: 94%; display: flex; gap: 5px">
                <el-form-item prop="startTime">
                  <el-date-picker
                    style="width: 100%"
                    v-model="formData.startTime"
                    type="date"
                    placeholder="出院开始日期"
                    :picker-options="pickerOptions"
                    value-format="yyyyMMdd"
                    format="yyyy年MM月dd日"
                  ></el-date-picker>
                </el-form-item>
                <el-form-item prop="endTime">
                  <!-- <el-col :span="11">
                <div style="width: 100%;"/>
                  </el-col>-->
                  <el-date-picker
                    style="width: 100%"
                    v-model="formData.endTime"
                    type="date"
                    placeholder="出院结束日期"
                    :picker-options="[]"
                    value-format="yyyyMMdd"
                    format="yyyy年MM月dd日"
                    @onClick="onClickEndTime"
                  ></el-date-picker>
                </el-form-item>
              </div>
            </el-form-item>
          </el-col>
          <el-col :span="5" :offset="12">
            <el-form-item>
              <div style="width: 94%; display: flex; justify-content: flex-end">
                <el-button class="btn1" type="primary" @click="funQuery">查询</el-button>
                <el-button @click="reset">重置</el-button>
              </div>
            </el-form-item>
          </el-col>
        </el-row>
      </el-form>
    </div>

    <!-- 统计汇总 -->
    <div class="bg-card">
      <CardTitle title="统计汇总">
        <el-image
          class="title_arrow"
          :class="{ arrow_top: !summaryStatus }"
          :src="require('../../assets/images/arrow-down.png')"
          fit="contain"
          @click="summaryStatus = !summaryStatus"
        ></el-image>
      </CardTitle>

      <el-collapse-transition>
        <div
          v-show="summaryStatus"
          style="display: flex; justify-content: center; margin-top: 20px"
        >
          <el-row style="width: 100%" :gutter="20" class="input-box">
            <el-col :span="5">
              <el-card class="summary-card" shadow="hover">
                <div slot="header" class="clearfix">
                  <span class="summary-card-title">质控病历</span>
                  <el-button
                    style="float: right; padding: 0"
                    type="text"
                    @click="urlGoto('/caseNumber?', '')"
                  >
                    <i class="el-icon-s-marketing summary-icon"></i>
                  </el-button>
                </div>

                <div class="summary-card-body">
                  <a
                    @click="urlGoto('/caseNumber?', '')"
                    class="summary-content-text"
                    type="text"
                  >{{ summaryData.case_total }}</a>
                  <span>例</span>
                </div>
              </el-card>
            </el-col>

            <el-col :span="5">
              <el-card class="summary-card" shadow="hover">
                <div slot="header" class="clearfix">
                  <span class="summary-card-title">缺陷病历</span>
                  <el-button
                    style="float: right; padding: 0"
                    type="text"
                    @click="urlGoto('/caseNumber?', 'is_qx=1')"
                  >
                    <i class="el-icon-s-marketing summary-icon"></i>
                  </el-button>
                </div>
                <div class="summary-card-body">
                  <a
                    @click="urlGoto('/caseNumber?', 'is_qx=1')"
                    class="summary-content-text"
                    type="text"
                  >{{ summaryData.case_quality_total }}</a>
                  <span>例</span>
                </div>
              </el-card>
            </el-col>

            <el-col :span="5">
              <el-card class="summary-card" shadow="hover">
                <div slot="header" class="clearfix">
                  <span class="summary-card-title">缺陷占比</span>
                  <el-button style="float: right; padding: 0" type="text">
                    <i class="el-icon-s-marketing summary-icon"></i>
                  </el-button>
                </div>
                <div class="summary-card-body">
                  <a
                    @click="javascript()"
                    class="summary-content-text1"
                    type="text"
                  >{{ summaryData.quality_proportion }}</a>
                  <span>%</span>
                </div>
              </el-card>
            </el-col>

            <el-col :span="5">
              <el-card class="summary-card" shadow="hover">
                <div slot="header" class="clearfix">
                  <span class="summary-card-title">病案质量</span>
                  <el-button style="float: right; padding: 0" type="text">
                    <i class="el-icon-s-marketing summary-icon"></i>
                  </el-button>
                </div>
                <div style="height: 120px" id="quality-chart"></div>
              </el-card>
            </el-col>

            <el-col :span="5">
              <el-card class="summary-card" shadow="hover">
                <div slot="header" class="clearfix">
                  <span class="summary-card-title">缺陷数量</span>
                  <el-button
                    style="float: right; padding: 0"
                    type="text"
                    @click="urlGoto('/defectNumber?', 'from=ZMBLZK_WTSL')"
                  >
                    <i class="el-icon-s-marketing summary-icon"></i>
                  </el-button>
                </div>
                <div class="summary-card-body">
                  <a
                    @click="urlGoto('/defectNumber?', 'from=ZMBLZK_WTSL')"
                    class="summary-content-text"
                    type="text"
                  >{{ summaryData.question_total }}</a>
                  <span>个</span>
                </div>
              </el-card>
            </el-col>
          </el-row>
        </div>
      </el-collapse-transition>
    </div>

    <!-- 缺陷问题 -->
    <div class="bg-card" style="margin-bottom: 24px">
      <!-- <Title :title="'缺陷问题'" /> -->
      <div style="margin-bottom: 20px">
        <CardTitle title="缺陷问题">
          <el-image
            class="title_arrow"
            :class="{ arrow_top: !quxian_show }"
            :src="require('../../assets/images/arrow-down.png')"
            fit="contain"
            @click="onToggleQuexianShow"
          ></el-image>
        </CardTitle>
      </div>
      <el-collapse-transition>
        <div v-show="quxian_show">
          <el-form
            :inline="true"
            ref="caseSearchDataRef"
            :model="caseSearchData"
            class="demo-form-inline"
          >
            <el-form-item label="规则类型" prop="type">
              <el-select
                v-model="caseSearchData.type"
                clearable
                filterable
                placeholder="请选择规则类型"
                style="width: 100%"
                @change="handleRuleCaseTitle"
              >
                <el-option
                  v-for="(item, index) in searchOptions.ruleTypeArray"
                  :label="item.name"
                  :value="item.name"
                  :key="index"
                ></el-option>
              </el-select>
            </el-form-item>
            <el-form-item label="病历目录" prop="case_title">
              <el-select
                v-model="caseSearchData.case_title"
                clearable
                multiple
                filterable
                collapse-tags
                placeholder="请选择病历目录"
                style="width: 100%"
                @change="handleCaseTitle"
              >
                <el-option
                  v-for="(item, key, index) in searchOptions.wtTitleArray"
                  :label="item.title"
                  :value="item.title"
                  :key="item.id"
                ></el-option>
              </el-select>
            </el-form-item>
            <el-form-item label="缺陷描述" prop="case_notice">
              <el-select
                v-model="caseSearchData.case_notice"
                clearable
                multiple
                filterable
                collapse-tags
                placeholder="请选择问题描述"
                style="width: 450px"
                @change="handleCaseNotice"
                :filter-method="handleFilterNotice"
              >
                <el-option
                  v-for="(item, index) in filteredWtDataArray"
                  :label="item.notice"
                  :value="item.notice"
                  :key="item.id"
                ></el-option>
              </el-select>
            </el-form-item>
            <el-form-item>
              <el-button type="primary" @click="getCaseList">查询</el-button>
              <el-button @click="resetWt">重置</el-button>
            </el-form-item>
            <el-form-item style="float: right">
              <el-button type="primary" @click="exportCaseList">导出</el-button>
            </el-form-item>
          </el-form>
          <ProblemTableBoxVue
            :data="caseList"
            from="ZMBLZK"
            @onGotoPage="e => urlGoto('/defectNumber?', `rule_id=${e.key}&rule_type=${caseSearchData.type}&from=ZMBLZK_WTSL`)"
          />
          <mPagination
            v-if="caseList && caseList.length !== 0"
            layout="sizes, prev, pager, next, slot"
            :data="paginationDataNotice"
            @pageChangeEvent="pageHasChanged"
            @sizeChange="handleSizeChange"
          ></mPagination>
        </div>
      </el-collapse-transition>
    </div>

    <!-- 科室排名 -->
    <div class="chart">
      <CardTitle :title="rankName + '排名'">
        <el-image
          class="title_arrow"
          :class="{ arrow_top: !quxian_show }"
          :src="require('../../assets/images/arrow-down.png')"
          fit="contain"
          @click="departmentRankStatus = !departmentRankStatus"
        ></el-image>
      </CardTitle>
      <el-collapse-transition>
        <div
          v-show="departmentRankStatus"
          style="display: flex; justify-content: center; margin-top: 20px"
        >
          <div style="width: 60%; margin-right: 20px">
            <div style="display: flex" class="mb20">
              <div style="display: flex; align-items: center">
                <el-select
                  v-model="departmentSearch.rank_type"
                  @change="depRankChange"
                  style="width: 120px"
                >
                  <el-option
                    v-for="(v, k) in departmentOptions.rankTypeArray"
                    :key="k"
                    :label="v.name"
                    :value="v.type_id"
                  ></el-option>
                </el-select>
                <div style="margin-left: 10px; font-size: 16px">
                  共
                  <span style="color: #296bcfff; font-size: 16px">{{ departmentSearch.total }}</span>
                  个{{ rankName }}
                </div>
                <el-pagination
                  @size-change="handleDepartmentSizeChange"
                  @current-change="handleDepartmentCurrentChange"
                  :current-page="departmentSearch.page"
                  :page-sizes="[10, 20, 30, 50, 100]"
                  :page-size="departmentSearch.size"
                  layout=" prev, pager, next,sizes"
                  :total="departmentSearch.total"
                  style="margin-left: 10px"
                ></el-pagination>
              </div>
              <div style="margin-left: auto">
                <el-button
                  @click="rankOrder"
                  :icon="departmentSearch.rank_order == 2 ? 'el-icon-bottom' : 'el-icon-top'"
                >{{ rankOrderName }}</el-button>
                <el-button icon="el-icon-download" @click="departmentRankExport">下载</el-button>
              </div>
            </div>
            <el-table :data="departmentOptions.tableList">
              <el-table-column
                v-for="(item, index) in rankTableFiled"
                :index="index"
                :prop="item.prop"
                :label="item.label"
                align="center"
                show-overflow-tooltip
              >
                <template slot-scope="scope">
                  <span
                    class="link"
                    @click="urlGoto('/caseNumber?', `${getCurrentRankType()}=${scope.row.code}`)"
                    style="color: #004983"
                    v-if="item.prop == 'bl_num'"
                  >{{ scope.row.bl_num }}</span>
                  <span
                    v-else-if="item.prop == 'qx_num'"
                    class="link"
                    @click="urlGoto('/caseNumber?', `${getCurrentRankType()}=${scope.row.code}&is_qx=1`)"
                    style="color: red"
                  >{{ scope.row[item.prop] }}</span>
                  <span
                    v-else-if="item.prop == 'jia_num' || item.prop == 'yi_num' || item.prop == 'bing_num'"
                    class="link"
                  >{{ scope.row[item.prop] }}</span>
                  <span v-else>{{ scope.row[item.prop] }}</span>
                </template>
              </el-table-column>
            </el-table>
          </div>
          <!-- <div id="myChart1" style="width: 40%;margin-top: 10px;" :style="'height:' + rankChartHeight + 'px'"></div> -->
          <div id="myChart1" style="width: 40%; margin-top: 10px; height: 200px"></div>
        </div>
      </el-collapse-transition>
    </div>

    <!-- 医师排名 -->
    <div class="bg-card" style="margin-bottom: 24px">
      <div
        style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px"
      >
        <CardTitle title="医师排名">
          <el-image
            class="title_arrow"
            :class="{ arrow_top: !doctor_show }"
            :src="require('../../assets/images/arrow-down.png')"
            fit="contain"
            @click="onToggleDoctorShow"
          ></el-image>
        </CardTitle>
        <div class="xz-btn" @click.stop="handleExport">下载</div>
      </div>

      <el-collapse-transition>
        <div v-show="doctor_show">
          <el-row :gutter="20">
            <el-col :span="13">
              <el-table :data="doctor_tableData" class="mb20" style="width: 100%">
                <el-table-column type="index" label="序号" width="80" align="center"></el-table-column>
                <el-table-column prop="dep_name" label="科室" align="center"></el-table-column>
                <el-table-column prop="key" label="医师姓名" align="center" show-overflow-tooltip></el-table-column>
                <el-table-column prop="code" label="工号" align="center"></el-table-column>
                <el-table-column prop="doc_count" label="病历总数" align="center">
                  <template slot-scope="scope">
                    <!-- <span class="link" @click="goto('/caseNumber?doctor_name='+ scope.row.key +'&sort=doc_count')" style="color: #004983;">{{ scope.row.doc_count }}例</span> -->
                    <span
                      class="link"
                      @click="
                        goto(
                          `/doctor/bl?doctor_code=${scope.row.code}&doctor_name=${scope.row.key}&start_time=${formData.startTime}&end_time=${formData.endTime}&AAA28=${formData.AAA28}`,
                        )
                      "
                      style="color: #004983"
                    >{{ scope.row.doc_count }}例</span>
                  </template>
                </el-table-column>
                <el-table-column prop="score" label="总扣分" align="center">
                  <template slot-scope="scope">
                    <span
                      class="link"
                      @click="toPageDoctor(scope.row, 'score')"
                      style="color: #004983"
                    >-{{ scope.row.score }}分</span>
                  </template>
                </el-table-column>
                <el-table-column prop="avg_score" label="平均分" align="center">
                  <template slot-scope="scope">
                    <span>{{ scope.row.avg_score }}分</span>
                  </template>
                </el-table-column>
              </el-table>
              <el-pagination
                background
                @size-change="handleDoctorSizeChange"
                @current-change="handleDoctorCurrentChange"
                :current-page="paginationDataDoctor.page"
                :page-size="paginationDataDoctor.size"
                layout="total, prev, pager, next, jumper"
                :total="paginationDataDoctor.total"
              ></el-pagination>
            </el-col>
            <el-col :span="11">
              <DoctorRankVue v-if="doctor_rank.length" :data="processedData" />
            </el-col>
          </el-row>
        </div>
      </el-collapse-transition>
    </div>
  </div>
</template>

<script>
import * as echarts from 'echarts';
import { mapGetters } from 'vuex';
import Title from '@/components/Title';
import ProblemTableBoxVue from './components/ProblemTableBox.vue';
import MedicalRecordTableBoxVue from './components/MedicalRecordTableBox.vue';
import { getDepartmentRankExport, medicalRecordDoctorExport, exportDefectIssues } from '@/api/excel';
import DoctorRankVue from './components/DoctorRank.vue';
import moment from 'moment/moment';
import mPagination from '@/components/m-pagination';

export default {
  components: {
    Title,
    ProblemTableBoxVue,
    MedicalRecordTableBoxVue,
    DoctorRankVue,
    mPagination,
  },
  name: 'Dashboard',
  data() {
    const that = this;
    return {
      //region 顶部搜索
      // search: { year: 0, quarter: 0 },
      searchOptions: {
        yqArray: [], //院区options
        ksArray: [], //科室options
        bqArray: [], //病区options
        ruleTypeArray: [], // 缺陷问题-规则类型
        wtArray: [], // 缺陷问题-缺陷描述
        wtDataArray: [], // 缺陷问题-缺陷描述-带类目
        wtTitleArray: [], // 缺陷问题-病历目录
        cascaderProps: {
          multiple: true, // 开启多选模式
          label: 'dep_name',
          value: 'dep_id',
          children: 'children',
          checkStrictly: true, // 允许独立选择任意层级
          emitPath: false, // 是否返回完整路径（true 返回路径数组，false 只返回末节点值）
        },
      },
      lastSearchQuery: '', //上一次搜索条件
      filteredWtDataArray: [], // 缺陷问题-缺陷描述-带类目-过滤后的
      //endregion
      //region 科室排名
      departmentSearch: { rank_type: 2, rank_order: 1, page: 1, size: 10, total: 0 }, //科室排名search条件
      departmentOptions: {
        tableField: [
          //列表表头
          { prop: 'rank', label: '排名' },
          { prop: 'dep_name', label: '出院科室名称' },
          { prop: 'bl_num', label: '质控病历（例）' },
          { prop: 'qx_num', label: '问题病历（例）' },
          { prop: 'qx_ratio', label: '缺陷占比' },
          { prop: 'jia_num', label: '甲级病例（例）' },
          { prop: 'yi_num', label: '乙级病历（例）' },
          { prop: 'bing_num', label: '丙级病历（例）' },
        ],
        tableList: [{}], //列表数据
        rankTypeArray: [
          //排名类型
          { type_id: 1, name: '院区维度', filedName: '出院院区名称', rankTitle: '院区', filed: 'YQ_CODE' },
          { type_id: 2, name: '科室维度', filedName: '出院科室名称', rankTitle: '科室', filed: 'KS_CODE' },
          { type_id: 3, name: '病区维度', filedName: '出院病区名称', rankTitle: '病区', filed: 'BQ_CODE' },
        ],
        rankOrderArray: [
          //排序
          { id: 1, name: '升序' },
          { id: 2, name: '降序' },
        ],
      },
      summaryStatus: true, //  统计汇总是否显示
      departmentRankStatus: true, //是否显示，默认为显示
      departmentMyChart: {
        //科室排名图表
        tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' } },
        legend: {},
        grid: { left: '3%', right: '4%', bottom: '3%', containLabel: true },
        xAxis: { type: 'value', boundaryGap: [0, 0.01] },
        yAxis: { type: 'category', data: [] },
        series: [
          { name: '质控病例', type: 'bar', data: [] },
          { name: '缺陷病例', type: 'bar', data: [], itemStyle: { color: 'red' } },
        ],
      },

      //endregion
      doctor_rank: [],
      formData: {
        YQ_CODE: [],
        KS_CODE: [],
        BQ_CODE: [],
        AAA28: '',
        startTime: moment().startOf('month').format('YYYYMMDD'),
        endTime: moment().format('YYYYMMDD'),
      },
      // homeData: {},
      // quarterList: [],
      // monthList: [],
      // yearList: [],
      // countsData: {
      //   case_total: 0,
      //   defect_case_total: 0,
      // },
      summaryData: {
        case_total: 0, //  病案数量
        case_quality_total: 0, //  缺陷病历数量
        quality_proportion: '2%', //  缺陷占比
        question_total: 1, //  问题数量
        case_calibre: {}, //  病案质量
      },
      caseSearchData: {
        type: '',
        case_title: [],
        case_notice: [],
      },
      caseList: [], // 缺陷问题
      // departmentList: [],
      // doctorList: [], // 医生列表
      // doctor_name: '',
      quxian_show: true,
      // 医师排名
      doctor_show: true,
      doctor_tableData: [],
      // 分页数据
      paginationDataDoctor: {
        page: 1,
        size: 10,
        total: 0,
      },
      paginationDataNotice: {
        currentPage: 1,
        pageSize: 10,
        total: 0,
      },
      wtTitleArrayTemp: [],
      wtDataArrayTemp: [],
      pickerOptions: {
        disabledDate: time => {
          if (this.formData.endTime != '') {
            return time.getTime() > Date.now();
          }
        },
        shortcuts: [
          {
            text: '今天',
            onClick(picker) {
              picker.$emit('pick', moment().format('YYYYMMDD'));
              that.formData.endTime = moment().format('YYYYMMDD');
            },
          },
          {
            text: '近7天',
            onClick(picker) {
              picker.$emit('pick', moment().subtract(7, 'days').format('YYYYMMDD'));
              that.formData.endTime = moment().format('YYYYMMDD');
            },
          },
          {
            text: '近30天',
            onClick(picker) {
              picker.$emit('pick', moment().subtract(30, 'days').format('YYYYMMDD'));
              that.formData.endTime = moment().format('YYYYMMDD');
            },
          },
          {
            text: '一季度',
            onClick(picker) {
              picker.$emit('pick', moment().startOf('year').format('YYYYMMDD'));
              that.formData.endTime = moment().startOf('year').add(3, 'M').subtract(1, 'days').format('YYYYMMDD');
            },
          },
          {
            text: '二季度',
            onClick(picker) {
              picker.$emit('pick', moment().startOf('year').add(3, 'M').format('YYYYMMDD'));
              that.formData.endTime = moment().startOf('year').add(6, 'M').subtract(1, 'days').format('YYYYMMDD');
            },
          },
          {
            text: '三季度',
            onClick(picker) {
              picker.$emit('pick', moment().startOf('year').add(6, 'M').format('YYYYMMDD'));
              that.formData.endTime = moment().startOf('year').add(9, 'M').subtract(1, 'days').format('YYYYMMDD');
            },
          },
          {
            text: '四季度',
            onClick(picker) {
              picker.$emit('pick', moment().startOf('year').add(9, 'M').format('YYYYMMDD'));
              that.formData.endTime = moment().startOf('year').add(12, 'M').subtract(1, 'days').format('YYYYMMDD');
            },
          },
          {
            text: moment().add(-2, 'Y').format('YYYY'),
            onClick(picker) {
              picker.$emit('pick', moment().add(-2, 'Y').startOf('year').format('YYYYMMDD'));
              that.formData.endTime = moment().add(-2, 'Y').endOf('year').format('YYYYMMDD');
            },
          },
          {
            text: moment().add(-1, 'Y').format('YYYY'),
            onClick(picker) {
              picker.$emit('pick', moment().add(-1, 'Y').startOf('year').format('YYYYMMDD'));
              that.formData.endTime = moment().add(-1, 'Y').endOf('year').format('YYYYMMDD');
            },
          },
          {
            text: moment().format('YYYY'),
            onClick(picker) {
              picker.$emit('pick', moment().startOf('year').format('YYYYMMDD'));
              that.formData.endTime = moment().endOf('year').format('YYYYMMDD');
            },
          },
        ],
      },
    };
  },
  computed: {
    ...mapGetters(['name']),
    processedData() {
      // 在计算属性中进行数据处理，返回处理后的结果
      return this.makeDoctorRank(this.doctor_rank);
    },
    //科室排名排序
    rankOrderName() {
      return this.departmentOptions.rankOrderArray.find(item => item.id === this.departmentSearch.rank_order).name;
    },
    //计算当前排名的所属维度
    rankName() {
      return this.departmentOptions.rankTypeArray.find(item => item.type_id === this.departmentSearch.rank_type).rankTitle;
    },
    //计算科室排名显示的出院科室名称还是出院院区名称
    rankTableFiled() {
      let filed = this.departmentOptions.rankTypeArray.find(item => item.type_id === this.departmentSearch.rank_type).filedName;
      this.departmentOptions.tableField[1]['label'] = filed;
      console.log('rankTableFiled', this.departmentOptions.tableField);
      return this.departmentOptions.tableField;
    },
    //计算科室排名图表高度
    // rankChartHeight() {
    //   //let height = this.departmentOptions.tableList.length * 48 + 100;
    //   return this.departmentOptions.tableList.length * 48 + 100;
    // },
    rankGotFiled() {
      let xx = this.departmentOptions.rankTypeArray.find(item => item.type_id === this.departmentSearch.rank_type).filed;
      return this.departmentOptions.rankTypeArray.find(item => item.type_id === this.departmentSearch.rank_type).filed;
    },
  },
  mounted() {
    this.getSearchOptions();
    this.doctor_rank = JSON.parse(JSON.stringify(this.doctor_tableData)).slice(0, 10);

    /*
    this.storageSet('start_time', '');
    this.storageSet('end_time', '');
    this.formData.chooseDate = '30';
    if (this.storageGet('homeFrom')) {
      this.formData = this.storageGet('homeFrom');
      this.storageRemove('homeFrom');
    }
     */
    // this.getDepartmentList()
    this.funQuery();
    // this.selectInfo();
    // this.getDepartmentTableList();
  },
  beforeRouteEnter(to, from, next) {
    next(() => {
      // 回到原来的位置
      const position = JSON.parse(window.sessionStorage.getItem('position'));
      document.querySelector('.app-wrapper').scrollTop = position;
    });
  },
  beforeRouteLeave(to, from, next) {
    // 保存离开页面时的位置
    const position = document.querySelector('.app-wrapper').scrollTop;
    window.sessionStorage.setItem('position', JSON.stringify(position));
    next();
  },
  methods: {
    handleRuleCaseTitle() {
      this.searchOptions.wtTitleArray = [];
      this.searchOptions.wtDataArray = [];
      this.searchOptions.wtTitleArray = this.wtTitleArrayTemp.filter(item => item.type.includes(this.caseSearchData.type));
      if (this.caseSearchData.type == '') {
        this.searchOptions.wtTitleArray = this.wtTitleArrayTemp;
      }
      this.filteredWtDataArray = this.searchOptions.wtDataArray
    },
    handleCaseTitle() {
      this.searchOptions.wtDataArray = [];
      this.searchOptions.wtDataArray = this.wtDataArrayTemp.filter(item => this.caseSearchData.case_title.includes(item.category));
      if (this.caseSearchData.case_title.length == 0) {
        this.searchOptions.wtDataArray = this.wtDataArrayTemp;
      }
      this.filteredWtDataArray = this.searchOptions.wtDataArray
    },
    handleCaseNotice() {
      if (!this.caseSearchData.case_notice || this.caseSearchData.case_notice.length === 0) {
        this.lastSearchQuery = '';
        this.filteredWtDataArray = this.searchOptions.wtDataArray || [];
      }
    },
    getCurrentRankType() {
      return this.departmentSearch.rank_type == 1 ? 'YQ_CODE' : this.departmentSearch.rank_type == 2 ? 'KS_CODE' : this.departmentSearch.rank_type == 3 ? 'BQ_CODE' : '';
    },
    pageHasChanged() {
      this.getCaseList();
    },
    handleSizeChange(size) {
      this.paginationDataNotice.currentPage = 1;
      this.paginationDataNotice.pageSize = size;
      this.getCaseList();
    },
    //下砖页面跳转
    urlGoto(url, value) {
      let urlString = url;
      Object.entries(this.formData).forEach(([key, value]) => {
        urlString = urlString + '&' + key + '=' + value;
      });
      urlString = urlString + '&' + value;
      this.goto(urlString);
    },
    //重置头部搜索条件
    reset() {
      this.$refs.filterFormRef.resetFields();
    },
    //重置缺陷问题查询条件
    resetWt() {
      this.$refs.caseSearchDataRef.resetFields();
      this.getSearchOptions();
      this.lastSearchQuery = ''; // 重置搜索词
      this.paginationDataNotice.currentPage = 1;
      this.getCaseList();
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
      // this.$axios.post('CaseHistory/Terminal/getBqOptions', { 'KS_CODE': this.formData.KS_CODE }).then(res => {
      //   // this.searchOptions.bqArray = this.cancelChildren(res.data.bqArray);//病区

      // })
      this.searchOptions.bqArray = this.searchOptions.ksArray.filter(item => this.formData.KS_CODE.includes(item.YQ_CODE));
    },
    //获取头部搜索options
    getSearchOptions() {
      this.$axios.post('CaseHistory/Terminal/getSearchOptions', {}).then(res => {
        this.searchOptions.yqArray = res.data.yqArray; //院区
        this.searchOptions.ksArray = this.cancelChildren(res.data.ksArray); //科室
        this.searchOptions.bqArray = this.cancelChildren(res.data.bqArray); //病区
      });
      this.$axios.post('CaseHistory/Terminal/getQxBlSearchOptions', {}).then(res => {
        this.searchOptions.ruleTypeArray = res.data.ruleTypeArray;
        this.searchOptions.wtDataArray = res.data.wtDataArray || {};
        this.searchOptions.wtTitleArray = res.data.wtTitleArray;
        this.wtDataArrayTemp = Object.values(res.data.wtDataArray);
        this.wtTitleArrayTemp = res.data.wtTitleArray;
        this.searchOptions.wtArray = res.data.wtArray;

        this.filteredWtDataArray = this.wtDataArrayTemp; // 初始化筛选后的数据
      });
    },
    //  将下拉框为空的children属性设置为undefined
    cancelChildren(arr) {
      if (!arr) {
        return [];
      }
      return arr;
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
    },

    onClickEndTime(e) {
      console.log(e);
    },

    //region 科室排名===
    //下载
    blLink(url, value) {
      let rank_type = this.departmentSearch.rank_type;
      let filed = '';
      switch (rank_type) {
        case 1:
          filed = 'YQ_CODE';
          break;
        case 2:
          filed = 'KS_CODE';
          break;
        case 3:
          filed = 'BQ_CODE';
          break;
      }
      this.goto(url + filed + '=' + value);
    },
    departmentRankExport() {
      let params = Object.assign({}, this.departmentSearch, this.formData);
      getDepartmentRankExport(params).then(res => {
        const content = res.data; // 后台返回二进制数据
        const blob = new Blob([content]);
        const fileName = `科室排名.csv`;
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
    //排序
    rankOrder() {
      this.departmentSearch.rank_order = this.departmentSearch.rank_order === 1 ? 2 : 1;
      this.getDepartmentTableList();
    },
    //科室排名当前维度change事件
    depRankChange() {
      this.getDepartmentTableList();
    },
    handleDepartmentSizeChange(value) {
      this.departmentSearch.page = 1;
      this.departmentSearch.size = value;
      this.getDepartmentTableList();
    },
    //跳页
    handleDepartmentCurrentChange(value) {
      this.departmentSearch.page = value;
      this.getDepartmentTableList();
    },
    //获取科室排名列表数据
    getDepartmentTableList() {
      let params = Object.assign({}, this.departmentSearch, this.formData);
      this.$axios.post('CaseHistory/Terminal/getDepartmentTableList', params).then(res => {
        this.departmentOptions.tableList = res.data.data;

        this.departmentSearch.total = res.data.total;
        this.departmentMyChart.yAxis.data = res.data.chartData.name;
        this.departmentMyChart.series[0].data = res.data.chartData.bl_num;
        this.departmentMyChart.series[1].data = res.data.chartData.qx_num;
        this.getDepartmentMyChart();
      });
    },
    handleFilterNotice(query) {
      if (query !== undefined && query.trim() !== '') {
        this.lastSearchQuery = query.trim();
      }

      const searchKey = this.lastSearchQuery.toLowerCase();

      if (!searchKey) {
        this.filteredWtDataArray = this.wtDataArrayTemp || [];
        return;
      }

      this.filteredWtDataArray = (this.wtDataArrayTemp || []).filter(item => {
        return item.notice && item.notice.toLowerCase().includes(searchKey);
      });
    },
    //科室排名图表
    getDepartmentMyChart() {
      const myChartId = document.getElementById('myChart1');
      myChartId.style.height = this.departmentOptions.tableList.length * 48 + 100 + 'px';
      //销毁上一次实例
      echarts.init(myChartId).dispose();
      // 构建新实例
      let myChart = echarts.init(myChartId);
      myChart.setOption(this.departmentMyChart, true);
      window.addEventListener('resize', function () {
        myChart.resize();
      });
      const resizeObserver = new ResizeObserver(() => {
        myChart.resize();
      });
      resizeObserver.observe(document.getElementById('myChart1'));
    },
    //endregion

    makeDoctorRank(data) {
      //处理图表数据
      let newData = JSON.parse(JSON.stringify(data)) || [];
      //根据defect_doc_count进行顺序排名 小的在前面
      return newData.sort((a, b) => {
        return a.defect_doc_count - b.defect_doc_count;
      });
    },

    toPageDoctor(row, sort) {
      this.$router.push({
        path: '/doctor/bl',
        query: {
          doctor_name: row.key,
          sort,
          start_time: this.formData.startTime,
          end_time: this.formData.endTime,
        },
      });
    },
    // 医师排名
    onToggleDoctorShow() {
      this.doctor_show = !this.doctor_show;
    },
    onToggleQuexianShow() {
      this.quxian_show = !this.quxian_show;
    },
    // 获取医生列表
    // getDoctorList() {
    //   this.$axios2.post('/case-quality/doctor_list').then(res => {
    //     this.doctorList = res.data;
    //   });
    // },
    // 缺陷病例统计
    // getAnalysis() {
    //   this.$axios2.post('/case-quality/analysis').then(res => {

    //   });
    // },

    // 甲乙病级病例
    getMedicalRecordLevel() {
      this.$axios2.post('/case-quality/medical_record_level').then(res => {});
    },
    // 获取部门集合
    // getDepartmentList() {
    //   this.$axios.post('/get_omr_department_list').then(res => {
    //     this.departmentList = res.data;
    //   });
    // },
    // 获取缺陷问题
    getCaseList() {
      let pramse = {
        ...this.formData,
        ...this.caseSearchData,
        case_title: this.caseSearchData.case_title.join(','),
        case_notice: this.caseSearchData.case_notice.join(','),
        page: this.paginationDataNotice.currentPage,
        size: this.paginationDataNotice.pageSize,
      };
      this.$axios.post('/case-quality/defect_issues', pramse).then(res => {
        this.caseList = res.data.list;
        this.paginationDataNotice.total = res.data.count;
      });
    },
    exportCaseList() {
      let params = {
        ...this.formData,
        ...this.caseSearchData,
        is_export: 1,
        case_title: this.caseSearchData.case_title.join(','),
        case_notice: this.caseSearchData.case_notice.join(','),
        page: this.paginationDataNotice.currentPage,
        size: this.paginationDataNotice.pageSize,
      };
      exportDefectIssues(params).then(res => {
        const content = res.data;
        const blob = new Blob([content]);
        const fileName = `缺陷问题.csv`;
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
    // 获取医师排名

    // 分页
    handleDoctorSizeChange(val) {
      this.paginationDataDoctor.page = 1;
      this.paginationDataDoctor.size = val;
      this.getDoctorRank();
    },
    handleDoctorCurrentChange(val) {
      this.paginationDataDoctor.page = val;
      this.getDoctorRank();
    },

    getDoctorRank() {
      const { page, size } = this.paginationDataDoctor;
      const params = {
        page,
        page_size: size,
        // start_time: this.formData.startTime,
        // end_time: this.formData.endTime,
        // is_export: 0,
        ...this.formData,
      };
      this.$axios2.post('/case-quality/medical_record_doctor', params).then(res => {
        // const { count } = res.data
        // if (count < 10) {
        //   for (let i = 0; i < 10 - count - 1; i++) {
        //     res.data.list.push({
        //       "key": "",
        //       "doc_count": '',
        //       "defect_doc_count": '',
        //       "score": '',
        //       "svg_score": '',
        //       "proportion": "",
        //       "dep_name": '',
        //       "code": '',
        //     })
        //   }
        // }
        this.doctor_tableData = res.data.list;
        this.paginationDataDoctor.total = res.data.count;
        this.doctor_rank = JSON.parse(JSON.stringify(res.data.list)).slice(0, 10);
      });
    },
    // getCounts() {
    //   let pramse = {
    //     start_time: this.formData.startTime,
    //     end_time: this.formData.endTime,
    //   };
    //   this.$axios.post('/case-quality/analysis', pramse).then(res => {
    //     this.countsData = res.data;
    //   });
    // },

    getSummaryData() {
      let params = Object.assign({}, this.formData);
      console.log(this.formData);
      this.$axios.post('/CaseHistory/Terminal/getTotalList', params).then(res => {
        if (res.code == 200) {
          this.summaryData = res.data;
          let dataList = [
            { score_lv: '丙', num: 0 },
            { score_lv: '乙', num: 0 },
            { score_lv: '甲', num: 0 },
          ];
          dataList.forEach(item => {
            let num = res.data.case_calibre.find(item2 => item2.score_lv == item.score_lv);
            item.num = num ? num.num : 0;
          });
          const colors = ['#f02b3fff', '#ed8b1aff', '#2e8241ff'];
          const option = {
            xAxis: {},
            grid: {
              // 让图表占满容器
              top: '0px',
              bottom: '0px',
              left: '20px',
              right: '45PX',
            },
            yAxis: { data: ['丙', '乙', '甲'] },
            series: [
              {
                type: 'bar',
                data: dataList.map(item => item.num),
                itemStyle: {
                  color: function (params) {
                    //通过返回值的下标一一对应将颜色赋给柱子上
                    return colors[params.dataIndex];
                  },
                },
                label: {
                  show: true, //开启显示
                  position: 'right', //在上方显示
                  textStyle: {
                    //数值样式
                    color: 'black', //字体颜色
                    fontSize: 16, //字体大小
                  },
                },
              },
            ],
          };
          const chartDom = document.getElementById('quality-chart');
          chartDom.style.height = '120px';
          echarts.init(chartDom).dispose();
          const myChart1 = echarts.init(chartDom);

          myChart1.setOption(option, true);
          window.addEventListener('resize', function () {
            myChart1.resize();
          });
          const resizeObserver = new ResizeObserver(() => {
            myChart1.resize();
          });
          resizeObserver.observe(document.getElementById('quality-chart'));
        }
      });
    },

    // selectInfo() {
    //   // let pramse = {};
    //   this.$axios.post('/selectInfo').then(res => {
    //     //问题属性 level
    //     this.quarterList = res.data.quarter;
    //     // 季度
    //     this.monthList = res.data.month;
    //     //月
    //     this.yearList = res.data.year;
    //   });
    // },
    // 选择时间段
    // chooseTime(time) {
    //   this.formData.rangeDate = this.timesCalculation(time).slice(0, 2);
    // },

    // 医师排名导出
    handleExport() {
      const { page, size } = this.paginationDataDoctor;
      const params = {
        page,
        page_size: size,
        start_time: this.formData.startTime,
        end_time: this.formData.endTime,
        is_export: 1,
      };
      medicalRecordDoctorExport(params).then(res => {
        const content = res.data; // 后台返回二进制数据
        const blob = new Blob([content]);
        const fileName = `医师排名.csv`;
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
      //查询
      // let type_id = '';
      // if (this.formData.type == '1') {
      //   type_id = this.formData.year.id || '';
      // } else if (this.formData.type == '2') {
      //   type_id = this.formData.quarter.id;
      // } else {
      //   type_id = this.formData.month.id;
      // }
      // let pramse = {
      //   start_time: this.formData.startTime,
      //   end_time: this.formData.endTime,
      // };
      this.storageSet('start_time', this.formData.startTime);
      this.storageSet('end_time', this.formData.endTime);
      this.storageSet('homeFrom', this.formData);
      // this.initCharts1(pramse);
      // this.getCounts();
      //  获取汇总的数据  同时渲染树状图
      this.getSummaryData();

      this.getCaseList();
      // 获取医师排名
      this.getDoctorRank();
      // 缺陷病例统计
      // this.getAnalysis();
      // 甲乙病级病例
      // this.getMedicalRecordLevel();
      //科室维度排名
      this.getDepartmentTableList();
    },
    // initCharts1(pramse) {
    //   this.$axios.post('/case-quality/ranking_department', pramse).then(res => {
    //     let dataName = [];
    //     let dataFleg = [];
    //     const total_error_medical = [];
    //     let dataNum = [];
    //     for (let item in res.data.list.slice(0, 10)) {
    //       dataName.push(res.data.list[item].name);
    //       dataFleg.push(res.data.list[item].total_medical);
    //       dataNum.push(res.data.list[item].item);
    //       total_error_medical.push(res.data.list[item].total_error_medical);
    //     }
    //     // 销毁上一次实例
    //     echarts.init(document.getElementById('myChart1')).dispose();
    //     // 构建新实例
    //     let myChart = echarts.init(document.getElementById('myChart1'));
    //     window.addEventListener('resize', function () {
    //       myChart.resize();
    //     });
    //     myChart.setOption({
    //       tooltip: {
    //         trigger: 'axis',
    //         axisPointer: {
    //           type: 'shadow'
    //         }
    //       },
    //       legend: {},
    //       grid: {
    //         left: '3%',
    //         right: '4%',
    //         bottom: '3%',
    //         containLabel: true
    //       },
    //       xAxis: {
    //         type: 'value',
    //         boundaryGap: [0, 0.01]
    //       },
    //       yAxis: {
    //         type: 'category',
    //         data: ['Brazil', 'Indonesia', 'USA', 'India', 'China', 'World']
    //       },
    //       series: [
    //         {
    //           name: '质控病例',
    //           type: 'bar',
    //           data: [18203, 23489, 29034, 104970, 131744, 630230]
    //         },
    //         {
    //           name: '缺陷病例',
    //           type: 'bar',
    //           data: [19325, 23438, 31000, 121594, 134141, 681807]
    //         }
    //       ]
    //     })
    //     /*
    //     if (res.data.list.length) {
    //       myChart.setOption({
    //         toolbox: {
    //           feature: {
    //             saveAsImage: {
    //               name: '科室排名',
    //             },
    //           },
    //         },
    //         tooltip: {
    //           trigger: 'axis',
    //           axisPointer: {
    //             type: 'cross',
    //             crossStyle: {
    //               color: '#999',
    //             },
    //           },
    //         },
    //         legend: {
    //           show: true,
    //         },
    //         xAxis: {
    //           type: 'category',
    //           data: dataName,
    //           axisLabel: {
    //             rotate: 15,
    //           },
    //         },
    //         grid: {
    //           left: '3%',
    //           right: '3%',
    //           bottom: '3%',
    //           containLabel: true,
    //         },
    //         yAxis: {
    //           type: 'value',
    //         },
    //         series: [
    //           {
    //             data: dataFleg,
    //             type: 'bar',
    //             showBackground: true,
    //             barMaxWidth: 30,
    //             label: {
    //               show: true,
    //               position: 'top',
    //             },
    //             name: '病案数',
    //             backgroundStyle: {
    //               color: 'rgba(180, 180, 180, 0.2)',
    //             },
    //           },
    //           {
    //             data: total_error_medical,
    //             type: 'bar',
    //             showBackground: true,
    //             barMaxWidth: 30,
    //             label: {
    //               show: true,
    //               position: 'top',
    //             },
    //             name: '缺陷病案数',
    //             backgroundStyle: {
    //               color: 'rgba(180, 180, 180, 0.2)',
    //             },
    //           },
    //         ],
    //       });
    //     } else {
    //       myChart.setOption({
    //         title: {
    //           text: '暂无数据',
    //           x: 'center',
    //           y: 'center',
    //           textStyle: {
    //             fontSize: 14,
    //             fontWeight: 'normal',
    //           },
    //         },
    //       });
    //     }
    //     */
    //   });
    // },
  },
};
</script>

<style lang="scss" scoped>
.input-box {
  .el-col-5 {
    max-width: 20%;
    flex: 0 0 20%;
  }

  .summary-card {
    background-color: rgb(245, 247, 247);
  }

  .summary-card-title {
    font-size: 20px;
  }

  .el-card__body {
    padding: 0;
  }

  .summary-card-body {
    height: 120px;
    width: 100%;
    display: flex;
    justify-content: center;
    align-items: center;
  }

  .summary-content-text {
    font-size: 30px;
    font-weight: 600;
    text-decoration: underline;
    margin-right: 10px;
    color: rgb(33, 100, 176);
  }

  .summary-content-text1 {
    font-size: 30px;
    font-weight: 600;
    margin-right: 10px;
  }

  .summary-icon {
    font-size: 20px;
    color: #2164b0ff;
  }
}

.pages {
  padding: 0 18px;
  background: #f4f4f4;

  .btnNAv {
    display: flex;
    justify-content: flex-end;
    padding-top: 30px;
    padding-bottom: 10px;

    a {
      padding: 15px 30px;
      color: #fff;
      border-radius: 10px;
      margin-left: 15px;
    }

    .bj {
      background: #35ae4a;
    }

    .bc {
      background: #dd7500;
    }

    .dc {
      background: #439ab6;
    }

    .fh {
      background: #185da6;
    }
  }

  .block {
    margin-bottom: 20px;
    background: #fff;
    padding: 25px 15px;
    border-radius: 5px;

    .blockCon {
      display: flex;
      justify-content: space-between;

      .lefts {
        display: flex;
      }

      .selects {
        margin: 0 20px;

        span {
          margin-right: 10px;
        }
      }
    }

    .ytext {
      font-size: 16px;
      color: #e48d53;
      font-weight: 400;
      line-height: 40px;
    }
  }

  .cardBox {
    margin: 0 0 16px 0;
    background: #fff;
    padding: 25px 15px;
    border-radius: 5px;

    .contentBox {
      display: flex;

      .left {
        display: flex;
        flex: 1;

        .l {
          display: flex;
          flex: 1;
          flex-wrap: wrap;

          .i {
            width: calc(50% - 20px);
            margin-right: 20px;
            height: 86px;
            background: #30b48e;
            border-radius: 5px;
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;

            .ba {
              flex: 1;
              font-size: 16px;
              font-weight: 400;
              color: #fff;
            }

            .num {
              font-size: 24px;
              font-weight: bold;
              color: #fff;
              flex: 1;
              margin-left: 20px;
            }

            .icon {
              width: 50px;
              position: absolute;
              top: 18px;
              right: 25px;
            }
          }
        }

        .r {
          margin: 0 9% 0 0;

          .i {
            width: 195px;
            height: 56px;
            background: #eaf4ff;
            border-radius: 4px;
            margin: 0 0 9px 0;
            display: flex;
            align-items: center;
            justify-content: center;

            .icon {
              width: 22px;
              margin: 6px 0 0 27px;
            }

            .t {
              font-size: 18px;
              font-weight: 400;
              color: #333333;
              text-align: left;
              margin-left: 10px;
            }

            .rt {
              margin-left: 10px;
              font-weight: bold;
              color: #38a1f2;
              font-size: 18px;
              text-align: left;
            }
          }

          .i:nth-child(1) {
            background: #93d2f3;
          }

          .i:nth-child(2) {
            background: #f4ce98;
          }

          .i:nth-child(3) {
            background: #de868f;
          }
        }
      }

      .right {
        width: 521px;
      }
    }
  }

  .cpoin {
    cursor: pointer;
  }

  .chart {
    margin-bottom: 16px;
    background-color: #fff;
    padding: 25px 15px 10px;
  }
}

.color-btn {
  color: #409eff;
  border-color: #c6e2ff;
  background-color: #ecf5ff;
}

.title-box {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.medicalRecord-box {
  width: 100%;
  display: flex;
  margin-top: 16px;

  & > div {
    flex: 1;
  }

  .medicalRecord-list-box {
    flex: 1;
  }
}

// 卡片背景

.pages {
  .bg-card {
    padding: 20px;
    background: #fff;
    border-radius: 5px;
    overflow-x: hidden;
  }

  .el-form-item__label {
    text-align: center;
  }

  .mb20 {
    margin-bottom: 20px;
  }

  .bg185DA6 {
    background: #185da6;
  }

  .text-right {
    text-align: right;
  }

  .text-center {
    text-align: center;
  }

  .link {
    text-decoration: underline;
    color: #ff786f;
    cursor: pointer;
  }

  .link2 {
    color: #004983;
    cursor: pointer;
  }

  .c_FF786F {
    color: #ff786f;
  }
}

.title_arrow {
  width: 10px;
  height: 11px;
  margin-left: 5px;
  cursor: pointer;

  &.arrow_top {
    transform: rotate(180deg);
  }
}

#tongji_pie {
  height: 200px;
}

#qxxq_pie {
  height: 600px;
  margin-top: 68px;
}

.xz-btn {
  width: 84px;
  height: 32px;
  text-align: center;
  line-height: 32px;
  background: #185da6;
  border-radius: 6px;
  color: #fff;
  font-size: 14px;
  cursor: pointer;
  //position: absolute;
  right: 20px;
  top: 0;
}
</style>
