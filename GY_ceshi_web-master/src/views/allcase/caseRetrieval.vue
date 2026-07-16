<template>
  <div class="dashboard-container">
    <div class="block">
      <div class="barBtn">
        <el-radio-group v-model="choice" class="bnts" size="medium">
          <el-radio-button :label="0">普通检索</el-radio-button>
          <el-radio-button :label="1">高级检索</el-radio-button>
        </el-radio-group>
      </div>
      <div class="bnh"></div>
      <!-- 普通检索 开始 -->
      <div class="inputs" v-if="choice == 0">
        <el-row :gutter="20" class="rowsa">
          <el-col :span="4">
            <div class="grid-content bg-purple">
              <el-input class="inpus" v-model="formData0.recordNum" placeholder="病案号"></el-input>
            </div>
          </el-col>
          <el-col :span="5">
            <div class="grid-content bg-purple">
              <el-select v-model="formData0.Department" filterable class="selects" placeholder="出院科室">
                <el-option v-for="(item, index) in departmentList" :label="item.name" :value="item.id"
                  :key="index"></el-option>
              </el-select>
            </div>
          </el-col>
          <el-col :span="5">
            <div class="grid-content bg-purple">
              <el-select v-model="formData0.problem" class="selects" placeholder="问题属性">
                <el-option v-for="(item, index) in levelList" :label="item.name" :value="item.id"
                  :key="index"></el-option>
              </el-select>
            </div>
          </el-col>
          <el-col :span="5">
            <div class="grid-content bg-purple">
              <el-select v-model="formData0.payment" filterable class="selects" placeholder="医疗付款方式">
                <el-option v-for="(item, index) in payList" :label="item.name" :value="item.id"
                  :key="index"></el-option>
              </el-select>
            </div>
          </el-col>
          <el-col :span="5">
            <div class="grid-content bg-purple">
              <el-select v-model="formData0.medicalRecord" class="selects" placeholder="全部病案">
                <el-option label="已质控" value="1"></el-option>
                <el-option label="未质控" value="0"></el-option>
              </el-select>
            </div>
          </el-col>
        </el-row>
        <el-row :gutter="20" class="rowsa">
          <el-col :span="5">
            <div class="grid-content bg-purple">
              <el-date-picker class="selects" v-model="formData0.startTime" type="date" format="yyyy 年 MM 月 dd 日"
                value-format="yyyyMMdd" placeholder="开始日期"></el-date-picker>
            </div>
          </el-col>
          <el-col :span="5">
            <div class="grid-content bg-purple">
              <el-date-picker class="selects" v-model="formData0.endTime" type="date" format="yyyy 年 MM 月 dd 日"
                value-format="yyyyMMdd" placeholder="结束日期"></el-date-picker>
            </div>
          </el-col>
        </el-row>
      </div>
      <!-- 普通检索 结束 -->
      <!-- 高级检索 开始 -->
      <div class="barBtn" v-else>
        <el-form ref="form" :model="formData1" label-width="100px">
          <el-form-item v-for="(item, index) in formData1.seniorList" :key="index">
            <!-- 下拉框开始 -->
            <el-select class="width150" filterable v-model="item.key" @change="getOneCleck(item)" placeholder="请选择">
              <!-- fieldList -->
              <el-option v-for="(item, index) in fieldList" :label="item.name" :value="item.id"
                :key="index"></el-option>
            </el-select>
            <!-- 下拉框结束 -->

            <span class="pind10"></span>
            <!-- 中间选择输入框开始 -->
            <span v-if="keyList.includes(item.key)">
              <el-select class="width150" filterable v-model="item.value" placeholder="请选择">
                <el-option v-for="(itemo, indexo) in item.selectList" :key="indexo" :label="itemo.label"
                  :value="itemo.id"></el-option>
              </el-select>
            </span>
            <span v-else>
              <el-input class="width150" v-model="item.value" placeholder=""></el-input>
            </span>
            <!-- 中间选择输入框结束 -->

            <span class="pind10"></span>
            <!-- 条件下拉开始 -->
            <el-select class="width90" v-model="item.type" placeholder="">
              <el-option label="精确" value="1"></el-option>
              <el-option label="模糊" value="0"></el-option>
            </el-select>
            <!-- 条件下拉结束 -->

            <span class="pind10"></span>
            <!-- 增减重置选项按钮开始 -->
            <span>
              <el-button :disabled="formData1.seniorList.length == 1" type="primary" icon="el-icon-minus"
                @click="funDel(index)"></el-button>
              <el-button type="primary" icon="el-icon-plus" @click="funAdd" v-if="index == 0"></el-button>
            </span>
            <!-- 增减重置选项按钮结束 -->

          </el-form-item>
          <el-form-item label="患者年龄">
            <div class="zkSelect">
              <el-input class="width300" v-model="formData1.ageday" :min="28" :max="365" type="number"
                placeholder="<28天" @blur="funBlur">
                <template slot="append">
                  <el-select v-model="formData1.age_start_type" placeholder="请选择">
                    <el-option v-for="item in Dayoptions" :key="item.value" :label="item.label"
                      :value="item.value"></el-option>
                  </el-select>
                </template>
              </el-input>
            </div>
            <!-- <el-input class="width300" v-model="formData1.ageday" :min="28" :max="365" type="number" placeholder="<28天" @blur="funBlur">
              <template slot="append">天</template>
            </el-input> -->
            <span class="pind" style="color: #ccc">——</span>
            <div class="zkSelect">
              <el-input class="width300" v-model="formData1.ageyear" :min="1" :max="150" type="number"
                placeholder="1-150岁" @blur="funBlur">
                <template slot="append">
                  <el-select v-model="formData1.age_end_type" placeholder="请选择">
                    <el-option v-for="item in Dayoptions" :key="item.value" :label="item.label"
                      :value="item.value"></el-option>
                  </el-select>
                </template>
              </el-input>
            </div>
          </el-form-item>
          <el-form-item label="住院天数">
            <div class="zkSelect">
              <el-input class="width300" v-model="formData1.hospitalizationon" :min="28" :max="365" type="number"
                @blur="funBluron">
                <template slot="append">天</template>
              </el-input>
            </div>
            <!-- <el-input class="width300" v-model="formData1.ageday" :min="28" :max="365" type="number" placeholder="<28天" @blur="funBlur">
              <template slot="append">天</template>
            </el-input> -->
            <span class="pind" style="color: #ccc">——</span>
            <div class="zkSelect">
              <el-input class="width300" v-model="formData1.hospitalizationin" :min="1" :max="150" type="number"
                @blur="funBluron">
                <template slot="append">天</template>
              </el-input>
            </div>
          </el-form-item>
          <el-form-item label="时间范围">
            <!-- <el-date-picker
              class="width500"
              v-model="formData1.rangeDate"
              size="large"
              type="daterange"
              range-separator="-"
              start-placeholder="开始日期"
              end-placeholder="结束日期"
              format="yyyy 年 MM 月 dd 日"
              value-format="yyyyMMdd"
            ></el-date-picker> -->
            <el-date-picker v-model="formData1.startTime" type="date" format="yyyy 年 MM 月 dd 日" value-format="yyyyMMdd"
              placeholder="开始日期"></el-date-picker>
            <span class="pind10"></span>
            <el-date-picker v-model="formData1.endTime" type="date" format="yyyy 年 MM 月 dd 日" value-format="yyyyMMdd"
              placeholder="结束日期"></el-date-picker>
          </el-form-item>
        </el-form>
      </div>
      <!-- 高级检索 结束 -->
      <div class="fBtn" style="position: relative">
        <el-button type="primary" @click="funQuery">检索</el-button>
        <el-button style="position: absolute; right: 30px" @click="reset">重置条件</el-button>
      </div>
    </div>
    <div class="tableBox">
      <div class="flextab" style="margin: 0;margin-bottom:15px;">
        <div class="flextabtitle-box">
          <!-- <Title :title="'病案列表'" /> -->
          <div class="h-title">
            <span class="blue"></span>
            <span class="text">病案列表</span>
          </div>
          <div class="flextab-item">
            <div>平均住院日: <span class="s-1">{{ ARG_STAY }}</span><span class="s-2"> 天</span></div>
            <div>平均费用: <span class="s-1">{{ ARG_F_D }}</span><span class="s-2"> 元；</span></div>
            <div>例数: <span class="s-1">{{ paginationData.total ? paginationData.total : 0 }}</span><span class="s-2"> 例</span>
            </div>
            <div>死亡例数: <span class="s-1">{{ AEM01C ? AEM01C : 0 }}</span><span class="s-2"> 例</span></div>
          </div>
        </div>

        <el-button type="primary" icon="el-icon-download" class="export-btn"
          @click="funExport('质控列表', '/qualityList')">导出数据</el-button>
      </div>

      <el-table :data="tableData" border style="width: 100%">
        <el-table-column type="index" label="序号" width="70px"></el-table-column>
        <el-table-column prop="AAA28" label="病案号">
          <template slot-scope="scope">
            <span class="blue" @click="funGoto(scope.row.MED_REC_ID)">{{ scope.row.AAA28 }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="AAA01" label="患者姓名"></el-table-column>
        <el-table-column prop="AAC01" label="出院时间"></el-table-column>
        <el-table-column prop="AAA02C" label="性别"></el-table-column>
        <el-table-column prop="AAA04" label="年龄"></el-table-column>

        <template v-for="(item, ind) in formData1.seniorList">
          <el-table-column :key="ind" v-if="tabKeyList.includes(item.key)" :label="funkdef(item.key)"
            :prop="item.key"></el-table-column>
        </template>
        <el-table-column prop="ABC01N" label="主诊断名称"></el-table-column>
        <el-table-column prop="ABC01C" label="主诊断编码"></el-table-column>
        <el-table-column prop="ICD9_NAME" label="主手术名称"></el-table-column>
        <el-table-column prop="ICD9_ID1" label="主手术编码"></el-table-column>
        <el-table-column v-if="columnShow" :prop="clumText.id" :label="clumText.name"></el-table-column>
        <el-table-column prop="AAA29" label="住院次数"></el-table-column>
        <el-table-column prop="AAC11N" label="出院科室"></el-table-column>
        <el-table-column prop="ADA01" label="住院总费用"></el-table-column>
        <el-table-column prop="F_D" label="药品总费用"></el-table-column>
        <el-table-column prop="J" label="材料总费用"></el-table-column>

        <!-- <el-table-column prop="address" label="业务操作人"> </el-table-column> -->
        <el-table-column prop="AAC04" label="实际住院(天)"></el-table-column>
        <!-- <el-table-column prop="ATTEND_GRP_NAME" label="主诊组"></el-table-column> -->
        <el-table-column prop="AEM01C" label="离院方式"></el-table-column>
        <el-table-column prop="AAB06C" label="入院途径"></el-table-column>
        <!-- <el-table-column prop="ARG_STAY" label="平均住院日"></el-table-column> -->
        <!-- <el-table-column prop="ARG_F_D" label="平均费用"></el-table-column> -->
        <el-table-column prop="SSPB" label="手术判别"></el-table-column>
        <el-table-column prop="AAB11N" label="入院科室"></el-table-column>
        <el-table-column prop="AAB01" label="入院时间"></el-table-column>
      </el-table>
      <!-- 分页控制 -->
      <el-pagination v-if="tableData && tableData.length !== 0" @size-change="SizeChangeEvent"
        @current-change="pageHasChanged" :total="paginationData.total" background class="table-pagination"
        style="margin: 15px 0px" :page-size="paginationData.pageSize" :current-page.sync="paginationData.currentPage"
        layout="total, sizes, prev, pager, next, jumper"></el-pagination>
      <!-- <mPagination style="margin:15px 0px;"
         v-if="tableData && tableData.length !== 0"
          :data="paginationData"
          @SizeChangeEvent="SizeChangeEvent"
           @pageChangeEvent="pageHasChanged"></mPagination> -->
    </div>
  </div>
</template>

<script>
import { downloadFile } from '@/httpFile';
import Title from '@/components/Title';
import { mapGetters } from 'vuex';
import mPagination from '@/components/m-pagination';
// import { json } from 'stream/consumers';

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
      choice: 0,
      clumText: {},
      columnShow: false,
      labelList: ['ICD10_NAME'],
      formData0: {
        recordNum: '', //病案号
        Department: '', //出院科室
        problem: 'all', //问题属性
        payment: '', //医疗付款方式
        // rangeDate: [], //时间
        endTime: '',
        medicalRecord: '', //全部病案
      },
      Dayoptions: [
        {
          value: 1,
          label: '天',
        },
        {
          value: 2,
          label: '岁',
        },
      ],
      formData1: {
        ageday: '',
        age_start_type: 2,
        age_end_type: 2,
        ageyear: '',
        // rangeDate: [],
        endTime: '',
        startTime: '',
        seniorList: [
          {
            key: '',
            value: '',
            type: '0',
            selectList: [],
          },
          {
            key: '',
            value: '',
            type: '0',
            selectList: [],
          },
        ],
        hospitalizationon: '',
        hospitalizationin: '',
      },
      inputOn: '', //全站搜索病案号
      value: '',
      value1: '',
      selectList: [],
      labelText: '',
      keyList: ['OPE_LEVEL', 'SSPB', 'ABC03C', 'RYQK', 'AAA02C', 'RJSS', 'AEM01C', 'AAC11N', 'LNSSQ', 'LNSSH', 'AEL01'],
      tabKeyList: ['ICD10_ID1_first', 'ICD10_NAME_first', 'ICD10_ID1', 'ICD10_NAME', 'ICD9_ID1', 'ICD9_NAME', 'ABC03C', 'RYQK', 'OPE_LEVEL', 'ABA01N', 'ABA01C', 'AEL01', 'RJSS', 'LNSSQ', 'LNSSH'], // 表头key动态展示
      tableData: [],
      payList: [], //支付方式
      departmentList: [], //出院科室
      levelList: [], //问题属性
      fieldList: [], //主要诊断名字
      department: [], //科室
      // 分页数据
      paginationData: {
        total: 10,
        currentPage: 1,
        pageSize: 10,
      },
      ARG_F_D: '',
      ARG_STAY: '',
      AEM01C: '',
    };
  },
  mounted() {

  },
  created() {
    this.funQuery();
    this.selectInfo();
  },
  methods: {
    funkdef(key) {
      console.log(this.fieldList)
      for (let item in this.fieldList) {
        if (this.fieldList[item].id == key) {
          return this.fieldList[item].name;
        }
      }
    },
    /**
     * 根据下拉框选择出现对应数据
     * @param {val} 选中当前
     */
    getOneCleck(val) {
      console.log(val);
      var that = this;
      this.labelText = val.key;
      console.log(this.labelText)
      var text = this.fieldList.filter(item => val.key == item.id);
      that.$nextTick(function () {
        that.clumText = {
          name: text[0].name,
          id: text[0].id,
        };
      });
      // console.error('122222', that.clumText);
      if (val.key == 'OPE_LEVEL') {
        val.selectList = [
          {
            label: '全部',
            id: 0,
          },
          {
            label: '一级手术',
            id: 1,
          },
          {
            label: '二级手术',
            id: 2,
          },
          {
            label: '三级手术',
            id: 3,
          },
          {
            label: '四级手术',
            id: 4,
          },
        ];
      } else if (val.key == 'SSPB') {
        val.selectList = [
          {
            label: '全部',
            id: 5,
          },

          {
            label: '介入治疗',
            id: 4,
          },
          {
            label: '手术',
            id: 1,
          },
          {
            label: '诊断操作',
            id: 2,
          },
          {
            label: '治疗操作',
            id: 3,
          },
          {
            label: '空',
            id: 0,
          },
        ];
      } else if (val.key == 'AAA02C') {
        val.selectList = [
          {
            label: '全部',
            id: 0,
          },
          {
            label: '男',
            id: 1,
          },
          {
            label: '女',
            id: 2,
          },
          {
            label: '未知的性别',
            id: 3,
          },
          {
            label: '未说明的性别',
            id: 4,
          },
        ];
      } else if (val.key == 'RJSS') {
        val.selectList = [
          {
            label: '全部',
            id: 0,
          },
          {
            label: '是',
            id: 1,
          },
          {
            label: '否',
            id: 2,
          },
        ];
      } else if (val.key == 'ABC03C') {
        val.selectList = [
          {
            label: '全部',
            id: 0,
          },
          {
            label: '有',
            id: 1,
          },
          {
            label: '临床未确定',
            id: 2,
          },
          {
            label: '情况不明',
            id: 3,
          },
          {
            label: '无',
            id: 4,
          },
        ];
      } else if (val.key == 'RYQK') {
        val.selectList = [
          {
            label: '全部',
            id: 0,
          },
          {
            label: '有',
            id: 1,
          },
          {
            label: '临床未确定',
            id: 2,
          },
          {
            label: '情况不明',
            id: 3,
          },
          {
            label: '无',
            id: 4,
          },
        ];
      } else if (val.key == 'AEM01C') {
        val.selectList = [
          {
            label: '全部',
            id: 0,
          },
          {
            label: '医嘱离院',
            id: 1,
          },
          {
            label: '医嘱转院',
            id: 2,
          },
          {
            label: '医嘱转社区卫生服务机构/乡镇卫生院',
            id: 3,
          },
          {
            label: '非医嘱离院',
            id: 4,
          },
          {
            label: '死亡',
            id: 5,
          },
          {
            label: '其他',
            id: 6,
          },
        ];
      } else if (val.key == 'AAC11N') {
        for (let item in this.departmentList) {
          this.departmentList[item];
          val.selectList.push({
            label: this.departmentList[item].name,
            id: this.departmentList[item].id,
          });
        }
        // val.selectList = this.departmentList
      } else if (val.key == 'LNSSQ' || val.key == 'LNSSH') {
        val.selectList = [
          {
            label: '全部',
            id: '0',
          },
          {
            label: '有',
            id: '1',
          },
          {
            label: '无',
            id: '2',
          },
        ];
      } else if (val.key == 'AEL01') {
        val.selectList = [
          {
            label: '全部',
            id: '0',
          },
          {
            label: '有',
            id: '1',
          },
          {
            label: '无',
            id: '2',
          },
        ];
      }
      this.$nextTick();
    },
    funRead() {
      //重置
      Object.assign(this.$data.formData1, this.$options.data().formData1);
    },
    funExport(fileName, httpUrl) {
      //查询
      var pramse = {};
      let min = this.formData1.hospitalizationon;
      let max = this.formData1.hospitalizationin;
      if (this.choice == 0) {
        // hospitalizationon: '',
        // hospitalizationin: '',
        pramse = {
          level: this.formData0.problem || null, //问题属性
          AAA28: this.formData0.recordNum || null, //病案号
          AAC11C: this.formData0.Department || null, //出院科室
          AAA26C: this.formData0.payment || null, //付款方式
          AAC01_start_date: this.formData0.startTime || '',
          AAC01_end_date: this.formData0.endTime || '',
          ORG_STATE: this.formData0.medicalRecord || null, //全部病案
          page: this.paginationData.currentPage, //页码
          limit: this.paginationData.pageSize, //条数
          is_export: 1,
        };
      } else {
        pramse = {
          AAC04: `${min ? min : 0}-${max ? max : 0}`,
          AAC0401: `${min ? min : 0}`,
          AAC0402: `${max ? max : 0}`,
          AAA04: this.formData1.ageyear || null, //年龄
          AAA40: this.formData1.ageday || null, //不足一周岁年龄
          age_start_type: this.formData1.age_start_type || null,
          age_end_type: this.formData1.age_end_type || null,
          AAC01_start_date: this.formData1.startTime || '',
          AAC01_end_date: this.formData1.endTime || '',
          field: this.formData1.seniorList || null, //字段条件
          page: this.paginationData.currentPage, //页码
          limit: this.paginationData.pageSize, //条数
          is_export: 1,
        };
      }
      console.error('pramse', pramse);
      this.funExeclPost(fileName, pramse, httpUrl, 'xlsx');
    },
    funExeclPost(fileName, pramse, httpUrl, format) {
      //导出
      let httpUrls = '/api' + httpUrl;
      downloadFile(httpUrls, pramse, format, fileName).then(res => {
        console.error('111', res);
      });
    },
    funGoto(val) {
      this.storageSet('getData', val);
      console.log(val);
      console.log(this.storageGet('getData'));
      // this.goto('/details');
      this.$router.push('/details');

    },
    funBlur() {
      if (this.formData1.ageday > 356) {
        this.formData1.ageday = 356;
      }
      if (this.formData1.ageyear > 150) {
        this.formData1.ageyear = 150;
      }
    },
    funBluron() {
      if (this.formData1.hospitalizationon && this.formData1.hospitalizationin) {
        if (this.formData1.hospitalizationon > this.formData1.hospitalizationin) {
          this.$message('起止住院天数输入不正确');
        }
      }

      // this.formData1.hospitalizationon || '0' + '-' + this.formData1.hospitalizationin || '0',
    },
    funDel(i) {
      let index = i;
      if (index == 0) {
        if (this.formData1.seniorList.length >= 2) {
          this.formData1.seniorList.pop();
        }
        return
      }
      let list = this.formData1.seniorList;
      list.splice(index, 1);
      this.formData1.seniorList = list;
    },
    funAdd() {
      this.formData1.seniorList.push({
        key: '',
        value: '',
        type: '0',
        selectList: [],
      });
    },
    pageHasChanged() {
      this.funQuery();
    },
    SizeChangeEvent(val) {
      this.paginationData.pageSize = val;
      this.funQuery();
    },
    selectInfo() {
      // let pramse = {};
      this.$axios.post('/selectInfo').then(res => {
        this.payList = res.data.pay;
        console.log(this.payList);
        //支付方式 pay
        this.departmentList = res.data.department;
        //出院科室 department
        this.levelList = res.data.level;
        //问题属性 level
        this.coderList = res.data.coder;
        //编码元  coder
        this.statusList = res.data.status;
        this.fieldList = res.data.field;
        // this.department = res.data.department
      });
    },
    // 点击检索按钮
    funQuery() {
      console.error('this.choice111', this.choice);
      let min = this.formData1.hospitalizationon;
      let max = this.formData1.hospitalizationin;
      if (this.labelList.includes(this.labelText)) {
        this.columnShow = true;
      } else {
        this.columnShow = false;
      }
      //查询
      if (this.choice == 0) {
        let pramse = {
          level: this.formData0.problem || null, //问题属性
          AAA28: this.formData0.recordNum || null, //病案号
          AAC11C: this.formData0.Department || null, //出院科室
          AAA26C: this.formData0.payment || null, //付款方式
          AAC01_start_date: this.formData0.startTime || '',
          AAC01_end_date: this.formData0.endTime || '',
          ORG_STATE: this.formData0.medicalRecord || null, //全部病案
          page: this.paginationData.currentPage, //页码
          limit: this.paginationData.pageSize, //条数
        };
        sessionStorage.setItem('Zkpramse', JSON.stringify(pramse));
        sessionStorage.setItem('ZkChoice', this.choice);
        this.getinfo(pramse);
      } else {
        let pramse = {
          AAC04: `${min ? min : 0}-${max ? max : 0}`,
          AAC0401: `${min ? min : 0}`,
          AAC0402: `${max ? max : 0}`,
          AAA04: this.formData1.ageyear || null, //年龄
          AAA40: this.formData1.ageday || null, //不足一周岁年龄
          age_start_type: this.formData1.age_start_type || null,
          age_end_type: this.formData1.age_end_type || null,
          AAC01_start_date: this.formData1.startTime || '',
          AAC01_end_date: this.formData1.endTime || '',
          field: this.formData1.seniorList || null, //字段条件
          page: this.paginationData.currentPage, //页码
          limit: this.paginationData.pageSize, //条数
        };
        sessionStorage.setItem('Zkpramse', JSON.stringify(pramse));
        sessionStorage.setItem('ZkChoice', this.choice);
        this.getinfo(pramse);
      }
    },
    getinfo(p) {
      this.$axios.post('/qualityList', p).then(res => {
        console.log(res);
        this.paginationData.total = res.data.count;
        this.tableData = res.data.list;
        this.ARG_F_D = res.data.ARG_F_D;
        this.ARG_STAY = res.data.ARG_STAY;
        this.AEM01C = res.data.AEM01C;
      });
    },
    reset() {
      // 重置数据
      if (this.choice == 0) {
        Object.assign(this.$data.formData0, this.$options.data().formData0);
      } else {
        Object.assign(this.$data.formData1, this.$options.data().formData1);
      }
      this.funQuery();
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
}

.flextab-item {
  display: flex;
  align-items: center;
  margin-left: 20px;
}

.flextab-item>div {
  font-size: 15px;
  margin-right: 15px;
}

.flextab-item>div span.s-1 {
  color: #185da6;
}

.flextab-item>div span.s-2 {
  font-weight: bold;
}
</style>
