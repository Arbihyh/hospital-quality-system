<template>
  <div class="bg-box">
    <!-- 搜索栏 -->
    <div class="bg-card" style="margin-bottom: 18px">
      <el-form :inline="true" ref="filterListFormRef" :model="formInline" class="demo-form-inline">
        <div style="display: flex; justify-content: left; flex-wrap: wrap">
          <div style="display: flex; justify-content: center; width: calc(40% - 20px); margin-right: 20px; margin-bottom: 10px">
            <div style="width: 15%; font-size: 14px; margin-top: 10px; display: flex; justify-content: left">出院时间</div>
            <el-date-picker style="width: 37%" v-model="formInline.start_time" type="date" placeholder="出院开始日期" value-format="yyyyMMdd" format="yyyy年MM月dd日" />
            <div style="width: 11%; margin-left: auto; font-size: 14px; margin-top: 10px; display: flex; justify-content: center">至</div>
            <el-date-picker
              style="margin-left: auto; width: 37%"
              v-model="formInline.end_time"
              type="date"
              placeholder="出院结束日期"
              value-format="yyyyMMdd"
              format="yyyy年MM月dd日"
            />
          </div>

          <div style="display: flex; justify-content: center; width: calc(20% - 10px); margin-right: 10px; margin-bottom: 10px">
            <div style="width: 25%; margin-left: auto; font-size: 14px; margin-top: 10px">在院状态</div>
            <el-select style="width: 75%" v-model="formInline.zy_status" placeholder="请选择在院状态" clearable>
              <el-option v-for="(item, index) in searchInfoOptions.zyStatusArray" :label="item.label" :value="item.value" :key="index"></el-option>
            </el-select>
          </div>
          <div style="display: flex; justify-content: center; width: calc(20% - 10px); margin-right: 10px; margin-bottom: 10px">
            <div style="width: 25%; margin-left: auto; font-size: 14px; margin-top: 10px">编目状态</div>
            <el-select style="width: 75%" v-model="formInline.bm_status" placeholder="请选择编目状态" clearable>
              <el-option v-for="(item, index) in searchInfoOptions.bmStatusArray" :label="item.label" :value="item.value" :key="index"></el-option>
            </el-select>
          </div>
          <div style="display: flex; justify-content: center; width: calc(20% - 10px); margin-right: 10px; margin-bottom: 10px">
            <div style="width: 25%; margin-left: auto; font-size: 14px; margin-top: 10px">所属院区</div>
            <el-select style="width: 75%" v-model="formInline.YQ_CODES" placeholder="请选择所属院区" multiple @change="yqChange">
              <el-option v-for="(item, index) in searchInfoOptions.yqArray" :label="item.YQ_NAME" :value="item.dep_id" :key="index"></el-option>
            </el-select>
          </div>

          <div style="display: flex; justify-content: center; width: calc(40% - 20px); margin-right: 20px; margin-bottom: 10px">
            <div style="width: 15%; font-size: 14px; margin-top: 10px; justify-content: left; display: flex">所属科室</div>
            <el-cascader
              style="width: 85%"
              v-model="formInline.KS_IDS"
              :options="searchInfoOptions.depArray"
              :props="cascaderProps"
              :show-all-levels="false"
              multiple
              clearable
              collapse-tags
              @change="ksChange"
            ></el-cascader>
          </div>
          <div style="display: flex; justify-content: center; width: calc(20% - 10px); margin-right: 10px; margin-bottom: 10px">
            <div style="width: 25%; margin-left: auto; font-size: 14px; margin-top: 10px">所属病区</div>
            <el-cascader style="width: 75%" v-model="formInline.BQ_IDS" :options="searchInfoOptions.bqArray" :props="cascaderProps" multiple clearable collapse-tags></el-cascader>
          </div>
          <div style="display: flex; justify-content: center; width: calc(20% - 10px); margin-right: 10px; margin-bottom: 10px">
            <div style="display: flex; width: 25%; margin-left: auto; font-size: 14px; margin-top: 10px">病案编号</div>
            <el-input style="width: 75%" v-model="formInline.AAA28" placeholder="请输入病案号" class="width150"></el-input>
          </div>

          <div style="display: flex; justify-content: center; width: calc(20% - 10px); margin-right: 10px; margin-bottom: 10px; margin-left: auto">
            <el-button type="primary" class="bg185DA6" @click="onGobalSearch" style="margin-left: auto">查询</el-button>
            <el-button @click="reset">重置</el-button>
          </div>
        </div>
        <!---->
      </el-form>
    </div>
    <!-- 汇总统计 -->
    <div class="bg-card" style="margin-bottom: 24px">
      <div style="margin-bottom: 16px">
        <CardTitle title="汇总统计">
          <el-image
            class="title_arrow"
            :class="{ arrow_top: !tongji_show }"
            :src="require('../../assets/images/arrow-down.png')"
            fit="contain"
            @click="onToggleTongjiShow"
          ></el-image>
        </CardTitle>
      </div>
      <el-collapse-transition>
        <div v-show="tongji_show">
          <el-row :gutter="20">
            <el-col :span="16">
              <el-row :gutter="20">
                <el-col :span="8">
                  <div class="tongji-item">
                    <div class="title">首页总例数</div>
                    <div class="middle">
                      日均例数：
                      <span>{{ tongjiData.dayAvg }}</span>
                    </div>
                    <span class="count" style="cursor: pointer">
                      {{ tongjiData.blSum }}
                    </span>
                  </div>
                </el-col>
                <el-col :span="8">
                  <div class="tongji-item nth2">
                    <div class="title">缺陷总例数</div>
                    <div class="middle">
                      缺陷占比：
                      <span>{{ tongjiData.averageError }}%</span>
                    </div>
                    <span class="count" style="cursor: pointer">{{ tongjiData.qxSum }}</span>
                  </div>
                </el-col>
                <el-col :span="8">
                  <div class="tongji-item">
                    <div class="title">平均得分</div>
                    <div class="middle">
                      最低分：
                      <span>{{ tongjiData.minScore }}</span>
                    </div>
                    <div class="count">{{ tongjiData.averageScore }}</div>
                  </div>
                </el-col>
              </el-row>
              <el-row :gutter="8">
                <el-col :span="5">
                  <div class="level-item">
                    <div class="level-text">
                      <span class="level">优</span>
                      <span class="count">{{ tongjiData.you_sum }}</span>
                    </div>
                    <div class="text-right level-percent">
                      <span>占比{{ tongjiData.you_ratio }}%</span>
                    </div>
                  </div>
                </el-col>
                <el-col :span="5">
                  <div class="level-item">
                    <div class="level-text">
                      <span class="level">良</span>
                      <span class="count">{{ tongjiData.liang_sum }}</span>
                    </div>
                    <div class="text-right level-percent">
                      <span>占比{{ tongjiData.liang_ratio }}%</span>
                    </div>
                  </div>
                </el-col>
                <el-col :span="5">
                  <div class="level-item">
                    <div class="level-text">
                      <span class="level">中</span>
                      <span class="count">{{ tongjiData.zhong_sum }}</span>
                    </div>
                    <div class="text-right level-percent">
                      <span>占比{{ tongjiData.zhong_ratio }}%</span>
                    </div>
                  </div>
                </el-col>
                <el-col :span="5">
                  <div class="level-item">
                    <div class="level-text">
                      <span class="level">差</span>
                      <span class="count">{{ tongjiData.cha_sum }}</span>
                    </div>
                    <div class="text-right level-percent">
                      <span>占比{{ tongjiData.cha_ratio }}%</span>
                    </div>
                  </div>
                </el-col>
                <el-col :span="4">
                  <div class="level-item" style="background: #fff">
                    <el-popover placement="top-start" title="" width="245" trigger="hover">
                      <div slot v-html="levelText"></div>
                      <div slot="reference" class="text-center" style="color: #d38d14; font-size: 16px">
                        <i class="el-icon-warning-outline"></i>
                        等级计算说明
                      </div>
                    </el-popover>
                  </div>
                </el-col>
              </el-row>
            </el-col>
            <el-col :span="8">
              <div id="tongji_pie"></div>
            </el-col>
          </el-row>
        </div>
      </el-collapse-transition>
    </div>
    <!-- 缺陷详情 -->
    <div class="bg-card" style="margin-bottom: 24px">
      <div style="display: flex; justify-content: space-between; margin-bottom: 16px">
        <CardTitle title="缺陷详情">
          <el-image
            class="title_arrow"
            :class="{ arrow_top: !quxian_show }"
            :src="require('../../assets/images/arrow-down.png')"
            fit="contain"
            @click="onToggleQuexianShow"
          ></el-image>
        </CardTitle>
        <el-button type="primary" class="export-btn" icon="el-icon-download" size="small" style="float: right" @click="onBmyProblemExport">下载</el-button>
      </div>

      <el-collapse-transition>
        <div v-show="quxian_show">
          <el-row :gutter="20">
            <el-col :span="24">
              <!-- 搜索栏 -->
              <el-form :inline="true" :model="qxxqData" class="demo-form-inline mb20">
                <el-form-item label="缺陷描述">
                  <el-input v-model="qxxqData.desc" placeholder="请输入缺陷描述" clearable></el-input>
                </el-form-item>
                <el-form-item label="缺陷字段">
                  <el-select v-model="qxxqData.field" placeholder="请选择缺陷字段" multiple collapse-tags clearable filterable>
                    <el-option v-for="(item, index) in fields" :label="item" :value="item" :key="'zd' + index"></el-option>
                  </el-select>
                </el-form-item>
                <el-form-item label="缺陷分级">
                  <el-select v-model="qxxqData.level" placeholder="请选择缺陷分级" clearable filterable>
                    <el-option v-for="(item, index) in levels" :label="item.label" :value="item.value" :key="'dj' + index"></el-option>
                  </el-select>
                </el-form-item>
                <el-form-item label="缺陷归类">
                  <el-select v-model="qxxqData.type" clearable filterable placeholder="全部">
                    <el-option label="患者基本信息" :value="0"></el-option>
                    <el-option label="诊疗信息" :value="1"></el-option>
                    <el-option label="费用信息" :value="2"></el-option>
                  </el-select>
                </el-form-item>
                <el-form-item label="编码员">
                  <el-select v-model="qxxqData.bmy_ids" placeholder="请选择编码员" clearable filterable multiple>
                    <el-option v-for="(item, index) in qxSearchOptions.bmyArray" :label="item.name" :value="item.id" :key="index"></el-option>
                  </el-select>
                </el-form-item>
                <el-form-item style="margin-bottom: 0">
                  <el-button type="primary" class="bg185DA6" @click="onQuexianSearch">查询</el-button>
                </el-form-item>
              </el-form>
            </el-col>
            <el-col :span="14">
              <el-table :data="tableData" class="mb20" style="width: 100%; margin-top: -20px">
                <el-table-column type="index" label="序号" width="80" align="center">
                  <template slot-scope="scope">
                    <span>{{ scope.$index + 1 + (paginationData.page - 1) * paginationData.size }}</span>
                  </template>
                </el-table-column>
                <el-table-column prop="desc" label="缺陷描述" align="center" show-overflow-tooltip>
                  <template slot-scope="scope">
                    <span class="c_FF786F">{{ scope.row.desc }}</span>
                  </template>
                </el-table-column>
                <el-table-column prop="field" label="缺陷字段" width="140" align="center" show-overflow-tooltip></el-table-column>
                <el-table-column prop="level" label="缺陷分级" width="120" align="center"></el-table-column>
                <el-table-column prop="count" label="缺陷数量" width="120" align="center">
                  <template slot-scope="scope">
                    <span class="link" @click="toPage(scope.row)">{{ scope.row.count }}</span>
                  </template>
                </el-table-column>
                <el-table-column label="缺陷占比" width="120" align="center">
                  <template slot-scope="scope">
                    <span v-if="scope.row.percentage">{{ scope.row.percentage }}</span>
                  </template>
                </el-table-column>
              </el-table>
              <!-- 分页 -->
              <div v-if="qxxqData.radio === 2">
                <div style="float: left; line-height: 32px">
                  <span>共{{ paginationData.total }}条记录</span>
                  <span style="margin-left: 10px">第{{ paginationData.page }}/{{ Math.ceil(paginationData.total / paginationData.size) }}页</span>
                </div>
                <el-pagination
                  style="float: right"
                  background
                  @size-change="handleSizeChange"
                  @current-change="handleCurrentChange"
                  :current-page="paginationData.page"
                  :page-size="paginationData.size"
                  layout="prev, pager, next, jumper"
                  :total="paginationData.total"
                ></el-pagination>
              </div>
            </el-col>
            <el-col :span="10">
              <div id="qxxq_pie"></div>
            </el-col>
          </el-row>
        </div>
      </el-collapse-transition>
    </div>
    <!-- 医师排名 -->
    <div class="bg-card" style="margin-bottom: 24px">
      <div style="display: flex; justify-content: space-between; margin-bottom: 16px">
        <CardTitle title="医师排名">
          <el-image
            class="title_arrow"
            :class="{ arrow_top: !doctor_show }"
            :src="require('../../assets/images/arrow-down.png')"
            fit="contain"
            @click="onToggleDoctorShow"
          ></el-image>
        </CardTitle>
        <el-button type="primary" class="export-btn" icon="el-icon-download" size="small" style="float: right" @click="onBmyDoctorExport">下载</el-button>
      </div>

      <div style="margin-bottom: 10px">
        <el-form v-model="ysSearch" class="demo-form-inline mb20" :inline="true">
          <el-form-item label="所属身份">
            <el-select v-model="ysSearch.sf_type" @change="ysTypeChange">
              <el-option v-for="(item, index) in ysSearchOptions.sfArray" :key="index" :label="item.label" :value="item.value"></el-option>
            </el-select>
          </el-form-item>
          <el-form-item label="所属人员">
            <el-select v-model="ysSearch.staff_code" clearable multiple collapse-tags filterable placeholder="请选择人员">
              <el-option v-for="(item, index) in ysSearchOptions.sfStaffArray" :key="index" :label="item" :value="index"></el-option>
            </el-select>
          </el-form-item>
          <el-form-item style="margin-bottom: 0">
            <el-button type="primary" class="bg185DA6" @click="getDoctorRank">查询</el-button>
          </el-form-item>
        </el-form>
      </div>
      <el-collapse-transition>
        <div v-show="doctor_show">
          <el-row :gutter="20">
            <el-col :span="14">
              <el-table :data="doctor_tableData" class="mb20" style="width: 150%" :default-sort="ysSortParams" @sort-change="handleSortChange">
                <el-table-column type="index" label="序号" width="50" align="center">
                  <template slot-scope="scope">
                    <span>{{ scope.$index + 1 + (paginationDataDoctor.page - 1) * paginationDataDoctor.size }}</span>
                  </template>
                </el-table-column>
                <el-table-column label="医师姓名/工号" width="160" align="center" show-overflow-tooltip>
                  <template slot-scope="scope">
                    <span v-if="scope.row.name">
                      {{ scope.row.name }}/{{ scope.row.code }}
                      <i class="el-icon-document-copy" style="color: green" @click="yiCodeCopy(scope.row.code)" />
                    </span>
                  </template>
                </el-table-column>
                <el-table-column prop="dep_name" label="医师科室" width="120" align="center" show-overflow-tooltip></el-table-column>
                <el-table-column prop="bl_sum" label="病历总数" width="100" align="center" sortable="custom" :sort-orders="['ascending', 'descending']">
                  <template slot-scope="scope">
                    <span v-if="scope.row.bl_sum" class="link2" @click="toPageDoctor(scope.row)">{{ scope.row.bl_sum }}例</span>
                  </template>
                </el-table-column>
                <el-table-column prop="total_deduction" label="总扣分" width="100" align="center" sortable="custom" :sort-orders="['ascending', 'descending']">
                  <template slot-scope="scope">
                    <span v-if="typeof scope.row.total_deduction === 'number'" class="link2" @click="toPageDoctorKf(scope.row)">{{ scope.row.total_deduction }}分</span>
                  </template>
                </el-table-column>
                <el-table-column prop="avg_score" label="平均得分" width="120" align="center" sortable="custom" :sort-orders="['ascending', 'descending']">
                  <template slot-scope="scope">
                    <span v-if="scope.row.avg_score">{{ scope.row.avg_score }}分</span>
                  </template>
                </el-table-column>
                <el-table-column prop="qx_total_num" label="缺陷总数" width="120" align="center">
                  <template slot-scope="scope">
                    <span v-if="typeof scope.row.qx_total_num === 'number'">{{ scope.row.qx_total_num }}例</span>
                  </template>
                </el-table-column>
                <el-table-column label="占缺占比" width="120" align="center">
                  <template slot-scope="scope">
                    <span v-if="typeof scope.row.qx_percentage === 'number'">{{ scope.row.qx_percentage }}%</span>
                  </template>
                </el-table-column>
              </el-table>
              <div style="overflow: hidden">
                <div style="float: left; line-height: 32px">
                  <span>共{{ paginationDataDoctor.total }}条记录</span>
                  <span style="margin-left: 10px">第{{ paginationDataDoctor.page }}/{{ Math.ceil(paginationDataDoctor.total / paginationDataDoctor.size) }}页</span>
                </div>
                <el-pagination
                  style="float: right"
                  background
                  @size-change="handleDoctorSizeChange"
                  @current-change="handleDoctorCurrentChange"
                  :current-page="paginationDataDoctor.page"
                  :page-size="paginationDataDoctor.size"
                  layout="prev, pager, next, jumper"
                  :total="paginationDataDoctor.total"
                ></el-pagination>
              </div>
            </el-col>
            <el-col :span="10">
              <DoctorRankVue v-if="doctor_tableData.length" :data="doctor_tableData" :key="refreshKey" ref="barRank" />
            </el-col>
          </el-row>
        </div>
      </el-collapse-transition>
    </div>
  </div>
</template>

<script>
import DoctorRankVue from './components/index/DoctorRank.vue';
import { bmyDoctorRanking, encoderProblemExport } from '@/api/excel';
import IconBtn from '@/views/medicalRecord/components/index/IconBtn.vue';
import moment from 'moment';
export default {
  components: {
    IconBtn,
    DoctorRankVue,
  },
  data() {
    return {
      refreshKey: 0,
      cascaderProps: {
        multiple: true, // 开启多选模式
        label: 'dep_name',
        value: 'dep_id',
        children: 'children',
        checkStrictly: true, // 允许独立选择任意层级
        emitPath: false, // 是否返回完整路径（true 返回路径数组，false 只返回末节点值）
      },
      search: {}, //搜索内容
      searchInfoOptions: {
        //顶部搜索options
        yqArray: [], //院区options
        depArray: [], //科室options
        bqArray: [], //病区options
        zyStatusArray: [
          //在院状态
          { label: '在院', value: 1 },
          { label: '出院', value: 2 },
        ],
        bmStatusArray: [
          //编目状态
          { label: '已编目', value: 1 },
          { label: '未编目', value: 2 },
        ],
      },
      qxSearchOptions: {
        //缺陷详情options
        bmyArray: [], //编码员options
      },
      //region 医师排名
      // 默认排序配置
      ysSortParams: {
        //排序
        prop: 'avg_score', // 排序字段（需与prop一致）
        order: 'descending', // 初始顺序：ascending（升序）/descending（降序）
      },
      ysSearch: { sf_type: 1 }, //医师排名search
      ysSearchOptions: {
        //医师排名options
        sfArray: [
          { label: '科主任', value: 1 },
          { label: '主任（副主任）医师', value: 2 },
          { label: '主治医师', value: 3 },
          { label: '住院医师', value: 4 },
          { label: '编码员', value: 5 },
        ], //身份option
        sfStaffArray: [], //身份人员options
      },
      //endregion
      levels: [
        {
          label: '强制',
          value: 0,
        },
        {
          label: '建议',
          value: 1,
        },
      ],
      fields: [],
      levelText: '优：≥97分；</br>' + '良：90~96分且不出现A类错误；</br>' + '中：75~89分且不出现A类错误；</br>' + '差：＜75分。',
      formInline: {
        start_time: moment().subtract(30, 'days').format('YYYYMMDD'),
        end_time: moment().format('YYYYMMDD'),
        zy_status: '',
        bm_status: '',
        YQ_CODES: [],
        KS_IDS: [],
        BQ_IDS: [],
        AAA28: '',
      },
      tongjiData: {
        blSum: 0,
        dayAvg: 0,
        qxSum: 0,
        averageError: 0,
        averageScore: 0,
        minScore: 0,
        you_sum: 0,
        liang_sum: 0,
        zhong_sum: 0,
        cha_sum: 0,
        you_ratio: 0,
        liang_ratio: 0,
        zhong_ratio: 0,
        cha_ratio: 0,
        jbxx: 0,
        zlxx: 0,
        fyxx: 0,
        qtxx: 0,
      },
      tongji_show: true,
      tongjiDom: null,
      // 缺陷详情
      quxian_show: true,
      qxxqData: {
        radio: 2,
        dep_id: '',
        type: '',
        level: '',
        desc: '',
        field: [],
      },
      qxPieDom: null,
      tableData: [],
      paginationData: {
        page: 1,
        size: 10,
        total: 0,
      },
      departmentList: [],
      // 医师排名
      doctor_show: true,
      paginationDataDoctor: {
        page: 1,
        size: 10,
        total: 11,
      },
      doctor_tableData: [],
      basicBqList: [],
    };
  },
  created() {
    this.getDoctorRank(); //医师排名
    this.getAllDoctor(); //医师身份
  },
  async mounted() {
    // this.getAAC01Date(); //获取出院日期
    this.getSearchOptions(); //获取搜索options
    await this.getTongjiData();
    this.tongjiPie();
    this.qxxqPie();
    this.getQuexianData();
    this.getFieldList();
  },
  beforeRouteEnter(to, from, next) {
    next(vm => {
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
    yqChange() {
      this.formInline.KS_IDS = [];
      this.formInline.BQ_IDS = [];
      this.$axios.post('CaseHistory/Terminal/getKsOptions', { YQ_CODE: this.formInline.YQ_CODES }).then(res => {
        this.searchInfoOptions.depArray = res.data.ksArray; //科室
        this.searchInfoOptions.bqArray = res.data.bqArray; //病区
        this.basicBqList = res.data.bqArray; //科室
      });
    },
    //科室change事件
    ksChange() {
      console.log('ksChange', this.formInline.KS_IDS);
      this.formInline.BQ_IDS = [];
      // this.$axios.post('CaseHistory/Terminal/getBqOptions', { 'KS_CODE': this.formData.KS_CODE }).then(res => {
      //   // this.searchOptions.bqArray = this.cancelChildren(res.data.bqArray);//病区

      // })
      this.searchInfoOptions.bqArray = this.bqArray.filter(item => this.formInline.KS_IDS.includes(item.parent_id));
    },
    //头部默认出院日期
    getAAC01Date() {
      this.formInline.start_time = moment().subtract(30, 'days').format('YYYYMMDD');
      this.formInline.end_time = moment().format('YYYYMMDD');
    },
    //重置头部搜索条件
    reset() {
      this.formInline = {
        start_time: moment().subtract(30, 'days').format('YYYYMMDD'),
        end_time: moment().format('YYYYMMDD'),
        zy_status: '',
        bm_status: '',
        YQ_CODES: [],
        KS_IDS: [],
        BQ_IDS: [],
        AAA28: '',
      };
      // this.getAAC01Date();
    },
    //医师工号复制
    yiCodeCopy(value) {
      const textArea = document.createElement('textarea');
      textArea.value = value;
      textArea.style.position = 'fixed'; // 避免触发滚动条
      document.body.appendChild(textArea);
      textArea.select();
      try {
        document.execCommand('copy');
        this.$message.success('复制成功');
      } catch (err) {
        this.$message.error('复制失败');
      }
      document.body.removeChild(textArea);
    },
    //医师排名排序
    handleSortChange({ prop, order }) {
      this.ysSortParams = { prop, order };
      this.getDoctorRank();
    },
    //医师排名身份类型切换
    ysTypeChange() {
      this.ysSearch.staff_code = '';
      this.ysSearchOptions.sfStaffArray = [];
      this.getAllDoctor(); //获取当前身份内的所有医师
    },
    //获取当前身份内的所有医师
    getAllDoctor() {
      this.$axios_new.post('/api/bmy/getBmyIndexDoctorOptions', this.ysSearch).then(res => {
        this.ysSearchOptions.sfStaffArray = res.data;
      });
    },
    //获取搜索下拉options
    getSearchOptions() {
      this.$axios_new.post('/api/bmy/getSearchOptions').then(res => {
        this.searchInfoOptions.yqArray = res.data.yqArray; //院区
        // this.searchInfoOptions.depArray = res.data.depArray; //科室
        // this.searchInfoOptions.bqArray = res.data.bqArray; //病区
        //缺陷详情options
        this.qxSearchOptions.bmyArray = res.data.qxSearchOptions.bmyArray;
      });
    },
    // 获取缺陷字段选项
    getFieldList() {
      this.$axios_new.post('/api/bmy/errorFieldList').then(res => {
        this.fields = res.data;
      });
    },
    // 缺陷详情列表
    toPage(row) {
      const { start_time, end_time } = this.formInline;
      const search = this.formInline;
      this.$router.push({
        name: 'EncoderErrors',
        query: {
          rule_id: row.error_rule,
          start_time,
          end_time,
          ...this.qxxqData,
          is_qx: 2,
          YQ_CODES: search.YQ_CODES,
          KS_IDS: search.KS_IDS,
          BQ_IDS: search.BQ_IDS,
          zy_status: search.zy_status,
          bm_status: search.bm_status,
          AAA28: search.AAA28,
        },
      });
    },
    // 全局筛选
    async onGobalSearch() {
      await this.getTongjiData();
      this.tongjiPieUpdate();
      this.qxxqPieUpdate();
      this.getQuexianData();
      this.getDoctorRank();
    },
    // 获取缺陷数据
    getQuexianData() {
      const { dep_id, type, level, desc, field, bmy_ids } = this.qxxqData;
      const { page, size } = this.paginationData;
      const params = {
        dep_id,
        type,
        level,
        desc,
        field,
        bmy_ids,
        page,
        page_size: size,
        ...this.formInline,
      };
      //获取缺陷数据
      this.$axios_new.post('/api/bmy/qualityData', params).then(res => {
        let arr = [];
        if (res.data.data.length < 10) {
          for (let i = 0; i < 10 - res.data.data.length; i++) {
            arr.push({
              error_rule: '',
              count: '',
              eror_zb: '',
              category: '',
              down: '',
              desc: '',
              level: '',
              type: '',
            });
          }
        }
        this.tableData = res.data.data.concat(arr);
        this.paginationData.total = res.data.count;
      });
    },
    onBmyProblemExport() {
      const { dep_id, type, level, desc, field } = this.qxxqData;
      const params = {
        is_ten: 0,
        AAC11C: dep_id,
        level,
        type,
        desc,
        field,
        ...this.formInline,
      };
      encoderProblemExport(params).then(res => {
        const content = res.data; // 后台返回二进制数据
        const blob = new Blob([content]);
        const fileName = `编码员-缺陷问题.csv`;
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
    // 获取部门集合
    getDepartmentData() {
      this.$axios.post('/bmy/getAllDepartment').then(res => {
        this.departmentList = res.data;
      });
    },
    // 获取统计信息
    async getTongjiData() {
      await this.$axios_new.post('/api/bmy/qualityStatistics', this.formInline).then(res => {
        this.tongjiData = res.data;
      });
    },
    onToggleTongjiShow() {
      this.tongji_show = !this.tongji_show;
    },
    onToggleQuexianShow() {
      this.quxian_show = !this.quxian_show;
    },
    // 统计环图
    tongjiPie() {
      this.tongjiDom = this.$echarts.init(document.getElementById('tongji_pie'));
      const option = {
        tooltip: {
          trigger: 'item',
        },
        legend: {
          top: '30%',
          left: 'right',
          orient: 'vertical',
          align: 'left',
          itemGap: 30,
        },
        color: ['#5087EC', '#FF786F'],
        series: [
          {
            type: 'pie',
            center: ['42%', '50%'],
            radius: ['40%', '60%'],
            avoidLabelOverlap: true,
            startAngle: 180,
            label: {
              show: true,
              formatter: '{b}: {c}',
            },
            labelLine: {
              show: true,
              length2: 40,
              minTurnAngle: 120,
              showAbove: true,
            },
            data: [
              { value: this.tongjiData.blSum, name: '首页总病例' },
              { value: this.tongjiData.qxSum, name: '缺陷总例数' },
            ],
          },
        ],
      };
      this.tongjiDom.setOption(option);
      // 窗口大小改变 重新渲染
      window.addEventListener('resize', () => {
        this.tongjiDom.resize();
      });
    },
    tongjiPieUpdate() {
      const option = {
        tooltip: {
          trigger: 'item',
        },
        legend: {
          top: '5%',
          left: 'right',
          orient: 'vertical',
          align: 'left',
        },
        color: ['#5087EC', '#FF786F'],
        series: [
          {
            type: 'pie',
            radius: ['40%', '60%'],
            avoidLabelOverlap: true,
            label: {
              show: true,
              formatter: '{b}: {c}',
            },
            labelLine: {
              show: true,
            },
            data: [
              { value: this.tongjiData.blSum, name: '首页总病例' },
              { value: this.tongjiData.qxSum, name: '缺陷总例数' },
            ],
          },
        ],
      };
      this.tongjiDom.setOption(option);
    },
    // 缺陷环图
    qxxqPie() {
      this.qxPieDom = this.$echarts.init(document.getElementById('qxxq_pie'));
      const option = {
        tooltip: {
          trigger: 'item',
        },
        legend: {
          width: '300',
          top: '10%',
          left: 'center',
          align: 'left',
          formatter: name => {
            return `{a|${name}} `;
          },
          textStyle: {
            rich: {
              a: {
                width: 100,
              },
            },
          },
        },
        color: ['#FF8279', '#FF9F98', '#FFBEB9', '#FFD9D6'],
        series: [
          {
            type: 'pie',
            top: '20%',
            radius: ['55%', '70%'],
            avoidLabelOverlap: false,
            label: {
              show: true,
              formatter: '{b}: {c}',
            },
            labelLine: {
              show: true,
            },
            data: [
              { value: this.tongjiData.jbxx || 0, name: '患者基本信息' },
              { value: this.tongjiData.zlxx || 0, name: '诊疗信息' },
              { value: this.tongjiData.fyxx || 0, name: '费用信息' },
              { value: this.tongjiData.qtxx || 0, name: '其他信息' },
            ],
          },
        ],
      };
      this.qxPieDom.setOption(option);
      // 窗口大小改变 重新渲染
      window.addEventListener('resize', () => {
        this.qxPieDom.resize();
      });
    },
    qxxqPieUpdate() {
      const option = {
        tooltip: {
          trigger: 'item',
        },
        legend: {
          top: '10%',
          left: 'center',
          align: 'left',
        },
        color: ['#FF8279', '#FF9F98', '#FFBEB9', '#FFD9D6'],
        series: [
          {
            type: 'pie',
            top: '20%',
            radius: ['55%', '70%'],
            avoidLabelOverlap: false,
            label: {
              show: true,
              formatter: '{b}: {c}',
            },
            labelLine: {
              show: true,
            },
            data: [
              { value: this.tongjiData.jbxx || 0, name: '患者基本信息' },
              { value: this.tongjiData.zlxx || 0, name: '诊疗信息' },
              { value: this.tongjiData.fyxx || 0, name: '费用信息' },
              { value: this.tongjiData.qtxx || 0, name: '其他信息' },
            ],
          },
        ],
      };
      this.qxPieDom.setOption(option);
    },
    // 分页
    handleSizeChange(val) {
      this.paginationData.page = 1;
      this.paginationData.size = val;
      this.getQuexianData();
    },
    //跳页
    handleCurrentChange(val) {
      this.paginationData.page = val;
      this.getQuexianData();
    },
    // tag 修改
    handleRadioChange(val) {
      if (val === 1) {
        this.paginationData.page = 1;
        this.paginationData.size = 10;
      }
      this.getQuexianData();
    },
    // 缺陷搜索
    onQuexianSearch() {
      this.paginationData.page = 1;
      this.getQuexianData();
    },
    // 医师排名
    onToggleDoctorShow() {
      this.doctor_show = !this.doctor_show;
    },
    // 分页
    handleDoctorSizeChange(val) {
      this.paginationDataDoctor.page = 1;
      this.paginationDataDoctor.size = val;
      this.getDoctorRank();
    },
    //跳页
    handleDoctorCurrentChange(val) {
      this.paginationDataDoctor.page = val;
      this.getDoctorRank();
    },
    // 医师排名
    getDoctorRank() {
      //zz修改
      let params = Object.assign({}, this.formInline, this.ysSearch); //将顶部搜索条件与医师排名搜索条件合并成一个
      params['prop'] = this.ysSortParams.prop; //排序字段
      params['order'] = this.ysSortParams.order; //排序规则
      params['page'] = this.paginationDataDoctor.page; //页码
      params['size'] = this.paginationDataDoctor.size; //页数
      this.$axios_new.post('/api/bmy/doctorRanking', params).then(res => {
        this.doctor_tableData = res.data.data;
        this.paginationDataDoctor.total = res.data.total;
        this.refreshKey++;
      });
    },
    // 导出
    onBmyDoctorExport() {
      //zz修改
      let params = Object.assign({}, this.formInline, this.ysSearch); //将顶部搜索条件与医师排名搜索条件合并成一个
      params['prop'] = this.ysSortParams.prop; //排序字段
      params['order'] = this.ysSortParams.order; //排序规则
      params['is_export'] = 1;
      bmyDoctorRanking(params).then(res => {
        const content = res.data; // 后台返回二进制数据
        const blob = new Blob([content]);
        const fileName = `编码员-医师排名.csv`;
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
    // 医生病历总数
    toPageDoctor(row) {
      let params = {};
      params['zy_status'] = this.formInline.zy_status;
      params['bm_status'] = this.formInline.bm_status;
      params['start_time'] = this.formInline.start_time;
      params['end_time'] = this.formInline.end_time;
      params['doctor_code'] = row.code;
      params['sf_type'] = this.ysSearch.sf_type;
      params['AAA28'] = this.formInline.AAA28;
      this.$router.push({ name: 'DoctorBl', query: params });
    },
    // 医生病历扣分
    toPageDoctorKf(row) {
      let params = {};
      params['zy_status'] = this.formInline.zy_status;
      params['bm_status'] = this.formInline.bm_status;
      params['start_time'] = this.formInline.start_time;
      params['end_time'] = this.formInline.end_time;
      params['doctor_code'] = row.code;
      params['sf_type'] = this.ysSearch.sf_type;
      params['AAA28'] = this.formInline.AAA28;
      this.$router.push({ name: 'DoctorBlKf', query: params });
    },
  },
};
</script>

<style lang="scss" scoped>
.custom-form-item {
  display: flex;
  align-items: center;
}
::custom-form-item .el-form-item__label {
  width: 120px; /* 自定义标签宽度 */
  margin-right: 10px; /* 标签与下拉框间距 */
}
::v-deep .el-radio-button__inner {
  border-color: #dcdfe6 !important;
  font-weight: 500;
  &:hover {
    color: #606266;
  }
}
::v-deep .el-radio-button__orig-radio:checked + .el-radio-button__inner {
  box-shadow: #dcdfe6 -1px 0px 0px 0px !important;
}
.tongji-item {
  height: 114px;
  background: #5087ec;
  border-radius: 8px;
  margin-bottom: 20px;
  padding: 25px 18px 0;
  box-sizing: border-box;
  position: relative;
  &.nth2 {
    background: #ff786f;
  }
  .title {
    font-size: 16px;
    font-family: PingFangSC-Regular, PingFang SC;
    font-weight: 400;
    color: #ffffff;
    line-height: 22px;
    margin-bottom: 20px;
  }
  .middle {
    text-align: right;
    font-size: 16px;
    font-family: PingFangSC-Regular, PingFang SC;
    font-weight: 400;
    color: #ffffff;
    line-height: 22px;
    position: absolute;
    top: 45px;
    right: 18px;
  }
  .count {
    font-size: 24px;
    font-family: DINAlternate-Bold, DINAlternate;
    font-weight: bold;
    color: #ffffff;
    line-height: 28px;
  }
}
.level-item {
  height: 75px;
  background: #eaf4ff;
  border-radius: 4px;
  padding: 0 11px;
  box-sizing: border-box;
  overflow: hidden;
  span {
    font-size: 14px;
    font-family: PingFangSC-Regular, PingFang SC;
    font-weight: 400;
    color: #333333;
    line-height: 75px;
    vertical-align: middle;
  }
  .level {
    font-size: 20px;
    font-family: PingFangSC-Medium, PingFang SC;
    font-weight: 500;
    color: #333333;
    line-height: 75px;
  }
  .count {
    font-size: 24px;
    font-family: DINAlternate-Bold, DINAlternate;
    font-weight: bold;
    color: #38a1f2;
    line-height: 75px;
    margin-left: 5px;
  }
  .level-text {
    float: left;
    box-sizing: border-box;
  }
  .level-percent {
    float: right;
    box-sizing: border-box;
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
  height: 580px;
  margin-top: -20px;
}
</style>
