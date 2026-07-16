<template>
  <div id="MyDiv" :class="{ nocopy: $route.query.status }">
    <div class="cont_container">
      <!-- 左侧点击列表部分 -->
      <div class="cont-left-tiem">
        <ul class="el-menu-vertical-demo el-menu">
          <li class="li-left-item" :class="is_active == 0 ? 'is-active' : ''" @click="clickTree(0, '住院病案')">
            <span>住院病案</span>
          </li>
          <li
            v-for="(item, index) in treeList"
            :key="index"
            :class="[is_active == item.bllb && item.bllb != 49 ? 'is-active' : '', item.bllb == 49 || [2000002, 294, 303, 329, 288, 34, 87].includes(item.bllb) ? 'li-left-itemyz' : 'li-left-item']"
            @click="clickTree(item.bllb, item.name, item)"
          >
            <span>{{ item.name }}</span>
            <div v-if="item.bllb == 49">
              <div :class="['li-left-item-li', is_active == '长期医嘱' ? 'is-active' : '']" data-li="49-1" :id="item.bllb">长期医嘱</div>
              <div :class="['li-left-item-li', is_active == '临时医嘱' ? 'is-active' : '']" data-li="49-2" :id="item.bllb">临时医嘱</div>
            </div>
            <div v-if="item.bllb == 2000002">
              <div v-for="(jitem, jindex) in item.list" :id="jitem.type" :key="jindex" :class="['li-left-item-li', is_active == jitem.ExamType ? 'is-active' : '']">
                {{ jitem.name }}
              </div>
            </div>
            <!-- 病程记录、手术记录 -->
            <div v-if="[294, 303, 329, 288, 34, 87].includes(item.bllb)">
              <div
                v-for="(jitem, jindex) in item.list"
                :id="`${jitem.blbh}`"
                :key="jindex"
                :class="['li-left-item-li', is_active == jitem.blbh ? 'is-active' : '']"
                :title="jitem.name.trim()"
              >
                {{ jitem.name.trim() }}
              </div>
            </div>
          </li>
        </ul>
      </div>
      <div class="cont-left-file" @mouseup.prevent="handleMouseupHandle" @contextmenu.prevent="openMenu($event)">
        <div v-if="is_active == 0">
          <mainHomePage ref="main" :data="mainHomeData" :if-file="ifFile" />
        </div>
        <div v-else-if="is_active == 292">
          <!-- 入院记录 -->
          <admissionRecord :data="admissionRecord" :if-file="ifFile" />
        </div>
        <div v-else-if="parentType == '1' && is_active == 1">
          <!-- 出院记录 -->
          <OutHospitalRecord :data="outHospitalRecordData" />
        </div>
        <div v-else-if="bcjlLiIds.includes(is_active)">
          <!-- 病程记录 -->
          <div>病程记录</div>
          <CaseRecord v-if="caseRecodeInfo.is_format === 1" :data="caseRecodeInfo" />
          <NoFormatText v-else :text="caseRecodeInfo.content" :data="caseRecodeInfo" name="" />
        </div>
        <div v-else-if="shoushuLiIds.includes(is_active)">
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
          <medicalAdvice :data-obj="longAdvice" />
        </div>
        <div v-else-if="is_active == '临时医嘱'">
          <!-- 临时医嘱 -->
          <medicalTemporary :data-obj="happensAdvice" />
        </div>
        <div v-else-if="parentType == '2000002' && is_active == '1'">
          <!-- 病历图文报告 -->
          <caseImageText :data-obj-arr="pacsDetail" />
        </div>
        <div v-else-if="parentType == '2000002' && is_active == '2'">
          <!-- 超声诊断 -->
          <ultrasound :data-obj-arr="pacsDetail" />
        </div>
        <div v-else-if="parentType == '2000002' && is_active == '3'">
          <!-- 影像诊断 -->
          <imaging :data-obj-arr="pacsDetail" />
        </div>
        <div v-else-if="parentType == '2000002' && is_active == '4'">
          <!-- 为心电 -->
          <electrocar :data-obj-arr="pacsDetail" />
        </div>
        <div v-else-if="parentType == '2000002' && is_active == '5'">
          <!-- 检验报告单 病理 -->
          <checkout :data-obj-arr="pacsDetail" />
        </div>
        <div v-else-if="parentType == '2000002' && is_active == '6'">
          <!-- 內窥镜检查报告 病理 -->
          <sightGlass :data-obj-arr="pacsDetail" />
        </div>
        <div v-else-if="is_active == 288 || is_active == 18">
          <!-- 死亡记录 或者 24小时内入院记录 -->
          <DeathText :data-obj-arr="dataObj" v-if="update" />
        </div>
        <div v-else>
          <div v-if="update">
            <newContFile v-for="(item, index) of text" :key="index" :text="item.HJNR" :name="name_title" />
          </div>
        </div>
      </div>
      <!-- status 不存在 意味着不脱敏、医院自助查询 -->
      <template v-if="!$route.query.status">
        <!-- 住院病案质控 -->
        <CaseQualityBox :data="results" v-if="results.data" @clickAppealBtn="clickAppealBtn" :width="340" :height="820" ref="CaseQualityBox"/>
      </template>
      <!-- 添加质控结果 -->
      <CreateControlResultDialogVue v-if="dialogData.bSwitch" :data="dialogData" @refresh="handelRefreshResults" />
      <!-- 右键菜单 -->
      <ul
        v-show="gridCustomizeVisible"
        :style="{ left: left + 'px', top: top + 'px' }"
        class="contextmenu"
      >
        <li @click="onCreate">添加</li>
      </ul>
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
          <el-button @click="dialogVisible=false">取 消</el-button>
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
import { getTreeList,getBlMenuList,getCaseQuality, getCasePlatform, getAllCase, getLong, getTemporary, getPacsData, getBcData, getHomeData, getSurgeryData, getBlInfo } from '@/api/qc'
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
      appealInfo: {}
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
  },
  mounted() {
    this.valData = localStorage.getItem('getData')
    if (this.valData) {
      this.funQuery()
      this.getCaseQualityResults()
    }
    this.getTree()
  },

  methods: {
    editSubmit(){
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
        if(c == 0){
          that.$message('提交成功');
          that.dialogVisible = false;
          that.alertForm = {};
          that.$refs.CaseQualityBox.editSubmit(index);
        }else{
          that.$message('提交失败');
        }

      })
    },
    clickAppealBtn(e) {
      console.log(e)
      let that = this;
      that.appealInfo = e;
      that.alertForm.case_document = localStorage.getItem('KSMC');
      that.alertForm.case_docter = localStorage.getItem('realname');
      if(e.status == 1){
        // 通过
        that.editSubmit();
      }else{

        that.dialogVisible = true;

      }
      
    },
    // 刷新质控结果
    handelRefreshResults() {
      this.getCaseQualityResults()
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
        id: Number(that.valData)
      }
      getCaseQuality(params).then(res => {
        console.log(res)
        that.results = null;
        that.$nextTick(() =>{
          that.results = res.data;
        })
      }).catch(e =>{
        console.log(e)
      })
      // that.axios({
      //   url:'http://10.10.11.65:8081/bazb/get_case_quality_v2',
      //   method: 'post',
      //   headers: { 'content-type': 'application/json', 'token': getToken() },
      //   data: {
      //     ZYH: Number(that.valData)
      //   }
      // }).then(res => {
      //   this.results = res.data
      // })
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
      const params = {
        id: this.valData
      }
      if (this.$route.query.status) {
        params.is_tm = 1
      }
      getHomeData(params).then(res => {
        this.mainHomeData = res.p
        this.is_active_blbh = this.mainHomeData.MED_REC_ID
      })
    },
    getTree() {
      const that = this
      const pramse = {
        id: this.valData
      }
      getTreeList(pramse).then(res => {
        that.treeList = res.data
        // const { p } = res
        // 初始化blbh
        // that.is_active_blbh = p[0].blbh
      })
    },
    clickTree(b, n, item) {
      if (item) {
        if (item.blbh) {
          this.is_active_blbh = item.blbh
        } else {
          this.is_active_blbh = event.target.id
        }
      } else {
        // this.is_active_blbh = this.mainHomeData.MED_REC_ID
        this.is_active = 0;
        this.funQuery();
      }
      this.titleName = n
      this.parentType = b
      const that = this
      // 判断点击的
      if (event.target.id || !item?.list) {
        if (event.target.outerText == '长期医嘱' || event.target.outerText == '临时医嘱') {
          that.is_active = event.target.outerText;
        } else if (b == 2000002) {
          that.is_active = event.target.id;
        } else if (b == 294) {
          that.is_active = event.target.id;
        } else if (b == 303) {
          that.is_active = event.target.id;
        } else {
          that.is_active = b;
        }
      }
      that.name_title = n
      if (b != 0 && b != 292 && b != 1) {
        const params = {
          MED_REC_ID: that.valData,
          bllb: b
        }
        if (this.$route.query.status) {
          params.is_tm = 1
        }
        getAllCase(params).then(res => {
          that.text = res.data
          that.dataObj = res.data
          that.reload()
        })
      } else if (b == 292) {
        // 获取详情
        const params = {
          id: this.valData,
          bllb: b
        }
        if (this.$route.query.status) {
          params.is_tm = '1'
        }
        getCasePlatform(params).then(res => {
          this.admissionRecord = res.data
        })
      } else if (b == 1) {
        // 获取详情
        const params = {
          id: this.valData,
          bllb: b
        }
        if (this.$route.query.status) {
          params.is_tm = 1
        }
        getCasePlatform(params).then(res => {
          this.outHospitalRecordData = res.data;
        })
      }
      if (that.is_active == '长期医嘱') {
        // 长期医嘱
        const params = {
          AAA28: that.valData
        }
        if (this.$route.query.status) {
          params.is_tm = 1
        }
        getLong(params).then(res => {
          this.longAdvice = res.data
        })
      }
      if (that.is_active == '临时医嘱') {
        // 临时医嘱
        const params = {
          AAA28: that.valData
        }
        if (this.$route.query.status) {
          params.is_tm = 1
        }
        getTemporary(params).then(res => {
          this.happensAdvice = res.p
        })
      }
      if (that.parentType == '2000002' && that.is_active != '') {
        // 报告单 相关
        const parm = {
          type: Number(that.is_active)
        }
        if (this.$route.query.status) {
          parm.is_tm = 1
        }
        const treeListArr = Object.values(that.treeList)
        treeListArr.forEach((item, index) => {
          if (item.bllb == 2000002) {
            parm.zyh = Number(item.list[0].ZYH)
          }
        })

        getPacsData(parm).then(res => {
          this.pacsDetail = res.data
        })
      }
      // 病程记录
      if (item.bllb === 294) {
        if (that.is_active) {
          // 请求前先重置之前的数据
          that.caseRecodeInfo = {}
          const parm = { blbh: that.is_active }
          if (this.$route.query.status) {
            parm.is_tm = 1
          }
          getBcData(parm).then(res => {
            that.caseRecodeInfo = res.data[0].bc_data
            that.caseRecodeInfo.is_format = res.data[0].is_format
          })
        }
      }
      // 手术记录
      if (item.bllb === 303) {
        if (that.is_active) {
          // 请求前先重置之前的数据
          that.surgeryData = {}
          const parm = { blbh: that.is_active }
          if (this.$route.query.status) {
            parm.is_tm = 1
          }
          getSurgeryData(parm).then(res => {
            that.surgeryData = res.p[0].surgery_data
            that.surgeryData.is_format = res.p[0].is_format
          })
        }
      }
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
}
.cont-left-tiem {
  width: 250px;
  margin: 0 0 0 15px;
  min-height: 650px;
  height: calc(100vh - 130px);
  overflow-y: scroll;
  background: #ffffff;
}
.cont-left-file {
  flex: 1;
  min-height: 650px;
  margin: 0 5px;
  height: calc(100vh - 130px);
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
  padding-left: 20px;

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
  ::v-deep .el-dialog__header{
    padding: 10px 20px;
    background: rgb(27,100,169);
    color: #fff !important;
    .el-dialog__title{
      color: #fff;
    }
    .el-dialog__headerbtn{
      top: 14px;
    }
  }
  ::v-deep .el-dialog__body{
    .el-form-item {
      background: #fff;
    }
    .el-input{
      width: 100%;
      input{
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
</style>
