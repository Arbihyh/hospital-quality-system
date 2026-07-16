<template>
  <div class="dashboard-container" :class="{ nocopy: $route.meta.nocopy }">
    <div class="block">
      <div class="barBtn-title">住院医嘱查询</div>
      <div class="bnh"></div>
      <div class="barBtn">
        <el-form ref="form" :model="formData1" label-width="100px">
          <el-form-item v-for="(item, index) in formData1.seniorList" :key="index">
            <!-- 下拉框开始 -->
            <el-select v-model="item.select_type" class="width100 marginLeft" filterable placeholder="">
              <!-- fieldList -->
              <el-option label="且" :value="0" />
              <el-option label="或" :value="1" />
              <el-option label="不包含" :value="2" />
            </el-select>
            <el-select v-model="item.key" class="width150" filterable placeholder="请选择" @change="funSelect(item,index)">
              <!-- fieldList -->
              <el-option v-for="(item, index) in fieldList" :key="index" :label="item.name" :value="item.id" />
            </el-select>
            <!-- 下拉框结束 -->

            <span class="pind10" />
            <!-- 中间选择输入框开始 -->
            <span v-if="item.key == 'YZQX'">
              <el-select class="width150" filterable v-model="item.value" placeholder="请选择">
                <el-option v-for="(item, index) in YZQXList" :key="index" :label="item.val" :value="item.id"></el-option>
              </el-select>
            </span>
            <span v-else-if="['MD_ICD10_NAME', 'MD_ICD10_ID1', 'MO_ICD9_NAME', 'MO_SO_ICD9_NAME'].includes(item.key)">
              <big-data-remote-select :ref="`bigDataRemoteSelectRef_${index}`" v-model="item.value" :api-url="isContainICU(item.key)" placeholder="请选择"></big-data-remote-select>
            </span>
            <span v-else-if="item.key == 'KZKS'">
              <el-select class="width150" multiple collapse-tags filterable v-model="item.value" placeholder="请选择">
                <el-option v-for="(item, index) in KZKSList" :key="index" :label="item.val" :value="item.id"></el-option>
              </el-select>
            </span>
            <span v-else-if="item.key == 'BRKS'">
              <el-select class="width150" multiple collapse-tags filterable v-model="item.value" placeholder="请选择">
                <el-option v-for="(item, index) in BRKSList" :key="index" :label="item.val" :value="item.id"></el-option>
              </el-select>
            </span>
            <span v-else-if="item.key == 'YYSX'">
              <el-select class="width150" filterable v-model="item.value" placeholder="请选择">
                <el-option v-for="(item, index) in YYSXList" :key="index" :label="item.val" :value="item.id"></el-option>
              </el-select>
            </span>
            <span v-else-if="item.key == 'XMLB'">
              <el-select class="width150" filterable v-model="item.value" placeholder="请选择">
                <el-option v-for="(item, index) in XMLBList" :key="index" :label="item.val" :value="item.id"></el-option>
              </el-select>
            </span>
            <span v-else-if="item.key == 'MD_RYQK'">
              <el-select class="width150" filterable v-model="item.value" placeholder="请选择">
                <el-option v-for="(item, index) in MD_RYQKList" :key="index" :label="item.val" :value="item.id"></el-option>
              </el-select>
            </span>
            <span v-else-if="item.key == 'OD_RYQK'">
              <el-select class="width150" filterable v-model="item.value" placeholder="请选择">
                <el-option v-for="(item, index) in OD_RYQKList" :key="index" :label="item.val" :value="item.id"></el-option>
              </el-select>
            </span>

            <span v-else-if="item.key == 'MO_OPE_LEVEL'">
              <el-select class="width150" filterable v-model="item.value" placeholder="请选择">
                <el-option v-for="(item, index) in MO_OPE_LEVELList" :key="index" :label="item.val" :value="item.id"></el-option>
              </el-select>
            </span>
            <span v-else-if="item.key == 'MO_SSPB'">
              <el-select class="width150" filterable v-model="item.value" placeholder="请选择">
                <el-option v-for="(item, index) in MO_SSPBList" :key="index" :label="item.val" :value="item.id"></el-option>
              </el-select>
            </span>
            <span v-else-if="item.key == 'MO_OPE_TYPE'">
              <el-select class="width150" filterable v-model="item.value" placeholder="请选择">
                <el-option v-for="(item, index) in MO_OPE_TYPEList" :key="index" :label="item.val" :value="item.id"></el-option>
              </el-select>
            </span>
            <span v-else-if="item.key == 'SO_OPE_LEVEL'">
              <el-select class="width150" filterable v-model="item.value" placeholder="请选择">
                <el-option v-for="(item, index) in SO_OPE_LEVELList" :key="index" :label="item.val" :value="item.id"></el-option>
              </el-select>
            </span>
            <span v-else-if="item.key == 'SO_SSPB'">
              <el-select class="width150" filterable v-model="item.value" placeholder="请选择">
                <el-option v-for="(item, index) in SO_SSPBList" :key="index" :label="item.val" :value="item.id"></el-option>
              </el-select>
            </span>
            <span v-else-if="item.key == 'AAD01C'">
              <el-select class="width150" filterable v-model="item.value" placeholder="请选择">
                <el-option v-for="(item, index) in AAD01COptions" :key="index" :label="item.val" :value="item.id"></el-option>
              </el-select>
            </span>
            <span v-else-if="item.key == 'AAB02C'">
              <el-select class="width150" filterable v-model="item.value" placeholder="请选择">
                <el-option v-for="(item, index) in AAB02COptions" :key="index" :label="item.val" :value="item.id"></el-option>
              </el-select>
            </span>
            <span v-else-if="item.key == 'AAC11N'">
              <el-select class="width150" filterable v-model="item.value" placeholder="请选择">
                <el-option v-for="(item, index) in AAC11NOptions" :key="index" :label="item.val" :value="item.id"></el-option>
              </el-select>
            </span>

            <span v-else-if="item.key == 'SO_OPE_TYPE'">
              <el-select class="width150" filterable v-model="item.value" placeholder="请选择">
                <el-option v-for="(item, index) in SO_OPE_TYPEList" :key="index" :label="item.val" :value="item.id"></el-option>
              </el-select>
            </span>
            <span v-else-if="item.key == 'MO_SO_OPE_LEVEL'">
              <el-select class="width150" filterable v-model="item.value" placeholder="请选择">
                <el-option v-for="(item, index) in MO_SO_OPE_LEVELList" :key="index" :label="item.val" :value="item.id"></el-option>
              </el-select>
            </span>
            <span v-else-if="item.key == 'MO_SO_SSPB'">
              <el-select class="width150" filterable v-model="item.value" placeholder="请选择">
                <el-option v-for="(item, index) in MO_SO_SSPBList" :key="index" :label="item.val" :value="item.id"></el-option>
              </el-select>
            </span>
            <span v-else-if="item.key == 'MO_SO_OPE_TYPE'">
              <el-select class="width150" filterable v-model="item.value" placeholder="请选择">
                <el-option v-for="(item, index) in MO_SO_OPE_TYPEList" :key="index" :label="item.val" :value="item.id"></el-option>
              </el-select>
            </span>
            <span v-else-if="item.key == 'MO_SO_RJSS'">
              <el-select class="width150" filterable v-model="item.value" placeholder="请选择">
                <el-option v-for="(item, index) in MO_SO_RJSSList" :key="index" :label="item.val" :value="item.id"></el-option>
              </el-select>
            </span>
            <span v-else-if="item.key == 'AEM01C'">
              <el-select class="width150" filterable v-model="item.value" placeholder="请选择">
                <el-option v-for="(item, index) in AEM01CList" :key="index" :label="item.val" :value="item.id"></el-option>
              </el-select>
            </span>
            <span v-else-if="item.key == 'AAB06C'">
              <el-select class="width150" filterable v-model="item.value" placeholder="请选择">
                <el-option v-for="(item, index) in AAB06CList" :key="index" :label="item.val" :value="item.id"></el-option>
              </el-select>
            </span>
            <span v-else>
              <el-input class="width150" v-model="item.value" placeholder="请输入"></el-input>
            </span>

            <!-- 中间选择输入框结束 -->

            <span class="pind10" />
            <!-- 条件下拉开始 -->
            <!-- <el-select v-model="item.type" class="width90" placeholder="">
              <el-option label="精确" value="1" />
              <el-option label="模糊" value="0" />
            </el-select> -->
            <!-- 条件下拉结束 -->

            <span class="pind10" />
            <!-- 增减重置选项按钮开始 -->
            <span>
              <el-button :disabled="formData1.seniorList.length == 1" type="primary" icon="el-icon-minus" @click="funDel(index)" />
              <el-button type="primary" icon="el-icon-plus" @click="funAdd" />
            </span>
            <!-- 增减重置选项按钮结束 -->
          </el-form-item>
          <el-form-item label="出院时间">
            <el-date-picker v-model="formData1.startTime" type="date" format="yyyy 年 MM 月 dd 日" value-format="yyyyMMdd" placeholder="开始日期" :picker-options="pickerOptions" />
            <span class="pind10" />
            <el-date-picker v-model="formData1.endTime" type="date" format="yyyy 年 MM 月 dd 日" value-format="yyyyMMdd" placeholder="结束日期" :picker-options="pickerOptions" />
          </el-form-item>

          <el-form-item label="住院天数">
            <el-input-number v-model="formData1.AAC04_start" :min="1" :step="1" :controls="false" placeholder="起始天数" style="width: 220px"></el-input-number>
            <span class="pind10" />
            <el-input-number v-model="formData1.AAC04_end" :min="1" :step="1" :controls="false" placeholder="终止天数" style="width: 220px"></el-input-number>
          </el-form-item>

          <el-form-item label="患者年龄">
            <el-input-number
              v-model="formData1.AAA04_start"
              :min="1"
              :step="1"
              :controls="false"
              :placeholder="formData1.ageType1 ? '起始年龄' : '起始天数'"
              style="width: 220px"
            ></el-input-number>
            <el-select v-model="formData1.ageType1" placeholder="请选择" @change="ageTypeChange1" style="width: 80px">
              <el-option label="年龄" :value="1"></el-option>
              <el-option label="天数" :value="0"></el-option>
            </el-select>
            <span class="pind10" />
            ——
            <span class="pind10" />
            <el-input-number
              v-model="formData1.AAA04_end"
              :min="1"
              :step="1"
              :controls="false"
              :placeholder="formData1.ageType2 ? '终止年龄' : '终止天数'"
              style="width: 220px"
            ></el-input-number>
            <el-select v-model="formData1.ageType2" placeholder="请选择" @change="ageTypeChange2" style="width: 80px">
              <el-option label="年龄" :value="1"></el-option>
              <el-option label="天数" :value="0"></el-option>
            </el-select>
          </el-form-item>
          <el-form-item label="住院次数">
            <el-input-number v-model="formData1.AAA29_start" :min="1" :step="1" :controls="false" placeholder="住院次数" style="width: 220px"></el-input-number>
            <span class="pind10" />
            <el-input-number v-model="formData1.AAA29_end" :min="1" :step="1" :controls="false" placeholder="住院次数" style="width: 220px"></el-input-number>
          </el-form-item>
          <el-form-item label="开嘱时间">
            <el-date-picker
              v-model="formData1.KZSJ_start"
              type="date"
              format="yyyy 年 MM 月 dd 日"
              value-format="yyyyMMdd"
              placeholder="开始日期"
              :picker-options="pickerOptions"
            />
            <span class="pind10" />
            <el-date-picker v-model="formData1.KZSJ_end" type="date" format="yyyy 年 MM 月 dd 日" value-format="yyyyMMdd" placeholder="结束日期" :picker-options="pickerOptions" />
          </el-form-item>
        </el-form>
      </div>
      <div class="fBtn" style="position: relative">
        <el-button style="position: absolute; right: 0px" @click="reset">重置条件</el-button>
        <el-button type="primary" class="long-btn" @click="seachFunQuery">检索</el-button>
      </div>
    </div>
    <div class="tableBox">
      <div class="flextab" style="margin: 0; margin-bottom: 15px">
        <div class="flextabtitle-box">
          <div class="h-title">
            <span class="blue"></span>
            <span class="text">住院医嘱列表</span>
            <span class="titleName" v-if="paginationData.total != 0">
              例数:
              <span class="title-left-span">{{ paginationData.total }}</span>
              例
            </span>
          </div>
          <!-- 不登陆访问没有导出 -->
          <el-button v-if="!isWhitelist" class="title-left-btn export-btn" icon="el-icon-download" @click="funExport()">导出数据</el-button>
        </div>
      </div>

      <div class="conter" style="border: none">
        <el-table ref="multipleTable" :data="tableData" tooltip-effect="dark" style="width: 100%" @selection-change="handleSelectionChange" @sort-change="handleSortChange">
          <el-table-column type="index" :index="indexAdd" label="序号" width="70px"></el-table-column>
          <el-table-column prop="AAA28" label="病案号">
            <template slot-scope="scope">
              <span class="blue" @click="funGoto(scope.row.ZYH)">
                <template>
                  <div>
                    {{ scope.row.AAA28 }}
                  </div>
                </template>
              </span>
            </template>
          </el-table-column>
          <el-table-column prop="AAB01" label="入院时间" sortable></el-table-column>
          <el-table-column prop="YZMC" label="医嘱名称" sortable></el-table-column>
          <!-- <el-table-column prop="YCJL" label="剂量"></el-table-column>
          <el-table-column prop="SYPC" label="用法"></el-table-column> -->
          <el-table-column prop="BRKS" label="病人科室" sortable></el-table-column>
          <el-table-column prop="KZKS" label="开嘱科室" sortable></el-table-column>
          <el-table-column prop="YZQX" label="医嘱期效"></el-table-column>
          <el-table-column prop="AAC01" label="出院时间" sortable></el-table-column>
        </el-table>
        <!-- 分页控制 -->
      </div>
      <el-pagination
        v-if="tableData && tableData.length !== 0"
        @size-change="SizeChangeEvent"
        @current-change="pageHasChanged"
        :total="paginationData.total"
        background
        class="table-pagination"
        style="margin: 15px 0px"
        :page-size="paginationData.pageSize"
        :current-page.sync="paginationData.currentPage"
        layout="total, sizes, prev, pager, next, jumper"
      ></el-pagination>
    </div>
  </div>
</template>

<script>
import { downloadFile } from '@/httpFile';
import Title from '@/components/Title';
import { mapGetters } from 'vuex';
import mPagination from '@/components/m-pagination';
import { doctorAdviceExport } from '@/api/excel';
import BigDataRemoteSelect from '@/components/BigDataRemoteSelect';

export default {
  name: 'adviceSearch',
  props: {
    isWhitelist: {
      type: Boolean,
      default() {
        return false;
      },
    },
  },
  components: {
    Title,
    mPagination,
    BigDataRemoteSelect,
  },
  computed: {
    ...mapGetters(['name']),
  },
  data() {
    return {
      choice: 0,
      formData: {
        endTime: '',
        startTime: '',
        seniorList: [
          {
            key: '',
            value: '',
            type: '0',
          },
        ],
      },
      tableData: [],
      paginationData: {
        total: 0,
        currentPage: 1,
        pageSize: 10,
      },
      fieldList: [],
      multipleSelection: [],
      setList: [],
      YZQXList: [], //医嘱起效列表
      KZKSList: [], //开嘱科室列表
      BRKSList: [], //病人列表
      YYSXList: [], //用药属性
      XMLBList: [], // 项目类别
      MD_RYQKList: [], // 主要诊断入院情况
      OD_RYQKList: [], // 其他诊断入院情况
      MO_OPE_LEVELList: [], // 主要手术级别
      MO_SSPBList: [], // 主要手术判别
      MO_OPE_TYPEList: [], // 主要手术类型
      SO_OPE_LEVELList: [], // 其他手术手术级别L
      SO_SSPBList: [], // 其他手术判别
      SO_OPE_TYPEList: [], // 其他手术类型
      MO_SO_OPE_LEVELList: [], // 手术级别
      MO_SO_SSPBList: [], // 手术判别
      MO_SO_OPE_TYPEList: [], // 手术类型
      MO_SO_RJSSList: [], // 是否为日间手术

      AAD01COptions: [], //转科科室
      AAB02COptions: [], //入院科室
      AAC11NOptions: [], //出院科室

      AEM01CList: [], // 离院方式
      AAB06CList: [], // 入院途径
      formData1: {
        // 搜搜条件
        ageday: '',
        age_start_type: 2,
        age_end_type: 2,
        ageyear: '',
        endTime: undefined,
        startTime: undefined,
        seniorList: [
          {
            select_type: 0,
            key: '',
            value: '',
            // type: '0',
          },
        ],
        seniorList1: [],
        hospitalizationon: '',
        hospitalizationin: '',
        AAC04_start: undefined,
        AAC04_end: undefined,
        AAA04_start: undefined,
        AAA04_end: undefined,
        ageType1: 1,
        ageType2: 1,
        AAA29_start: undefined, // 住院次数
        AAA29_end: undefined, // 住院次数
        KZSJ_start: undefined, // 开嘱时间
        KZSJ_end: undefined, // 开嘱时间
        order_by_field: 'AAC01',
        order_by_sort: 'desc',
      },

      departmentList: [],
      searchNum: 0,
      pickerOptions: {
        disabledDate(time) {
          return time.getTime() > Date.now();
        },
      },
      is_tm_path: ['/hospital-caseViews', '/embedIndex-caseViews', '/reviewIndex-caseViews', '/whitelist-caseViews', '/whitelist-search'],
    };
  },
  mounted() {
    this.funQuery();
  },
  created() {
    this.searchCondition();
  },
  methods: {
    // table 字段排序
    handleSortChange(column) {
      const { prop, order } = column;
      if (order === 'descending') {
        this.formData1.order_by_sort = 'desc';
      } else if (order === 'ascending') {
        this.formData1.order_by_sort = 'asc';
      } else {
        this.formData1.order_by_sort = 'desc';
      }
      this.formData1.order_by_field = prop || 'AAC01';
      this.seachFunQuery();
    },
    funGoto(val) {
      this.storageSet('getData', val);
      const { path } = this.$route;
      console.log('funGoto', path);
      let toPath;
      if (path === '/hospital-search') {
        toPath = '/hospital-caseViews';
      } else if (path === '/whitelist-search') {
        toPath = '/whitelist-caseViews';
      } else {
        toPath = '/caseViews?pageType=search';
      }
      this.$router.push({ path: toPath, query: { status: 1 } });
    },
    indexAdd(index) {
      return index + 1 + (this.paginationData.currentPage - 1) * this.paginationData.pageSize;
    },
    isContainICU(key) {
      //诊断名称
      if (['MD_ICD10_NAME'].includes(key)) {
        return '/icd10DiagnosisList';
      }
      //诊断编码
      if (['MD_ICD10_ID1'].includes(key)) {
        return '/icd10DiagnosisCodeList';
      }

      //手术名称
      if (['MO_ICD9_NAME', 'MO_SO_ICD9_NAME'].includes(key)) {
        return '/icd09OperationNameList';
      }
    },
    funSelect(item,index, e) {
      const targetKeyList = ['MD_ICD10_NAME', 'MD_ICD10_ID1', 'MO_ICD9_NAME', 'MO_SO_ICD9_NAME'];

      if (targetKeyList.includes(item.key)) {
        this.$nextTick(() => {
          const refName = `bigDataRemoteSelectRef_${index}`;
          const targetSelect = this.$refs[refName];
          const selectInstance = Array.isArray(targetSelect) ? targetSelect[0] : targetSelect;
          selectInstance.currentKeyword = '';
          selectInstance.initData();
        });
      }

      this.$nextTick();
      this.formData1.seniorList[index].value = '';
    },

    funSetList() {},

    handleSelectionChange(val) {
      this.multipleSelection = val;
    },
    SizeChangeEvent(val) {
      this.paginationData.pageSize = val;
      this.funQuery();
    },
    pageHasChanged() {
      this.funQuery();
    },
    seachFunQuery() {
      this.paginationData.currentPage = 1;
      this.funQuery();
    },
    funDel(i) {
      const index = i;
      if (index == 0) {
        if (this.formData1.seniorList.length >= 2) {
          this.formData1.seniorList.pop();
        }
        return;
      }
      const list = this.formData1.seniorList;
      list.splice(index, 1);
      this.formData1.seniorList = list;
    },
    funAdd() {
      this.formData1.seniorList.push({
        key: '',
        select_type: 0,
        value: '',
        // type: '0',
      });
    },
    funQuery() {
      let pramse = {
        page_size: this.paginationData.pageSize,
        page: this.paginationData.currentPage, //是当前页数 默认是0 。普通检索的参数是
      };

      if (this.$route.query.code) {
        pramse.code = this.$route.query.code;
      }
      // 处理 field  字段等于空的时候
      let fieldArr = [];
      this.formData1.seniorList.forEach((item, index) => {
        if (item.key != '') {
          fieldArr.push(item);
        }
      });
      if (fieldArr != '' && fieldArr != null) {
        pramse.field = fieldArr;
      }

      pramse.AAC01_start = this.formData1.startTime;
      pramse.AAC01_end = this.formData1.endTime;

      pramse.AAC04_start = this.formData1.AAC04_start;
      pramse.AAC04_end = this.formData1.AAC04_end;

      pramse.AAA29_start = this.formData1.AAA29_start;
      pramse.AAA29_end = this.formData1.AAA29_end;

      pramse.KZSJ_start = this.formData1.KZSJ_start;
      pramse.KZSJ_end = this.formData1.KZSJ_end;
      pramse.order_by_sort = this.formData1.order_by_sort;
      pramse.order_by_field = this.formData1.order_by_field;

      const { ageType1, ageType2 } = this.formData1;
      if (this.formData1.AAA04_start) {
        pramse.AAA04_start = { type: ageType1, value: this.formData1.AAA04_start };
      }
      if (this.formData1.AAA04_end) {
        pramse.AAA04_end = { type: ageType2, value: this.formData1.AAA04_end };
      }
      if (this.is_tm_path.includes(this.$route.path)) {
        pramse.is_tm = 1;
      }
      this.$axios3.post('/yz/serach', pramse).then(res => {
        this.tableData = res.data.list || [];
        this.paginationData.total = res.data.total;
      });
    },

    reset() {
      // 重置数据
      this.paginationData.currentPage = 1;
      Object.assign(this.$data.formData1, this.$options.data().formData1);
      this.funQuery();
    },
    searchCondition() {
      this.$axios3.post('/yz/serach_where').then(res => {
        res.data.forEach((item, index) => {
          item.id = item.key;
          if (item.key == 'YZQX') {
            let YZQXArr = Object.keys(item.value);
            YZQXArr.forEach((jitem, index) => {
              this.YZQXList.push({ id: jitem, val: item.value[jitem] });
            });
          }
          if (item.key == 'KZKS') {
            let KZKSArr = Object.keys(item.value);
            KZKSArr.forEach((jitem, index) => {
              this.KZKSList.push({ id: jitem, val: item.value[jitem] });
            });
          }
          if (item.key == 'BRKS') {
            let BRKSArr = Object.keys(item.value);
            BRKSArr.forEach((jitem, index) => {
              this.BRKSList.push({ id: jitem, val: item.value[jitem] });
            });
          }
          if (item.key == 'YYSX') {
            let YYSXArr = Object.keys(item.value);
            YYSXArr.forEach((jitem, index) => {
              this.YYSXList.push({ id: jitem, val: item.value[jitem] });
            });
          }
          if (item.key == 'XMLB') {
            let XMLBArr = Object.keys(item.value);
            XMLBArr.forEach((jitem, index) => {
              this.XMLBList.push({ id: jitem, val: item.value[jitem] });
            });
          }
          if (item.key == 'MD_RYQK') {
            let MD_RYQKArr = Object.keys(item.value);
            MD_RYQKArr.forEach((jitem, index) => {
              this.MD_RYQKList.push({ id: jitem, val: item.value[jitem] });
            });
          }
          if (item.key == 'OD_RYQK') {
            let OD_RYQKArr = Object.keys(item.value);
            OD_RYQKArr.forEach((jitem, index) => {
              this.OD_RYQKList.push({ id: jitem, val: item.value[jitem] });
            });
          }

          if (item.key == 'MO_OPE_LEVEL') {
            let MO_OPE_LEVELArr = Object.keys(item.value);
            MO_OPE_LEVELArr.forEach((jitem, index) => {
              this.MO_OPE_LEVELList.push({ id: jitem, val: item.value[jitem] });
            });
          }
          if (item.key == 'MO_SSPB') {
            let MO_SSPBArr = Object.keys(item.value);
            MO_SSPBArr.forEach((jitem, index) => {
              this.MO_SSPBList.push({ id: jitem, val: item.value[jitem] });
            });
          }
          if (item.key == 'MO_OPE_TYPE') {
            let MO_OPE_TYPEArr = Object.keys(item.value);
            MO_OPE_TYPEArr.forEach((jitem, index) => {
              this.MO_OPE_TYPEList.push({ id: jitem, val: item.value[jitem] });
            });
          }
          if (item.key == 'SO_OPE_LEVEL') {
            let SO_OPE_LEVELArr = Object.keys(item.value);
            SO_OPE_LEVELArr.forEach((jitem, index) => {
              this.SO_OPE_LEVELList.push({ id: jitem, val: item.value[jitem] });
            });
          }
          if (item.key == 'SO_SSPB') {
            let SO_SSPBArr = Object.keys(item.value);
            SO_SSPBArr.forEach((jitem, index) => {
              this.SO_SSPBList.push({ id: jitem, val: item.value[jitem] });
            });
          }
          if (item.key == 'SO_OPE_TYPE') {
            let SO_OPE_TYPEArr = Object.keys(item.value);
            SO_OPE_TYPEArr.forEach((jitem, index) => {
              this.SO_OPE_TYPEList.push({ id: jitem, val: item.value[jitem] });
            });
          }
          if (item.key == 'MO_SO_OPE_LEVEL') {
            let MO_SO_OPE_LEVELArr = Object.keys(item.value);
            MO_SO_OPE_LEVELArr.forEach((jitem, index) => {
              this.MO_SO_OPE_LEVELList.push({ id: jitem, val: item.value[jitem] });
            });
          }
          if (item.key == 'MO_SO_SSPB') {
            let MO_SO_SSPBArr = Object.keys(item.value);
            MO_SO_SSPBArr.forEach((jitem, index) => {
              this.MO_SO_SSPBList.push({ id: jitem, val: item.value[jitem] });
            });
          }

          if (item.key == 'AAD01C') {
            let AAD01COptions = Object.keys(item.value);
            AAD01COptions.forEach((jitem, index) => {
              this.AAD01COptions.push({ id: jitem, val: item.value[jitem] });
            });
          }
          if (item.key == 'AAB02C') {
            let AAB02COptions = Object.keys(item.value);
            AAB02COptions.forEach((jitem, index) => {
              this.AAB02COptions.push({ id: jitem, val: item.value[jitem] });
            });
          }
          if (item.key == 'AAC11N') {
            let AAC11NOptions = Object.keys(item.value);
            AAC11NOptions.forEach((jitem, index) => {
              this.AAC11NOptions.push({ id: jitem, val: item.value[jitem] });
            });
          }

          if (item.key == 'MO_SO_OPE_TYPE') {
            let MO_SO_OPE_TYPEArr = Object.keys(item.value);
            MO_SO_OPE_TYPEArr.forEach((jitem, index) => {
              this.MO_SO_OPE_TYPEList.push({ id: jitem, val: item.value[jitem] });
            });
          }
          if (item.key == 'MO_SO_RJSS') {
            let MO_SO_RJSSArr = Object.keys(item.value);
            MO_SO_RJSSArr.forEach((jitem, index) => {
              this.MO_SO_RJSSList.push({ id: jitem, val: item.value[jitem] });
            });
          }

          if (item.key == 'AEM01C') {
            let AEM01CArr = Object.keys(item.value);
            AEM01CArr.forEach((jitem, index) => {
              this.AEM01CList.push({ id: jitem, val: item.value[jitem] });
            });
          }
          if (item.key == 'AAB06C') {
            let AAB06CArr = Object.keys(item.value);
            AAB06CArr.forEach((jitem, index) => {
              this.AAB06CList.push({ id: jitem, val: item.value[jitem] });
            });
          }
        });
        this.fieldList = res.data;
      });
    },

    funExport() {
      //查询
      let pramse = {
        page_size: this.paginationData.pageSize,
        page: this.paginationData.currentPage, //是当前页数 默认是0 。普通检索的参数是
      };

      // 处理 field  字段等于空的时候
      let fieldArr = [];
      this.formData1.seniorList.forEach((item, index) => {
        if (item.key != '') {
          fieldArr.push(item);
        }
      });
      if (fieldArr != '' && fieldArr != null) {
        pramse.field = fieldArr;
      }
      if (this.formData1.startTime) {
        pramse.AAC01_start = this.formData1.startTime;
      }
      if (this.formData1.endTime) {
        pramse.AAC01_end = this.formData1.endTime;
      }
      if (this.formData1.AAC04_start) {
        pramse.AAC04_start = this.formData1.AAC04_start;
      }
      if (this.formData1.AAC04_end) {
        pramse.AAC04_end = this.formData1.AAC04_end;
      }
      if (this.formData1.AAA29_start) {
        pramse.AAA29_start = this.formData1.AAA29_start;
      }
      if (this.formData1.AAA29_end) {
        pramse.AAA29_end = this.formData1.AAA29_end;
      }
      if (this.formData1.KZSJ_start) {
        pramse.KZSJ_start = this.formData1.KZSJ_start;
      }
      if (this.formData1.KZSJ_end) {
        pramse.KZSJ_end = this.formData1.KZSJ_end;
      }

      const { ageType1, ageType2 } = this.formData1;
      if (this.formData1.AAA04_start) {
        pramse.AAA04_start = { type: ageType1, value: this.formData1.AAA04_start };
      }
      if (this.formData1.AAA04_end) {
        pramse.AAA04_end = { type: ageType2, value: this.formData1.AAA04_end };
      }

      doctorAdviceExport(pramse).then(res => {
        const content = res.data; // 后台返回二进制数据
        const blob = new Blob([content]);
        const fileName = `病案医嘱列表.csv`;
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

    ageTypeChange1() {
      this.formData1.ageType2 = this.formData1.ageType1;
    },
    ageTypeChange2() {
      this.formData1.ageType1 = this.formData1.ageType2;
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
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
  }
  .bnh {
    margin: 0 auto;
    margin-bottom: 20px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
  }
  .barBtn {
    display: flex;
    align-items: center;
    justify-content: center;
  }
  .barBtn-title {
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    font-weight: bold;
    margin-top: 10px;
    margin-bottom: 20px;
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
.width100 {
  width: 100px;
}
.width150 {
  width: 200px;
}
.width300 {
  width: 295px;
}
.width500 {
  width: 420px;
}
.width90 {
  width: 90px;
}
.blue {
  color: #185da6;
}
.h-title {
  display: flex;
  .blue {
    display: block;
    width: 6px;
    height: 17px;
    background: linear-gradient(180deg, #185da6 0%, #3195ff 100%);
    border-radius: 3px;
  }
  .text {
    font-size: 16px;
    font-weight: 600;
    color: #13171e;
    margin: 0 0 0 14px;
  }
}
.flextabtitle-box {
  display: flex;
  align-items: center;
  justify-content: space-between;
  width: 100%;
}
.flextab-item {
  display: flex;
  align-items: center;
  margin-left: 20px;
}
.flextab-item > div {
  font-size: 15px;
  margin-right: 15px;
}
.flextab-item > div span.s-1 {
  color: #185da6;
}
.flextab-item > div span.s-2 {
  font-weight: bold;
}
.inputOn {
  width: 200px;
  margin: 10px 10px;
}
.search-title {
  padding: 20px 10px;
  // width: 900px;
  display: flex;
  justify-content: space-between;
}
.search-title-span {
  font-size: 20px;
  font-weight: 600;
  color: #13171e;
}
.conter {
  position: relative;
  margin: 20px 0;
  border: 1px solid skyblue;
}
.conter-title {
  font-size: 16px;
  font-weight: 600;
  color: #13171e;
}
.blue {
  color: #185da6;
  font-size: 16px;
  font-weight: 600;
}
.conter-case {
  margin: 20px 0;
  font-size: 15px;
  text-indent: 30px;
  line-height: 30px;
}
.conter-case1 {
  margin: 0 auto;
  margin: 50px 0;
}
.yeleou {
  font-size: 16px;
  color: rgb(233, 157, 66);
}
.conter-num {
  font-size: 15px;
  padding-top: 30px;
  font-weight: 600;
}
.conter-time {
  font-size: 15px;
  padding-top: 10px;
  font-weight: 600;
}
.onQuery {
  padding: 0 20px;
  color: rgb(233, 157, 66);
}
.cont-title {
  width: 100%;
  padding: 20px;
  display: flex;
  justify-content: space-between;
}
.title-left {
  display: flex;
}
.title-right {
  width: 300px;
  display: flex;
  & > div {
    width: 150px;
    border: 1px solid #d5e4ff;
    text-align: center;
    padding: 10px 0;
  }
}
.title-right-data {
  background: #3195ff;
  color: #fff;
}
.title-left-p {
  line-height: 38px;
  padding: 0 20px;
  font-size: 16px;
  color: #13171e;
}
.title-left-span {
  color: #3195ff;
  font-size: 18px;
  padding: 0 5px;
  // font-weight: 600;
}
.title-left-btn {
  margin: 0 0 0 40px;
  background: #185da6;
  color: #fff;
}
.title-left-checked {
  margin-top: 10px;
}
.conter-checked {
  position: absolute;
  top: 21px;
  left: 30px;
}
.bule {
  background: #3195ff !important;
  color: #fff !important;
}
.titleName {
  margin-left: 30px;
  // margin-top:3px;
}
.marginLeft {
  margin-left: -110px;
}

::v-deep .el-input-number .el-input__inner {
  text-align: left;
}
.sort-icon {
  font-size: 14px;
}
</style>
