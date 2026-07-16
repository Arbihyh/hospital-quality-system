<template>
  <div id="MyDiv" :class="{ nocopy: $route.query.status }">
    <el-row type="flex" justify="space-between" style="margin-bottom: 10px;">
      <el-tabs v-model="currentTab">
        <el-tab-pane label="未编目首页" name="1" />
        <el-tab-pane label="已编目首页" name="2" />
      </el-tabs>
      <div v-if="$route.query.from == 'review'">
        <el-button type="primary" @click="caseShow = !caseShow">申诉问题</el-button>
      </div>
      <div v-else>
        <el-button type="primary" @click="toExamine" :disabled="review_status == 2">审核通过</el-button>
        <el-button type="primary" @click="caseShow = !caseShow">病历智审结果</el-button>
      </div>
    </el-row>
    <div class="cont_container">
      <!-- 左侧点击列表部分 -->
      <div class="cont-left-tiem">
        <ul class="el-menu-vertical-demo el-menu">
          <li v-if="$route.query.from !== 'forewarning'" class="li-left-item"
            :class="is_active == 0 ? 'is-active' : ''">
            <span class="treeTitle" @click="clickTree(0, '病案首页', {}, false, '病案首页')">病案首页</span>
          </li>
          <li v-for="(item, index) in treeList" :class="[
            is_active == item.bllb ? 'is-active' : '',
            item.bllb == 49 || [2000002, 294, 303, 329, 288, 87, 43, 2000185, 34].includes(item.bllb) ? 'li-left-itemyz' : 'li-left-item',
          ]" :key="index">
            <el-row type="flex" justify="space-between" align="middle">
              <span class="treeTitle" @click="clickTree(item.bllb, item.name, item, false, item.name)">
                {{ item.name }}
                <span v-if="item.bllb == 49">（2）</span>
                <span v-if="(Array.isArray(item.list) && !!item.list.length)">（{{ item.list.length }}）</span>
              </span>
              <el-button type="text" v-if="item.bllb == 49 || (Array.isArray(item.list) && !!item.list.length)"
                :class="`el-icon-arrow-${item.collapse ? 'down' : 'up'}`" @click.stop="setTreeCollapse(index)" />
            </el-row>
            <!-- 医嘱 -->
            <div v-if="item.bllb == 49 && !item.collapse">
              <div :class="['li-left-item-li', is_active == '长期医嘱' ? 'is-active' : '']" data-li="49-1" :id="item.bllb"
                @click.stop="clickTree(item.bllb, '长期医嘱', {}, true, item.name)">长期医嘱</div>
              <div :class="['li-left-item-li', is_active == '临时医嘱' ? 'is-active' : '']" data-li="49-2" :id="item.bllb"
                @click.stop="clickTree(item.bllb, '临时医嘱', {}, true, item.name)">临时医嘱</div>
            </div>
            <!-- 报告单 -->
            <div v-if="item.bllb == 2000002 && !item.collapse">
              <div v-for="(jitem, jindex) in item.list" :key="jindex" :id="jitem.type"
                :class="['li-left-item-li', parentType == item.bllb && is_active == jitem.type ? 'is-active' : '']"
                @click.stop="clickTree(item.bllb, jitem.name, jitem, true, item.name)">
                {{ jitem.name }}
              </div>
            </div>
            <!-- 病程记录、手术记录 -->
            <div v-if="[294, 303, 329, 288, 87, 43, 2000185, 34].includes(item.bllb) && !item.collapse">
              <div v-for="(jitem, jindex) in item.list"
                :class="['li-left-item-li', parentType == item.bllb && is_active == jitem.blbh ? 'is-active' : '']"
                :key="jindex" :id="`${jitem.blbh}`" :title="jitem.name.trim()"
                @click.stop="clickTree(item.bllb, jitem.name, jitem, true, item.name)">
                {{ jitem.name.trim() }}
              </div>
            </div>
          </li>
        </ul>
      </div>
      <div class="cont-left-file" @mouseup.prevent="handleMouseupHandle" @contextmenu.prevent="openMenu($event)">
        <div v-if="is_active == 0">
          <mainHomePage :data="mainHomeData" ref="main" :if-file="ifFile"></mainHomePage>
        </div>
        <div v-else-if="parentType == 292">
          <!-- 入院记录 -->
          <admissionRecord v-if="!admissionRecord.HTML_PRINT" :data="admissionRecord" :ifFile="ifFile">
          </admissionRecord>
          <NoFormatText :text="admissionRecord.HTML_PRINT" :data="admissionRecord" name="" v-else />
        </div>
        <div v-else-if="parentType == '1' && is_active == 1">
          <!-- 出院记录 -->
          <OutHospitalRecord v-if="!outHospitalRecordData.HTML_PRINT" :data="outHospitalRecordData" />
          <NoFormatText :text="outHospitalRecordData.HTML_PRINT" :data="outHospitalRecordData" name="" v-else />
        </div>
        <div
          v-else-if="[294, 329, 43, 2000185, 34].includes(parentType) && !([294, 329, 43, 2000185, 34].includes(is_active))">
          <!-- 病程记录 -->
          <CaseRecord :data="caseRecodeInfo" v-if="caseRecodeInfo.is_format === 1" :ZYH="valData" />
          <NoFormatText :text="caseRecodeInfo.content" :data="caseRecodeInfo" name="" v-else />
        </div>
        <div v-else-if="parentType == 303 && is_active != 303">
          <!-- 手术记录 -->
          <template v-if="surgeryData.is_format">
            <ShouShuRecord1 v-if="surgeryData.type === 1" :data="surgeryData" />
            <ShouShuRecord2 v-if="surgeryData.type === 2" :data="surgeryData" />
            <ShouShuRecord4 v-if="surgeryData.type === 4" :data="surgeryData" />
          </template>
          <NoFormatText v-else :text="surgeryData.content" :data="surgeryData" :name="blname_title" />
        </div>
        <div v-else-if="is_active == '长期医嘱'">
          <!-- 长期医嘱 -->
          <medicalAdvice :dataObj="longAdvice"></medicalAdvice>
        </div>
        <div v-else-if="is_active == '临时医嘱'">
          <!-- 临时医嘱 -->
          <medicalTemporary :dataObj="happensAdvice"></medicalTemporary>
        </div>
        <div v-else-if="parentType == '2000002' && is_active == '1'">
          <!-- 病历图文报告 -->
          <caseImageText :dataObjArr="pacsDetail"></caseImageText>
        </div>
        <div v-else-if="parentType == '2000002' && is_active == '2'">
          <!-- 超声诊断 -->
          <ultrasound :dataObjArr="pacsDetail"></ultrasound>
        </div>
        <div v-else-if="parentType == '2000002' && is_active == '3'">
          <!-- 影像诊断 -->
          <imaging :dataObjArr="pacsDetail"></imaging>
        </div>
        <div v-else-if="parentType == '2000002' && is_active == '4'">
          <!-- 为心电 -->
          <electrocar :dataObjArr="pacsDetail"></electrocar>
        </div>
        <div v-else-if="parentType == '2000002' && is_active == '5'">
          <!-- 检验报告单 病理 -->
          <checkout :dataObjArr="pacsDetail"></checkout>
        </div>
        <div v-else-if="parentType == '2000002' && is_active == '6'">
          <!-- 內窥镜检查报告 病理 -->
          <sightGlass :dataObjArr="pacsDetail"></sightGlass>
        </div>
        <div v-else-if="is_active == 288 || is_active == 18">
          <!-- 死亡记录 或 24小时内入院记录 -->
          <DeathText :dataObjArr="dataObj"></DeathText>
        </div>
        <div v-else>
          <div v-if="update">
            <newContFile v-for="(item, index) of text" :key="index" :data="item" :name="name_title"></newContFile>
          </div>
        </div>
      </div>
      <div class="case-content" v-show="caseShow">
        <!-- status 不存在 意味着不脱敏、医院自助查询 -->
        <template v-if="!$route.query.status">
          <!-- 住院病案质控 -->
          <CaseQualityBox :linkType="$route.query.from" ref="CaseQualityBoxRef" @changeTab="(e) => currentTab = e" />
        </template>
      </div>
      <!-- 添加质控结果 -->
      <CreateControlResultDialogVue v-if="dialogData.bSwitch" :AAA28="mainHomeData.AAA28" :data="dialogData"
        @refresh="handelRefreshResults" :MED_REC_ID="valData" :CWH="mainHomeData.CWH" :catalogName="catalogName"
        :AAA29="mainHomeData.AAA29" :JSKS="mainHomeData.AAC11C" @close="handleUpdate"
        :currentTreeItem="currentTreeItem"  :currentTreeBLBH = "currentTreeBLBH"/>
      <!-- 右键菜单 -->
      <div class="dialog-box">
        <el-dialog title="通知" :show-close="false" :visible.sync="gridCustomizeVisible" width='350px'>
          请确认是否编辑整改通知
          <span slot="footer" class="dialog-footer">
            <el-button @click="gridCustomizeVisible = false">取 消</el-button>
            <el-button type="primary" @click="onCreate">确 认</el-button>
          </span>
        </el-dialog>
      </div>
      <!-- <ul v-show="gridCustomizeVisible" :style="{ left: left + 'px', top: top + 'px' }" class="contextmenu">
        <li @click="onCreate">添加</li>
      </ul> -->
    </div>

    <!-- 审核申诉弹窗 -->
    <div class="dialog-box">
      <el-dialog title="审核结果" :visible.sync="dialogVisible" :close-on-click-modal="false" width="340px">
        <el-form ref="form" :model="alertForm">
          <el-form-item label="驳回原因：">
            <el-input v-model="alertForm.describe" type="textarea" placeholder="请输入驳回原因" />
          </el-form-item>
          <el-form-item label="审核科室:">
            <el-input v-model="alertForm.case_document" placeholder="审核科室" />
            <!-- <el-select v-model="alertForm.case_document">
              <el-option v-for="(v,k) in groupList" :key="k" :label="v.name" :value="v.name"/>
            </el-select> -->
          </el-form-item>
          <el-form-item label="审核医师:">
            <el-input v-model="alertForm.case_docter" placeholder="审核医师" />
          </el-form-item>
          <el-form-item label="手机号">
            <el-input v-model="alertForm.case_docter_mobile" placeholder="手机号" />
          </el-form-item>
        </el-form>

        <span slot="footer" class="dialog-footer">
          <el-button type="primary" @click="editSubmit">确 定</el-button>
          <el-button @click="dialogVisible = false">取 消</el-button>
        </span>
      </el-dialog>
    </div>
  </div>
</template>
<script>
import Mmenu from '@/components/m-menu'
import mainHomePage from './contFile/mainHomePage'
import newContFile from './contFile/newContFile'
import admissionRecord from './contFile/admissionRecord'
import medicalAdvice from './contFile/medicalAdvice'
import medicalTemporary from './contFile/medicalTemporary'
import caseImageText from './report/caseImageText'
import ultrasound from './report/ultrasound'
import imaging from './report/imaging'
import electrocar from './report/electrocar'
import checkout from './report/checkout'
import sightGlass from './report/sightGlass'
import CaseRecord from './components/CaseRecord2.vue'
import ShouShuRecord1 from './components/ShouShuRecord1.vue'
import ShouShuRecord2 from './components/ShouShuRecord2.vue'
import ShouShuRecord4 from './components/ShouShuRecord4.vue'
import NoFormatText from './components/NoFormatText.vue'
import OutHospitalRecord from './components/OutHospitalRecord.vue'
import CaseQualityBox from './components/CaseQualityBox2.vue'
import DeathText from './components/DeathText.vue'
import CreateControlResultDialogVue from './components/CreateControlResultDialog.vue'
import { getTreeList, getBlMenuList, getCaseQuality, getCasePlatform, getAllCase, getLong, getTemporary, getPacsData, getBcData, getHomeData, getSurgeryData, applyForReview } from '@/api/qc'
import { getCaseExamineAppeal } from '@/api/admin'
import { getToken, removeToken } from '@/utils/auth'

export default {
  components: {
    Mmenu,
    mainHomePage,
    newContFile,
    admissionRecord,
    medicalAdvice,
    medicalTemporary,
    caseImageText,
    ultrasound,
    imaging,
    electrocar,
    sightGlass,
    checkout,
    CaseRecord,
    ShouShuRecord1,
    ShouShuRecord2,
    ShouShuRecord4,
    NoFormatText,
    OutHospitalRecord,
    CaseQualityBox,
    DeathText,
    CreateControlResultDialogVue
  },
  directives: {},
  filters: {},
  extends: {},
  mixins: {},
  props: {},
  data() {
    return {
      catalogName: '病案首页',
      currentTreeItem: {},
      currentTab: "1",
      mainHomeData: {},
      admissionRecord: {},
      longAdvice: {},
      happensAdvice: {},
      valData: '',
      ifFile: false,
      treeList: [],
      is_active_blbh: 0,
      is_active: 0,
      parentType: 0,
      name_title: '',
      text: [],
      update: true,
      titleName: '住院病案',
      pacsDetail: {},
      // 病程记录详情
      caseRecodeInfo: {
        is_format: 1
      },
      surgeryData: {
        mzfj: [],
        ssqk: [],
        sscxsj: [],
        sslb: [],
        is_format: 0
      },
      outHospitalRecordData: {
        name: {},
        ry_time: {},
        sex: {},
        age: {},
        cy_time: {},
        zyts: {},
        ryqk: {},
        cbzd: {},
        zljg: {},
        cyqk: {},
        cyzd: {},
        cyyz: {}
      },
      dataObj: [],
      results: null,
      dialogData: {
        bSwitch: false,
        text: '',
        blbh: ''
      },
      top: 0,
      left: 0,
      gridCustomizeVisible: false,

      dialogVisible: false,
      alertForm: {},
      appealInfo: {},
      review_status: 0,
      caseShow: true,
      resultsData: null,
      currentTreeBLBH: '',
    }
  },
  computed: {
    bcjlLiIds() {
      // 病程记录子项数据
      const arr = Object.values(this.treeList).filter(item => item.bllb === 294)
      const liIds = []
      if (arr.length) {
        if (arr[0].list) {
          for (let i = 0; i < arr[0].list.length; i++) {
            liIds.push(arr[0].list[i].blbh)
          }
        }
      }
      return liIds
    },
    shoushuLiIds() {
      // 病程记录子项数据
      const arr = Object.values(this.treeList).filter(item => item.bllb === 303)
      const liIds = []
      if (arr.length) {
        if (arr[0].list) {
          for (let i = 0; i < arr[0].list.length; i++) {
            liIds.push(arr[0].list[i].blbh)
          }
        }
      }
      return liIds
    },
    blname_title() {
      let title
      const type = this.surgeryData.type
      if (type === 1) {
        title = '手术风险评估表'
      } else if (type === 2) {
        title = '手术安全核查表'
      } else if (type === 3) {
        title = '手术同意书'
      } else if (type === 4) {
        title = '手术记录'
      }
      return title
    }
  },
  watch: {
    //变量名
    gridCustomizeVisible(val) {
      if (val) {
        //点击事件，调用方法
        document.body.addEventListener("click", this.closeMenu);
      } else {
        document.body.removeEventListener("click", this.closeMenu);
      }
    },
    currentTab() {
      this.funQuery()
    }
  },
  mounted() {
    this.getInitData();
  },

  methods: {
    setTreeCollapse(index) {
      this.$set(this.treeList[index], 'collapse', !(this.treeList[index].collapse))
    },
    getInitData() {
      this.valData = this.$route.query.ZYH
      if (this.valData) {
        this.funQuery()
        this.getCaseQualityResults()
      }
      this.getTree();
      this.getDataExamine();
    },
    //申述提交
    editSubmit() {
      let that = this;
      let params = {
        id: that.appealInfo.id,
        status: that.appealInfo.status,
        describe: that.alertForm.describe,
        case_document: that.alertForm.case_document,
        case_docter: that.alertForm.case_docter,
        case_docter_mobile: that.alertForm.case_docter_mobile,
      }
      let index = that.appealInfo.index;
      getCaseExamineAppeal(params).then(res => {
        const { c } = res
        if (c == 0) {
          that.$message('提交成功');
          that.dialogVisible = false;
          that.alertForm = {};
          that.$refs.CaseQualityBox.editSubmit(index);
          that.$refs.CaseQualityBox.qualityBazb(index);
        } else {
          that.$message('提交失败');
        }

      })
    },
    clickAppealBtn(e) {
      let that = this;
      that.appealInfo = e;
      that.alertForm.case_document = localStorage.getItem('KSMC');
      that.alertForm.case_docter = localStorage.getItem('realname');
      if (e.status == 1) {
        // 通过
        that.editSubmit();
      } else {

        that.dialogVisible = true;

      }

    },
    // 刷新质控结果
    handelRefreshResults() {
      if (this.$refs.CaseQualityBoxRef) {
        this.$refs.CaseQualityBoxRef.getTabsData(true);
      }
    },
    // 鼠标右击事件
    openMenu(e) {
      //获取右击时得坐标
      var x = e.pageX;
      var y = e.pageY;
      //top，left在data种定义，初始值为0
      //top，left是右键菜单得坐标值，可以通过运算调整
      this.top = y - 80;
      this.left = x - 200;

      if (this.dialogData.text) {
        this.gridCustomizeVisible = true;
      }
    },
    closeMenu() {
      this.gridCustomizeVisible = false;
    },
    // 鼠标右击事件
    onCreate() {
      this.dialogData.bSwitch = true
    },
    // 鼠标事件
    handleMouseupHandle() {
      const text = window.getSelection().toString()
      if (text.trim().length) {
        this.dialogData.blbh = this.is_active_blbh
        this.dialogData.text = text
      }
    },
    // 获取新病案指控结果
    getCaseQualityResults() {
      let that = this;
      const params = {
        id: that.valData
      }
      getCaseQuality(params).then(res => {
        that.results = null;
        that.$nextTick(() => {
          that.results = res.data;

        })
      }).catch(e => {
        console.log(e)
      })
    },
    reload() {
      // 移除组件
      this.update = false
      // 在组件移除后，重新渲染组件
      // this.$nextTick可实现在DOM 状态更新后，执行传入的方法。
      this.$nextTick(() => {
        this.update = true
      })
    },
    funEdit() {
      this.ifFile = true
      this.$message('errer:功能待开发')
    },
    getback() {
      this.$router.go(-1)
    },
    /**
     * 跳转对应病历首页
     */
    getBlankIndexss(item) {
      this.$refs.main.getBlankIndex(item)
    },
    funQuery() {
      console.log('this.currentTab',this.currentTab)
      const params = {
        [this.currentTab == '1' ? 'ZYH' : 'id']: this.valData
      }
      if (this.$route.query.status) {
        params.is_tm = 1
      }
      if (this.currentTab == '1') {
        this.$axios.post('/home_sz_quality/blInfo', params).then(res => {
          this.mainHomeData = res.data
          this.is_active_blbh = this.mainHomeData.MED_REC_ID
        })
      } else {
        getHomeData(params).then(res => {
          this.mainHomeData = res.data
          this.is_active_blbh = this.mainHomeData.MED_REC_ID
        })
      }
    },
    getTree() {
      const that = this
      const pramse = {
        id: this.valData
      }
      this.$axios.post('/getTree', pramse).then(res => {
        that.treeList = res.data
      })
    },
    clickTree(b, n, item, isLeaf = false, name = '') {
      this.currentTreeBLBH = item.blbh
      this.catalogName = name
      this.currentTreeItem.blbh = ''
      if (item) {
        if (isLeaf) {
          if (n == '长期医嘱' || n == '临时医嘱') {
            this.is_active = n
          } else if (b == '2000002') { // 报告单
            this.is_active = item.type
          } else {
            this.is_active = item.blbh
          }
        } else { // 如果不是叶子节点
          this.is_active = b;
        }
      } else { // 病案首页
        this.is_active = 0;
        this.funQuery();
      }
      this.titleName = n;
      this.parentType = b;
      let that = this;
      that.name_title = n;
      console.log('>>>>>>>>>>>>', this.parentType, this.is_active)
      if (b != 0 && b != 292 && b != 1 && !isLeaf) {
        const params = {
          MED_REC_ID: that.valData,
          bllb: b,
        };
        if (this.$route.query.status) {
          params.is_tm = 1;
        }
        that.$axios.post('/getAllCase', params).then(res => {
          that.text = res.data;
          that.dataObj = res.data;
          that.reload();
        });
      } else if (b == 292) {
        // 获取详情-入院记录
        const params = {
          id: this.valData,
          bllb: b,
        };
        if (this.$route.query.status) {
          params.is_tm = '1';
        }
        that.$axios2.post('/get_case_platform', params).then(res => {
          this.admissionRecord = res.data;
          this.currentTreeItem.blbh = res.data.blbh
        });
      } else if (b == 1) {
        // 获取详情-出院记录
        const params = {
          id: this.valData,
          bllb: b,
        };
        if (this.$route.query.status) {
          params.is_tm = 1;
        }
        that.$axios2.post('/get_case_platform', params).then(res => {
          this.outHospitalRecordData = res.data;
          this.currentTreeItem.blbh = res.data.blbh
        });
      }
      if (that.is_active == '长期医嘱') {
        // 长期医嘱
        const params = {
          AAA28: that.valData,
        };
        if (this.$route.query.status) {
          params.is_tm = 1;
        }
        that.$axios.post('/long', params).then(res => {
          this.longAdvice = res.data;
        });
      }
      if (that.is_active == '临时医嘱') {
        // 临时医嘱
        const params = {
          AAA28: that.valData,
        };
        if (this.$route.query.status) {
          params.is_tm = 1;
        }
        that.$axios.post('/temporary', params).then(res => {
          this.happensAdvice = res.data;
        });
      }
      if (that.parentType == '2000002' && isLeaf) {
        // 报告单 相关
        let parm = {
          type: Number(that.is_active),
        };
        if (this.$route.query.status) {
          parm.is_tm = 1;
        }
        parm.zyh = item.ZYH
        that.$axios.post('/get_pacs_data', parm).then(res => {
          this.pacsDetail = res.data;
        });
      }
      // 病程记录 
      if ([294, 329, 43, 2000185, 34].includes(b) && isLeaf) {
        // if (that.is_active) {
        // 请求前先重置之前的数据
        that.caseRecodeInfo = {};
        let parm = { blbh: item.blbh };
        if (this.$route.query.status) {
          parm.is_tm = 1;
        }
        that.$axios.post('/get_bc_data', parm).then(res => {
          that.caseRecodeInfo = res.data[0].bc_data;
          that.caseRecodeInfo.is_format = res.data[0].is_format;
          that.currentTreeItem.blbh = res.data[0].blbh
        });
        // }
      }

      // 会诊记录
      console.log('会诊记录',b, isLeaf, item)
      if (( item.name === '会诊记录') && isLeaf) {
        console.log('会诊记录11',item)
        
        let parm = { "blbh": item.blbh,"jzhm": item.jzhm };
      
        that.$axios.post('/getHzxx', parm).then(res => {
          console.log('会诊记录',res.data)
        });
        // }
      }

      // 手术记录
      if (b == 303 && isLeaf) {
        // if (that.is_active) {
        // 请求前先重置之前的数据
        that.surgeryData = {};
        let parm = { blbh: item.blbh };
        if (this.$route.query.status) {
          parm.is_tm = 1;
        }
        that.$axios.post('/get_surgery_data', parm).then(res => {
          that.surgeryData = res.data[0].surgery_data;
          that.surgeryData.is_format = res.data[0].is_format;
          that.currentTreeItem.blbh = res.p[0].blbh
        });
        // }
      }
    },

    /**
     * 批量审核
     */
    toExamine() {
      this.$confirm('是否确认通过?', '提示', {
        confirmButtonText: '是',
        cancelButtonText: '否',
        type: 'warning'
      }).then(() => {
        var ZYH = [this.valData];
        applyForReview({ ZYH: ZYH, status: 2 }).then(res => {
          this.$message.success(res.msg || '申请成功');
          this.getDataExamine();
          this.$router.back();
        }).catch(error => {
          console.log(error);
          if (error && error.message) {
            const errorMsg = error.message;
            this.$alert(errorMsg, '审核', {
              confirmButtonText: '确定',
              callback: action => { }
            });
          }
        })
      }).catch(() => {

      });
    },

    /**
     * 获取审核状态
     */
    getDataExamine() {
      var ZYH = this.valData;
      this.$axios.post('/getDataExamine', { ZYH: ZYH }).then(res => {
        this.review_status = res.data.review_status ?? 0;
      })
    },
    handleAnimationEnd(event) {
      if (event.animationName === 'casehidden' && !this.caseShow) {
        // 动画结束后隐藏元素
        this.caseShow = false;
      }
    },
    handleClose() {
      this.caseShow = false; // 关闭弹框
    },
    handleUpdate() {
      this.dialogData.bSwitch = false
      this.getInitData();
      this.handelRefreshResults();
      this.caseShow = true
    }
  }
}
</script>
<style lang="scss" scoped>
#MyDiv {
  margin: 0;
  padding: 0 !important;
}

.nocopy {
  user-select: none;
}

.header {
  margin: 10px 20px;
  text-align: right;
  display: flex;
  justify-content: flex-end;
}

.cont_container {
  display: flex;
  justify-content: center;
  height: calc(100% - 64px)
}

.cont-left-tiem {
  width: 250px;
  // margin: 0 0 0 15px;
  // min-height: 650px;
  height: 100%;
  overflow-y: scroll;
  background: #ffffff;
}

.cont-left-file {
  flex: 1;
  // min-height: 650px;
  margin: 0 5px;
  height: 100%;
  overflow-y: scroll;
  background: #ffffff;
  border: 1px solid #e2e2e2;
  padding: 10px;
}

.li-router {
  display: inline-block;
  width: 100%;
  height: 100%;
}

.li-left-item {
  line-height: 56px;
  font-size: 14px;
  color: #303133;
  padding: 0 20px;
  cursor: pointer;
  -webkit-transition: border-color 0.3s, background-color 0.3s, color 0.3s;
  transition: border-color 0.3s, background-color 0.3s, color 0.3s;
  -webkit-box-sizing: border-box;
  box-sizing: border-box;
}

.li-left-itemyz {
  line-height: 56px;
  font-size: 14px;
  color: #303133;
  padding: 0 20px;

  -webkit-transition: border-color 0.3s, background-color 0.3s, color 0.3s;
  transition: border-color 0.3s, background-color 0.3s, color 0.3s;
  -webkit-box-sizing: border-box;
  box-sizing: border-box;

  .li-left-item-li {
    width: 100%;
    line-height: 36px;
    padding: 0 20px;
    cursor: pointer;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  .li-left-item-li:hover {
    outline: 0;
    background-color: #ecf5ff;
  }
}

.li-left-item:focus,
.li-left-item:hover {
  outline: 0;
  background-color: #ecf5ff;
}

.is-active {
  color: #409eff;
}

.dialog-box {
  ::v-deep .el-dialog__header {
    padding: 10px 20px;
    background: rgb(27, 100, 169);
    color: #fff !important;

    .el-dialog__title {
      color: #fff;
    }

    .el-dialog__headerbtn {
      top: 14px;
    }
  }

  ::v-deep .el-dialog__body {
    .el-form-item {
      background: #fff;
    }

    .el-input {
      width: 100%;

      input {
        height: 35px;
        border: 1px solid #C0C4CC;
        border-radius: 6px;
      }
    }
  }
}
</style>
<style>
/*  ================================== 文本形式样式 ↓ ======================== */
.refachInput span {
  height: auto;
  line-height: 1;
  padding: 10px 0;
}

.refachInput span.refachInput-text {
  padding-left: 12px;
}

.el-row--flex.is-justify-space-around {
  justify-content: flex-start;
}

.member-infobox {
  width: 100%;
}

.member-infobox .info-box-1 {
  /* display: flex;
    flex-wrap: wrap; */
  margin-top: 20px;
}

.infoBox-items {
  width: auto;
  display: flex;
  align-items: center;
  padding: 8px 0;
}

.padding-left20 {
  padding-left: 20px;
}

.padding-right20 {
  padding-right: 20px;
}

.infoBox-title {
  color: #333;
  font-size: 12px;
  font-weight: bold;
}

.infoBox-items-text {
  color: #666;
  font-size: 12px;
  padding-left: 5px;
  padding-right: 20px;
}

.title-ff0000 {
  color: #ff0000;
}

/* 高亮 */
.choose-twinkle {
  font-size: 20px;
  color: red;
  font-weight: 600;
  background: yellow;
}

.table-value-look {
  padding-left: 12px;
  color: #ff0000;
  cursor: pointer;
}

/* 右键菜单 */
.contextmenu {
  margin: 0;
  background: #fff;
  z-index: 3000;
  position: absolute;
  list-style-type: none;
  padding: 5px 0;
  border-radius: 4px;
  font-size: 12px;
  font-weight: 400;
  color: #333;
  box-shadow: 2px 2px 3px 0 rgba(0, 0, 0, 0.3);
}

.contextmenu li {
  margin: 0;
  padding: 7px 16px;
  cursor: pointer;
}

.contextmenu li:hover {
  background: #eee;
}

.case-content {
  width: 340px;
  background-color: #fff;
}

.case-content-hidden {
  width: 0px;
  animation: casehidden 1.4s ease;
  /*动画名称：loading  动画时长：1.4s  动画循环：infinite*/
}

.case-content-show {
  width: 340px;
  /*初始宽度*/
  animation: caseshow 1.4s ease;
  /*动画名称：loading  动画时长：1.4s  动画循环：infinite*/
}

@keyframes caseshow {
  0% {
    width: 0px;
    /*初始宽度*/
  }

  100% {
    width: 340px;
    /*结束宽度*/
  }
}

@keyframes casehidden {
  0% {
    width: 340px;
    /*初始宽度*/
  }

  100% {
    width: 0px;
    /*结束宽度*/
  }
}
</style>
