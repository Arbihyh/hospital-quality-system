<template>
  <div id="MyDiv" style="margin: 16px" :class="{ nocopy: $route.query.status }">
    <div v-if="topMtnShow" class="header zbjg-box" style="background: #fff; margin: 10px 0; padding: 6px 20px">
      <span>
        <el-button type="primary" v-if="!($route.query.from == 'majorIndexDetail')" style="background-color: #328240" @click="onControll">重新质控</el-button>
        <el-button type="primary" v-if="!($route.query.from == 'majorIndexDetail')" style="background-color: #328240" @click="onModuleControl">模型质控</el-button>
      </span>
      <div>
        <el-button @click="clickzbjg" v-if="$route.query.from == 'majorIndexDetail'">指标结果</el-button>
        <el-button type="primary" v-if="!($route.query.from == 'majorIndexDetail')" @click="isControl = !isControl">智审结果</el-button>
        <el-button type="text" v-if="!($route.query.from == 'majorIndexDetail')" style="color: #606266" @click="openAIModel">
          <img src="../../assets//images//AIHelper.png" style="width: 30px; display: inline-block; vertical-align: middle" />
          AI模型
        </el-button>
      </div>
    </div>
    <div class="cont_container">
      <!-- 左侧点击列表部分 -->
      <div class="cont-left-tiem">
        <!-- <div style="margin: 20px">
          <el-button type="primary" @click="onControll">执行质控</el-button>
        </div> -->
        <ul class="el-menu-vertical-demo el-menu">
          <!-- 预警信息不展示病案首页 -->
          <li v-if="$route.query.from !== 'forewarning'" class="li-left-item" :class="is_active == 0 ? 'is-active' : ''">
            <span class="treeTitle" @click="clickTree(0, '病案首页', {}, false, '病案首页')">病案首页</span>
          </li>
          <li
            v-for="(item, index) in treeList"
            :class="[
              is_active == item.bllb ? 'is-active' : '',
              item.bllb == 49 || [2000002, 294, 303, 329, 288, 87, 43, 2000185, 34, 'hzxx'].includes(item.bllb) || item.name === '会诊记录' ? 'li-left-itemyz' : 'li-left-item',
            ]"
            :key="index"
          >
            <el-row type="flex" justify="space-between" align="middle">
              <span class="treeTitle" @click="clickTree(item.bllb, item.name, item, false, item.name)">
                {{ item.name }}
                <span v-if="item.bllb == 49">（2）</span>
                <span v-if="Array.isArray(item.list) && !!item.list.length">（{{ item.list.length }}）</span>
              </span>
              <el-button
                type="text"
                v-if="item.bllb == 49 || (Array.isArray(item.list) && !!item.list.length)"
                :class="`el-icon-arrow-${item.collapse ? 'down' : 'up'}`"
                @click.stop="setTreeCollapse(index)"
              />
            </el-row>
            <!-- 医嘱 -->
            <div v-if="item.bllb == 49 && !item.collapse">
              <div
                :class="['li-left-item-li', is_active == '长期医嘱' ? 'is-active' : '']"
                data-li="49-1"
                :id="item.bllb"
                @click.stop="clickTree(item.bllb, '长期医嘱', {}, true, item.name)"
              >
                长期医嘱
              </div>
              <div
                :class="['li-left-item-li', is_active == '临时医嘱' ? 'is-active' : '']"
                data-li="49-2"
                :id="item.bllb"
                @click.stop="clickTree(item.bllb, '临时医嘱', {}, true, item.name)"
              >
                临时医嘱
              </div>
            </div>
            <!-- 报告单 -->
            <div v-if="item.bllb == 2000002 && !item.collapse">
              <div
                v-for="(jitem, jindex) in item.list"
                :key="jindex"
                :id="jitem.type"
                :class="['li-left-item-li', parentType == item.bllb && is_active == jitem.type ? 'is-active' : '']"
                @click.stop="clickTree(item.bllb, jitem.name, jitem, true, item.name)"
              >
                {{ jitem.name }}
              </div>
            </div>
            <!-- 病程记录、手术记录 -->
            <div v-if="([294, 303, 329, 288, 87, 43, 2000185, 34].includes(item.bllb) || item.name === '会诊记录') && !item.collapse">
              <div
                v-for="(jitem, jindex) in item.list"
                :class="['li-left-item-li', parentType == item.bllb && is_active == jitem.blbh ? 'is-active' : '']"
                :key="jindex"
                :id="`${jitem.blbh}`"
                :title="jitem.name.trim()"
                @click.stop="clickTree(item.bllb, jitem.name, jitem, true, item.name)"
              >
                {{ jitem.name.trim() }}
              </div>
            </div>
          </li>
        </ul>
      </div>
      <div class="cont-left-file" @mouseup.prevent="handleMouseupHandle" @contextmenu.prevent="openMenu($event)">
        <div v-if="is_active == 0">
          <!-- <mainHomePage :data="mainHomeData" ref="main" :ifFile="ifFile"></mainHomePage> -->
          <medicalRecord :zyh="valData" v-if="pageType === 'search'" :data="mainHomeData" :pageType="pageType" />
          <HospitalAdmissionPage v-else :zyh="valData" />
        </div>
        <div v-else-if="parentType == 292">
          <div class="sticky top-0 bg-white z-10 shadow-sm p-3">
            <div class="flex-container">
              <!-- 左侧按钮组 -->
              <div class="left-buttons">
                <el-radio-group v-model="radio1" @input="handleRadio1Change" v-if="htmlShow == 1 || showHJNR">
                  <el-radio-button label="编辑"></el-radio-button>
                  <el-radio-button label="保存"></el-radio-button>
                </el-radio-group>
              </div>

              <!-- 右侧按钮组 -->
              <div class="right-buttons">
                <el-radio-group v-model="radio2" @input="handleRadio2Change">
                  <el-radio-button label="TXT"></el-radio-button>
                  <el-radio-button label="格式化"></el-radio-button>
                  <el-radio-button label="HTML"></el-radio-button>
                </el-radio-group>
              </div>
            </div>
          </div>
          <!-- 入院记录 -->
          <!-- <admissionRecord v-if="!admissionRecord.HTML_PRINT" :data="admissionRecord" :ifFile="ifFile"></admissionRecord>
          <NoFormatText :text="admissionRecord.HTML_PRINT" :data="admissionRecord" name="" v-else /> -->
          <!-- <el-input v-if="showHJNR" v-model="admissionRecord.HJNR" placeholder="请输入内容" type="textarea" :autosize="{ minRows: 40, maxRows: 80 }"></el-input> -->
          <TextEdit v-if="showHJNR" v-model="admissionRecord.HJNR" :data="admissionRecord" />
          <!-- <admissionRecord v-if="htmlShow === 1" :data="admissionRecord" :ifFile="ifFile"></admissionRecord> -->
          <AdmissionRecordNew v-if="htmlShow === 1" :patientInfo="admissionRecord" :ifFile="ifFile" />
          <NoFormatText v-if="htmlShow === 2" :text="admissionRecord.HTML_PRINT" :data="admissionRecord" name="" />
        </div>
        <div v-else-if="parentType == '1' && is_active == 1">
          <div class="sticky top-0 bg-white z-10 shadow-sm p-3">
            <div class="flex-container">
              <!-- 左侧按钮组 -->
              <div class="left-buttons">
                <el-radio-group v-model="radio1" @input="handleRadio1Change" v-if="htmlShow == 1 || showHJNR">
                  <el-radio-button label="编辑"></el-radio-button>
                  <el-radio-button label="保存"></el-radio-button>
                </el-radio-group>
              </div>

              <!-- 右侧按钮组 -->
              <div class="right-buttons">
                <el-radio-group v-model="radio2" @input="handleRadio2Change">
                  <el-radio-button label="TXT"></el-radio-button>
                  <el-radio-button label="格式化"></el-radio-button>
                  <el-radio-button label="HTML"></el-radio-button>
                </el-radio-group>
              </div>
            </div>
          </div>
          <!-- 出院记录 -->
          <!-- <OutHospitalRecord v-if="!outHospitalRecordData.HTML_PRINT" :data="outHospitalRecordData" />
          <NoFormatText :text="outHospitalRecordData.HTML_PRINT" :data="outHospitalRecordData" name="" v-else /> -->

          <!-- <el-input v-if="showHJNR" v-model="outHospitalRecordData.HJNR" placeholder="请输入内容" type="textarea" :autosize="{ minRows: 40, maxRows: 80 }"></el-input> -->
          <TextEdit v-if="showHJNR" v-model="outHospitalRecordData.HJNR" :data="outHospitalRecordData" />
          <OutHospitalRecord v-if="htmlShow === 1" :data="outHospitalRecordData"></OutHospitalRecord>
          <NoFormatText v-if="htmlShow === 2" :text="outHospitalRecordData.HTML_PRINT" :data="outHospitalRecordData" name="" />
        </div>
        <div v-else-if="[294, 329, 43, 2000185, 34].includes(Number(parentType)) && ![294, 329, 43, 2000185, 34].includes(Number(is_active))">
          <!-- 病程记录 -->
          <div class="sticky top-0 bg-white z-10 shadow-sm p-3">
            <div class="flex-container">
              <!-- 左侧按钮组 -->
              <div class="left-buttons">
                <el-radio-group v-model="radio1" @input="handleRadio1Change" v-if="htmlShow == 1 || showHJNR">
                  <el-radio-button label="编辑"></el-radio-button>
                  <el-radio-button label="保存"></el-radio-button>
                </el-radio-group>
              </div>

              <!-- 右侧按钮组 -->
              <div class="right-buttons">
                <el-radio-group v-model="radio2" @input="handleRadio2Change">
                  <el-radio-button label="TXT"></el-radio-button>
                  <el-radio-button label="格式化"></el-radio-button>
                  <el-radio-button label="HTML"></el-radio-button>
                </el-radio-group>
              </div>
            </div>
          </div>
          <div v-if="showHJNR">
            <!-- <el-input
              v-if="caseRecodeInfo && caseRecodeInfo.HJNR"
              v-model="caseRecodeInfo.HJNR"
              placeholder="请输入内容"
              type="textarea"
              :autosize="{ minRows: 40, maxRows: 80 }"
            ></el-input> -->
            <TextEdit v-if="caseRecodeInfo && caseRecodeInfo.HJNR" v-model="caseRecodeInfo.HJNR" :data="caseRecodeInfo" />
            <!-- <el-input
              v-else-if="caseRecodeInfo && caseRecodeInfo.content"
              v-model="caseRecodeInfo.content"
              placeholder="请输入内容"
              type="textarea"
              :autosize="{ minRows: 40, maxRows: 80 }"
            ></el-input> -->
            <TextEdit v-else-if="caseRecodeInfo && caseRecodeInfo.content" v-model="caseRecodeInfo.content" :data="caseRecodeInfo" />
          </div>
          <CaseRecord v-if="htmlShow === 1" :data="caseRecodeInfo" :ZYH="valData" />
          <NoFormatText v-if="htmlShow === 2" :text="caseRecodeInfo.HTML_PRINT" :data="caseRecodeInfo" name="" />
        </div>
        <div v-else-if="is_active == '会诊记录'">
          <!-- 会诊记录 -->
          <ConsultationRecordForm :patientInfo="patientInfoConsultationRecordForm" :basicInfoList="basicInfoList"></ConsultationRecordForm>
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
      <!-- status 不存在 意味着不脱敏、医院自助查询 -->
      <template v-if="!$route.query.status && isControl && evaluationIndexShow">
        <!-- 病案首页质控 -->
        <CaseQualityBox ref="CaseQualityBoxRef" :mainHomeData="mainHomeData" :MED_REC_ID="valData" type="type_v" :width="340" :height="820" @hightRight="hightRight" />
      </template>
      <!-- 添加质控结果 -->
      <CreateControlResultDialogVue
        v-if="dialogData.bSwitch"
        :AAA28="mainHomeData.AAA28"
        :data="dialogData"
        @refresh="handelRefreshResults"
        :MED_REC_ID="valData"
        :CWH="mainHomeData.CWH"
        :catalogName="catalogName"
        :AAA29="mainHomeData.AAA29"
        :JSKS="mainHomeData.AAC11C"
        @close="handleUpdate"
        :currentTreeItem="currentTreeItem"
      />

      <!-- 右键菜单 -->
      <div class="dialog-box">
        <el-dialog title="通知" :show-close="false" :visible.sync="gridCustomizeVisible" width="350px">
          请确认是否编辑整改通知
          <span slot="footer" class="dialog-footer">
            <el-button @click="gridCustomizeVisible = false">取 消</el-button>
            <el-button type="primary" @click="onCreate">确 认</el-button>
          </span>
        </el-dialog>
      </div>
      <div class="VueDragResize-box" v-if="is_VueDragResize">
        <!-- 弹窗 -->
        <!-- <VueDragResize :style="`z-index:${zInfex_0};`"
          dragHandle=".VueDragResize-title-box"
          :isActive="true" :isDraggable="true"
          :parentW="parentW" :parentH="parentH" :parentLimitation="true" :preventActiveBehavior="true"
          :w="width" :h="height"
          :minw="minw" :minh="minh"
          :x='left' :y='top' @dragstop="onDragstop"
          @resizing="resize" @dragging="resize" @deactivated="onDeactivated"
          > -->
        <div class="VueDragResize-centent-box">
          <div class="VueDragResize-title-box">
            <div class="title"><span>指标结果</span></div>
            <!-- <div class="icon-box">
                <span @click="clickcloseBtn">X</span>
              </div> -->
          </div>
          <div class="navbaerMag-content-box">
            <div class="list-box">
              <div class="list-item-box">
                <div class="listItem-title-box">
                  <span>指标名称：</span>
                  <span style="color: #ff0000">{{ show_name }}</span>
                  <span class="span-btn" @click="clickJsTitleBtn">指标计算</span>
                  <!-- <img src="../../assets/images/arrow-down.png" alt="" @click.stop="clickListItem"  class="arrow-down" :class="show_box?'show':''"/> -->
                </div>

                <div class="items-show-box" :class="show_box ? 'show' : ''">
                  <div class="xqItemError-box" v-for="(item, index) in xqItemError" :key="index">
                    <div class="items-titleBox">
                      <span class="idx-span">{{ index + 1 }}</span>
                      <el-tag :type="item.status == 1 ? 'success' : 'danger'">{{ item.status == 1 ? '正确' : '错误' }}</el-tag>
                      <!-- <img src="../../assets/images/arrow-down.png" alt="" @click.stop="clickxqItemErrorItems(index)"  class="arrow-down" :class="item.show ?'show':''"/> -->
                    </div>

                    <div class="itemsDiv-box" :class="item.show ? 'show' : ''">
                      <div class="items-box" v-for="(items, idx) in item.content" :key="idx">
                        <div class="items-content" v-html="items.content" :style="`color: ${items.status == 1 ? '#000' : '#ff0000'};`"></div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <!-- </VueDragResize> -->
      </div>
    </div>

    <Loading />
  </div>
</template>
<script>
import AdmissionRecordNew from '@/components/records-form/admission-record';
import ConsultationRecordForm from '@/components/records-form/consultation-record';
import medicalRecord from '@/views/medicalRecord';
import HospitalAdmissionPage from '@/views/data/query/details.vue';
import CreateControlResultDialogVue from '@/views/recordsRoom/qc/components/CreateControlResultDialog.vue';
import Mmenu from '@/components/m-menu';
import LoadingComponent from '@/components/loading/index';
import mainHomePage from '@/views/allcase/contFile/mainHomePage';
import newContFile from '@/views/allcase/contFile/newContFile';
import admissionRecord from '@/views/allcase/contFile/admissionRecord2';
import medicalAdvice from '@/views/allcase/contFile/medicalAdvice';
import medicalTemporary from '@/views/allcase/contFile/medicalTemporary';
import caseImageText from '@/views/allcase/report/caseImageText';
import ultrasound from '@/views/allcase/report/ultrasound';
import imaging from '@/views/allcase/report/imaging';
import electrocar from '@/views/allcase/report/electrocar';
import checkout from '@/views/allcase/report/checkout';
import sightGlass from '@/views/allcase/report/sightGlass';
import CaseRecord from './components/CaseRecord2.vue';
import ShouShuRecord1 from './components/ShouShuRecord1.vue';
import ShouShuRecord2 from './components/ShouShuRecord2.vue';
import ShouShuRecord4 from './components/ShouShuRecord4.vue';
import NoFormatText from './components/NoFormatText.vue';
import TextEdit from './components/TextEdit.vue';
import OutHospitalRecord from './components/OutHospitalRecord.vue';
import CaseQualityBox from '@/views/allcase/components/CaseQualityBox2.vue';
import DeathText from './components/DeathText.vue';
import VueDragResize from 'vue-drag-resize';
import Axios from 'axios';
import { Loading } from 'element-ui';
import { getToken, removeToken } from '@/utils/auth';
import { getHomeData } from '@/api/qc';

export default {
  name: 'qc-caseViews',
  components: {
    Mmenu,
    LoadingComponent,
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
    VueDragResize,
    CreateControlResultDialogVue,
    TextEdit,
    medicalRecord,
    HospitalAdmissionPage,
    AdmissionRecordNew,
    ConsultationRecordForm
  },
  directives: {},
  filters: {},
  extends: {},
  mixins: {},
  props: {},
  data() {
    return {
      patientInfoConsultationRecordForm: {},
      basicInfoList: [],
      evaluationIndexShow: true,
      topMtnShow: true,
      catalogName: '病案首页',
      currentTreeItem: {},
      gridCustomizeVisible: false,
      currentTreeNodeRow: {},
      radio1: '',
      blbh: '',
      pageType: 'normal',
      radio2: '',
      type_v: '', // v2 表示从（事中）进入
      fullscreenLoading: false,
      mainHomeData: {},
      signature: {},
      htmlShow: 1,
      showHJNR: false,
      admissionRecord: {},
      longAdvice: {},
      happensAdvice: {},
      valData: '',
      ruleId: '',
      ifFile: false,
      treeList: [],
      is_active: 0,
      parentType: 0,
      name_title: '',
      text: [],
      update: true,
      titleName: '病案首页',
      pacsDetail: {},
      // 病程记录详情
      caseRecodeInfo: {
        is_format: 1,
      },
      surgeryData: {
        mzfj: [],
        ssqk: [],
        sscxsj: [],
        sslb: [],
        is_format: 0,
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
        cyyz: {},
      },
      dataObj: [],
      results: null,
      is_tm_path: ['/hospital-caseViews', '/embedIndex-caseViews', '/reviewIndex-caseViews', '/whitelist-caseViews', '/whitelist-search'],
      isControl: false,

      // 弹窗信息
      width: 0,
      height: 0,
      minw: 620,
      minh: 340,
      parentH: 0,
      parentW: 0,
      top: 1,
      left: 500,
      zInfex_0: 9999,
      is_VueDragResize: false,
      tabStatus: 1,
      show_box: false,
      xqItemError: [],
      show_name: '',
      dialogData: {
        bSwitch: false,
        text: '',
        blbh: '',
      },
      is_active_blbh: 0,
    };
  },
  computed: {
    bcjlLiIds() {
      // 病程记录子项数据
      const arr = Object.values(this.treeList).filter(item => item.bllb === 294);
      const liIds = [];
      if (arr.length) {
        if (arr[0].list) {
          for (let i = 0; i < arr[0].list.length; i++) {
            liIds.push(arr[0].list[i].blbh);
          }
        }
      }
      return liIds;
    },
    shoushuLiIds() {
      // 病程记录子项数据
      const arr = Object.values(this.treeList).filter(item => item.bllb === 303);
      const liIds = [];
      if (arr.length) {
        if (arr[0].list) {
          for (let i = 0; i < arr[0].list.length; i++) {
            liIds.push(arr[0].list[i].blbh);
          }
        }
      }
      return liIds;
    },
    blname_title() {
      let title;
      const type = this.surgeryData.type;
      if (type === 1) {
        title = '手术风险评估表';
      } else if (type === 2) {
        title = '手术安全核查表';
      } else if (type === 3) {
        title = '手术同意书';
      } else if (type === 4) {
        title = '手术记录';
      }
      return title;
    },
  },
  watch: {
    gridCustomizeVisible(val) {
      if (val) {
        //点击事件，调用方法
        document.body.addEventListener('click', this.closeMenu);
      } else {
        document.body.removeEventListener('click', this.closeMenu);
      }
    },
  },
  created() {
    // 弹窗
    let getViewportSize = this.$getViewportSize();
    this.parentH = getViewportSize.height; // 组件范围
    this.parentW = getViewportSize.width; // 组件范围
    this.width = 400; // 可拖动div 宽
    this.height = Number(getViewportSize.height - 100); // 可拖动div 高度
    this.left = Number(getViewportSize.width) - Number(this.width) - 40;
    this.top = 60;
    this.type_v = this.$route.query.type_v;
    this.show_name = this.$route.query.show_name ? this.$route.query.show_name : '';
    this.pageType = this.$route.query.pageType ? this.$route.query.pageType : 'normal';
    if (this.$route.query.from == 'majorIndexDetail') {
      this.is_VueDragResize = true;
      this.evaluationIndexShow = false;
    }

    if (this.$route.query.topBtn == 'top') {
      console.log('topBtn', this.$route.query.topBtn);
      this.topMtnShow = false;
    }
    this.isControl = localStorage.getItem('isControl') == 'true' ? true : false;

    this.valData = this.$route.query.from == 'expertQualityControl' ? this.$route.query.ZYH : this.storageGet('getData');
    this.blbh = this.$route.query.from == 'expertQualityControl' ? this.$route.query.blbh : '0';
    this.ruleId = this.storageGet('getDataRule');
    let xqItemError = this.storageGet('xqItemError');
    if (xqItemError) {
      this.xqItemError = JSON.parse(xqItemError);
      // this.is_VueDragResize= true;
    }
  },
  mounted() {
    this.getInitData();
  },
  activated() {},
  methods: {
    handleUpdate() {
      this.dialogData.bSwitch = false;
      this.getInitData();
      this.handelRefreshResults();
      this.isControl = true;
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
      this.dialogData.bSwitch = true;
    },
    // 鼠标事件
    handleMouseupHandle() {
      const text = window.getSelection().toString();
      if (text.trim().length) {
        this.dialogData.blbh = this.is_active_blbh;
        this.dialogData.text = text;
      }
    },
    handleRadio1Change(e) {
      if (e == '编辑') {
        this.showHJNR = true;
        this.htmlShow = 3;
      } else {
        this.funSave();
      }
    },
    handleRadio2Change(e) {
      if (e == 'HTML') {
        this.htmlShow = 2;
        this.showHJNR = false;
      } else if (e == '格式化') {
        this.htmlShow = 1;
        this.radio1 = null;
        this.showHJNR = false;
      } else {
        this.htmlShow = 3;
        this.radio1 = '编辑';
        this.showHJNR = true;
      }
    },
    funSave() {
      let params;
      if (this.parentType == 292) {
        params = { blbh: this.admissionRecord.blbh, hjnr: this.admissionRecord.HJNR };
      } else if (this.parentType == '1') {
        params = { blbh: this.outHospitalRecordData.blbh, hjnr: this.outHospitalRecordData.HJNR };
      } else {
        params = { blbh: this.blbh, hjnr: this.caseRecodeInfo.HJNR || this.caseRecodeInfo.content };
      }

      this.$axios2
        .post('/edit_hjnr', params)
        .then(res => {
          if (res.code == 200) {
            if (res.code == 200) {
              this.$message({
                message: `保存成功`,
                type: 'success',
              });

              if (this.parentType == 292) {
                this.execInHospital();
              } else if (this.parentType == '1') {
                this.execOutHospital();
              } else {
                this.execHospital();
              }
            }
          }
        })
        .catch(err => {
          this.$message({
            message: `保存失败`,
            type: 'error',
          });

          this.showHJNR = false;
          this.htmlShow = 1;
          this.radio1 = null;
        });
    },
    setTreeCollapse(index) {
      this.$set(this.treeList[index], 'collapse', !this.treeList[index].collapse);
    },
    openAIModel() {
      if (!this.valData) {
        this.$message.error('住院号不存在！');
        return;
      }
      const { protocol, hostname, port } = window.location;
      Axios.post(`http://localhost:3003/iframe`, {
        path: `${protocol}//${hostname}:${port}/#/whitelist-caseControl?id=${this.valData}`,
      })
        .then(function (response) {
          // 处理成功的情况
          console.log(response.data);
        })
        .catch(function (error) {
          // 处理错误的情况
          console.log(error);
        });
    },
    getInitData() {
      if (this.valData) {
        if (this.$route.query.from !== 'forewarning') {
          if (this.$route.query.from != 'expertQualityControl') {
            this.is_active = 0;
            this.funQuery();
          }
        }
      }
      this.getTree();
    },
    clickzbjg() {
      // this.is_VueDragResize = true;
      this.is_VueDragResize = !this.is_VueDragResize;
    },
    clickListItem() {
      this.show_box = !this.show_box;
      this.resize();
    },
    clickJsTitleBtn() {
      const params = {
        category: this.$route.query.category,
        zyh: this.$route.query.zyh,
      };
      this.$axios2.post('/quality_index_recalculate', params).then(res => {
        if (res.code == 200) {
          this.$router.back();
        }
      });
    },
    clickxqItemErrorItems(i) {
      let xqItemError = this.xqItemError;
      xqItemError[i].show = !xqItemError[i].show;
      this.xqItemError = xqItemError;
      this.resize();
    },
    clickStatus(n) {
      this.tabStatus = Number(n);
    },
    clickcloseBtn() {
      this.is_VueDragResize = false;
    },
    // 拖拽时可以确定元素位置
    resize(newRect) {
      if (newRect) {
        this.width = newRect.width;
        this.height = newRect.height;
        this.top = newRect.top;
        this.right = newRect.right;
      }
    },
    onDeactivated(e) {},
    onDragstop(newRect) {},

    onControll() {
      this.$axios2
        .get(`/quality_handle?zyh=${this.valData}`, {
          timeout: 180000,
        })
        .then(res => {
          if (res.code == 200) {
            // this.valData = this.storageGet('getData');
            // if (this.valData) {
            //   if (this.$route.query.from !== 'forewarning') {
            //     this.funQuery();
            //   }
            //   this.getCaseQualityResults();
            // }
            // this.getTree();
            this.getInitData();
            this.$refs.CaseQualityBoxRef.getInitData();
          }
        });
    },
    onModuleControl() {
      // this.fullscreenLoading = true;
      const loading = this.$loading({
        lock: true,
        text: '质控中，请稍后',
        spinner: 'el-icon-loading',
        background: 'rgba(0, 0, 0, 0.7)',
      });
      this.$axios
        .get(`/single_patient_quality?zyh=${this.valData}`, {
          timeout: 3000,
        })
        .then(res => {
          if (res.code == 200) {
            this.getInitData();
            this.$refs.CaseQualityBoxRef.getInitData();
            loading.close();
          }
        })
        .catch(err => {
          loading.close();
        });
    },
    // 获取新病案指控结果
    getCaseQualityResults() {
      // const params = {
      //   id: Number(this.valData),
      //   ruleId: Number(this.ruleId)
      // };
      // if (this.type_v == 'v2') {
      //   // (事中)
      //   this.$axios2.post('/get_case_quality_v2', params).then(res => {
      //     this.results = null;
      //     this.$nextTick(() => {
      //       this.results = res.data;
      //     });
      //   });
      // } else {
      //   this.$axios2.post('/get_case_quality', params).then(res => {
      //     this.results = null;
      //     this.$nextTick(() => {
      //       this.results = res.data;
      //     });
      //   });
      // }
    },
    reload() {
      // 移除组件
      this.update = false;
      // 在组件移除后，重新渲染组件
      // this.$nextTick可实现在DOM 状态更新后，执行传入的方法。
      this.$nextTick(() => {
        this.update = true;
      });
    },
    funEdit() {
      this.ifFile = true;
      this.$message('errer:功能待开发');
    },
    getback() {
      this.$router.go(-1);
    },
    /**
     * 跳转对应病历首页
     */
    getBlankIndexss(item) {
      this.$refs.main.getBlankIndex(item);
    },
    funQuery() {
      // if (this.is_tm_path.includes(this.$route.path)) {
      //   params.is_tm = 1;
      // }
      // this.$axios.post('/medical_record', params).then(res => {
      //   this.mainHomeData = res.data;
      // });

      // const params1 = {
      //   id: this.valData,
      // };
      // getHomeData(params1).then(res => {
      //   // console.log("getHomeData",res.data);
      //   // this.mainHomeData = res.data;
      //   // this.is_active_blbh = this.mainHomeData.MED_REC_ID
      //   this.signature = res.data
      // });

      const params = {
        ZYH: this.valData,
      };
      this.$axios.post('/bmy/getBlDetails', params).then(res => {
        // console.log("getBlDetails",res.data);
        this.mainHomeData = res.data;
        this.is_active_blbh = this.mainHomeData.MED_REC_ID;
      });
    },

    getTree() {
      let that = this;
      let pramse = {
        id: this.valData,
      };
      this.$axios.post('/getTree', pramse).then(res => {
        that.treeList = res.data;
        // 当页面上级为预警信息时，设置默认

        const keys = Object.keys(this.treeList);
        if (this.$route.query.from === 'forewarning') {
          const item = this.treeList[keys[0]];
          this.clickTree(item.bllb, item.name, item, false, item.name);
        }
        if (this.$route.query.from == 'expertQualityControl') {
          this.setCurrentTreeNode();
        }
      });
    },
    setCurrentTreeNode() {
      const currentTreeRow = this.treeList[this.$route.query.currentKey];
      this.currentTreeNodeRow = currentTreeRow;
      if (this.$route.query.bllb && this.$route.query.bllb == '-1') {
        this.is_active = 0;
        this.funQuery();
      }
      if (this.$route.query.bllb && this.$route.query.bllb != '-1' && !this.$route.query.blbh) {
        // this.$message.success('请求多组')
        if (this.$route.query.specialName) {
          // 如果是医嘱
          this.clickTree(this.$route.query.bllb, this.$route.query.specialName, {}, true, '');
          // this.setTreeCollapse(this.$route.query.currentKey)
        } else {
          this.clickTree(this.$route.query.bllb, currentTreeRow.name, currentTreeRow, false, '');
        }
      }
      if (this.$route.query.blbh && this.$route.query.bllb) {
        if (Array.isArray(currentTreeRow.list)) {
          let currentChildRow = currentTreeRow.list.find(element => element.blbh == this.$route.query.blbh);
          currentChildRow && this.clickTree(this.$route.query.bllb, currentChildRow.name, currentChildRow, true, '');
          // currentChildRow && this.setTreeCollapse(this.$route.query.currentKey)
        }
      }
    },
    clickTree(b, n, item, isLeaf = false, catalogName = '') {
      console.log('clickTree', b, n, item, isLeaf);
      this.isControl = true;
      this.catalogName = catalogName;
      this.blbh = item?.blbh || '';
      this.currentTreeItem.blbh = '';
      if (item) {
        if (isLeaf) {
          if (n == '长期医嘱' || n == '临时医嘱') {
            this.is_active = n;
          } else if (b == '2000002') {
            // 报告单
            this.is_active = item.type;
          } else if (catalogName == '会诊记录') {
            // 报告单
            this.is_active = '会诊记录';
          } else {
            this.is_active = item.blbh;
          }
        } else {
          // 如果不是叶子节点
          this.is_active = b;
        }
      } else {
        // 病案首页
        this.is_active = 0;
        this.funQuery();
      }
      this.titleName = n;
      this.parentType = b;
      let that = this;
      that.name_title = n;
      console.log('>>>>>>>>>>>>', this.parentType, this.is_active);
      if (b != 0 && b != 292 && b != 1 && !isLeaf) {
        const params = {
          MED_REC_ID: that.valData,
          bllb: b,
        };
        if (this.is_tm_path.includes(this.$route.path)) {
          params.is_tm = 1;
        }
        that.$axios.post('/getAllCase', params).then(res => {
          that.text = res.data;
          that.dataObj = res.data;
          this.currentTreeItem.blbh = res.data.blbh;
          that.reload();
        });
      } else if (b == 292) {
        // 获取详情-入院记录
        const params = {
          id: this.valData,
          bllb: b,
        };
        if (this.is_tm_path.includes(this.$route.path)) {
          params.is_tm = '1';
        }
        console.log('这里1');
        that.$axios2.post('/get_case_platform', params).then(res => {
          this.admissionRecord = res.data;
          this.currentTreeItem.blbh = res.data.blbh;
          if (this.admissionRecord.HTML_PRINT) {
            this.htmlShow = 2;
            this.radio1 = null;
            this.radio2 = 'HTML';
            this.showHJNR = false;
          } else if (res.data.is_pormat === 1) {
            this.htmlShow = 1;
            this.radio1 = null;
            this.radio2 = '格式化';
            this.showHJNR = false;
          } else {
            this.showHJNR = true;
            this.htmlShow = 3;
            this.radio1 = '编辑';
            this.radio2 = 'TXT';
          }
        });
      } else if (b == 1) {
        // 获取详情-出院记录
        const params = {
          id: this.valData,
          bllb: b,
        };
        if (this.is_tm_path.includes(this.$route.path)) {
          params.is_tm = 1;
        }
        console.log('这里2');
        that.$axios2.post('/get_case_platform', params).then(res => {
          this.outHospitalRecordData = res.data;
          this.currentTreeItem.blbh = res.data.blbh;
          // if (this.outHospitalRecordData.HTML_PRINT) {
          //   this.htmlShow = 2;
          //   this.radio1 = null;
          //   this.radio2 = 'HTML';
          // } else {
          //   this.htmlShow = 1;
          //   this.radio1 = null;
          //   this.radio2 = '格式化';
          // }

          if (this.outHospitalRecordData.HTML_PRINT) {
            this.htmlShow = 2;
            this.radio1 = null;
            this.radio2 = 'HTML';
            this.showHJNR = false;
          } else if (res.data.is_pormat === 1) {
            this.htmlShow = 1;
            this.radio1 = null;
            this.radio2 = '格式化';
            this.showHJNR = false;
          } else {
            this.showHJNR = true;
            this.htmlShow = 3;
            this.radio1 = '编辑';
            this.radio2 = 'TXT';
          }
        });
      }
      if (that.is_active == '长期医嘱') {
        // 长期医嘱
        const params = {
          AAA28: that.valData,
        };
        if (this.is_tm_path.includes(this.$route.path)) {
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
        if (this.is_tm_path.includes(this.$route.path)) {
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
        if (this.is_tm_path.includes(this.$route.path)) {
          parm.is_tm = 1;
        }
        parm.zyh = item.ZYH;
        that.$axios.post('/get_pacs_data', parm).then(res => {
          this.pacsDetail = res.data;
        });
      }
      // 病程记录
      if ([294, 329, 43, 2000185, 34].includes(Number(b)) && isLeaf) {
        // if (that.is_active) {
        // 请求前先重置之前的数据
        that.caseRecodeInfo = {};
        let parm = { blbh: item.blbh };
        if (this.is_tm_path.includes(this.$route.path)) {
          parm.is_tm = 1;
        }
        that.$axios.post('/get_bc_data', parm).then(res => {
          that.caseRecodeInfo = res.data[0].bc_data;
          that.caseRecodeInfo.is_format = res.data[0].is_format;
          this.currentTreeItem.blbh = res.data[0].blbh;
          if (this.caseRecodeInfo.HTML_PRINT) {
            this.htmlShow = 2;
            this.radio1 = null;
            this.radio2 = 'HTML';
            this.showHJNR = false;
          } else if (res.data[0].is_format === 1) {
            this.htmlShow = 1;
            this.radio1 = null;
            this.radio2 = '格式化';
            this.showHJNR = false;
          } else {
            this.showHJNR = true;
            this.htmlShow = 3;
            this.radio1 = '编辑';
            this.radio2 = 'TXT';
          }
        });
        // }
      }
      
      if (catalogName === '会诊记录' && isLeaf) {
        let parm = { blbh: item.blbh, jzhm: item.jzhm };
        that.$axios.post('/getHzxx', parm).then(res => {
          this.patientInfoConsultationRecordForm = res.data[0];
          this.basicInfoList = res["data"]["基本信息"]
        });
        // }
      }

      // 手术记录
      if (b == 303 && isLeaf) {
        // if (that.is_active) {
        // 请求前先重置之前的数据
        that.surgeryData = {};
        let parm = { blbh: item.blbh };
        if (this.is_tm_path.includes(this.$route.path)) {
          parm.is_tm = 1;
        }
        that.$axios.post('/get_surgery_data', parm).then(res => {
          that.surgeryData = res.data[0].surgery_data;
          that.surgeryData.is_format = res.data[0].is_format;
          this.currentTreeItem.blbh = res.p[0].blbh;
        });
        // }
      }
    },

    execInHospital() {
      // 获取详情-入院记录
      const params = {
        id: this.valData,
        bllb: 292,
      };
      if (this.is_tm_path.includes(this.$route.path)) {
        params.is_tm = '1';
      }
      this.$axios2.post('/get_case_platform', params).then(res => {
        this.admissionRecord = res.data;
        this.currentTreeItem.blbh = res.data.blbh;
        if (this.admissionRecord.HTML_PRINT) {
          this.htmlShow = 2;
          this.radio1 = null;
          this.radio2 = 'HTML';
          this.showHJNR = false;
        } else if (res.data.is_pormat === 1) {
          this.htmlShow = 1;
          this.radio1 = null;
          this.radio2 = '格式化';
          this.showHJNR = false;
        } else {
          this.showHJNR = true;
          this.htmlShow = 3;
          this.radio1 = '编辑';
          this.radio2 = 'TXT';
        }
      });
    },
    execOutHospital() {
      const params = {
        id: this.valData,
        bllb: 1,
      };
      if (this.is_tm_path.includes(this.$route.path)) {
        params.is_tm = 1;
      }
      this.$axios2.post('/get_case_platform', params).then(res => {
        this.outHospitalRecordData = res.data;
        this.currentTreeItem.blbh = res.data.blbh;
        if (this.outHospitalRecordData.HTML_PRINT) {
          this.htmlShow = 2;
          this.radio1 = null;
          this.radio2 = 'HTML';
          this.showHJNR = false;
        } else if (res.data.is_pormat === 1) {
          this.htmlShow = 1;
          this.radio1 = null;
          this.radio2 = '格式化';
          this.showHJNR = false;
        } else {
          this.showHJNR = true;
          this.htmlShow = 3;
          this.radio1 = '编辑';
          this.radio2 = 'TXT';
        }
      });
    },
    execHospital() {
      this.$axios.post('/get_bc_data', { blbh: this.blbh }).then(res => {
        this.caseRecodeInfo = res.data[0].bc_data;
        this.caseRecodeInfo.is_format = res.data[0].is_format;
        this.currentTreeItem.blbh = res.data[0].blbh;
        if (this.caseRecodeInfo.HTML_PRINT) {
          this.htmlShow = 2;
          this.radio1 = null;
          this.radio2 = 'HTML';
          this.showHJNR = false;
        } else if (res.data[0].is_format === 1) {
          this.htmlShow = 1;
          this.radio1 = null;
          this.radio2 = '格式化';
          this.showHJNR = false;
        } else {
          this.showHJNR = true;
          this.htmlShow = 3;
          this.radio1 = '编辑';
          this.radio2 = 'TXT';
        }
      });
    },

    //质控依据高亮方法
    hightRight(hightKeyWord, bllb, zyh) {
      const that = this;
      let betweenPart = [];
      betweenPart = that.getKeyWord(hightKeyWord);
      //自动选中某个菜单
      that.is_active = bllb;
      that.titleName = '入院记录';
      that.parentType = bllb;
      console.log('这里3');
      const params = { id: zyh, bllb: bllb, isHight: 1, keyWord: betweenPart };
      that.$axios2.post('/get_case_platform', params).then(res => {
        this.admissionRecord = res.data;
        this.currentTreeItem.blbh = res.data.blbh;
      });
    },
    //获取需要高亮的关键词
    getKeyWord(str) {
      const result = [];
      let startIndex = 0;
      let endIndex;

      // 查找第一个【的索引
      while ((startIndex = str.indexOf('【', startIndex)) !== -1) {
        // 查找当前【之后的第一个】的索引
        endIndex = str.indexOf('】', startIndex);

        // 确保找到了闭合的】
        if (endIndex !== -1) {
          // 截取【和】之间的内容（不包括【和】）
          const content = str.substring(startIndex + 1, endIndex);
          result.push(content);

          // 更新开始索引为当前】之后的位置，以便查找下一个【
          startIndex = endIndex + 1;
        } else {
          // 如果没有找到闭合的】，则退出循环
          break;
        }
      }
      return result;
    },
  },
};
</script>
<style lang="scss" scoped>
#MyDiv {
  margin: 0;
  padding: 0 !important;
}
.flex-container {
  display: flex;
  justify-content: space-between;
  width: 100%;
}

.left-buttons,
.right-buttons {
  display: flex;
  gap: 8px; // 按钮之间的间距
}

.right-buttons {
  justify-content: flex-end; // 右对齐
}
.treeTitle {
  cursor: pointer;
}
.header {
  margin: 10px 20px;
  text-align: right;
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.cont_container {
  height: calc(100% - 68px - 20px);
  display: flex;
  justify-content: center;
}
.cont-left-tiem {
  width: 250px;
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
  &::-webkit-scrollbar {
    width: 12px !important;
  }
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
  // cursor: pointer;
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
// 拖拽弹窗样式
.VueDragResize-box {
  // position: fixed;
  width: 400px;
  // height: 100%;
  // z-index: 9999;
  // top: 0;
  // left: 0;
  // pointer-events: none; /* 鼠标事件穿透 */
  & ::v-deep .vdr-stick {
    display: none;
  }
  & ::v-deep .vdr.active:before {
    display: none;
  }
  .VueDragResize-centent-box {
    width: 100%;
    height: 100%;
    background: #fff;
    box-shadow: 0 1px 8px 0 rgb(206 206 206);
    border-radius: 6px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    pointer-events: all; /* 鼠标事件穿透 */

    .vdr-icon {
      width: 28px;
      height: 28px;
      position: absolute;
      bottom: -4px;
      right: -4px;
      background: #ff0000;
      z-index: auto;
      display: block;
    }
    .VueDragResize-title-box {
      background: rgb(32 138 183);
      color: #fff;
      display: flex;
      align-items: center;
      justify-content: space-between;
      font-size: 14px;
      height: auto;
      height: 40px;
      padding: 0 12px;
      cursor: move;
      .icon-box {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        & > span {
          font-size: 12px;
          display: flex;
          align-items: center;
          justify-content: center;
          width: 20px;
          height: 20px;
          line-height: 20px;
          border: 1px solid #fff;
          border-radius: 50%;
          cursor: pointer;
        }
      }
    }
    .navbaerMag-content-box {
      flex: 1;
      height: calc(100% - 40px);
      overflow: auto;
      padding: 10px;
      .navbaerMag-tab-box {
        width: auto;
        display: flex;
        align-items: center;
        & > div {
          cursor: pointer;
          width: 82px;
          font-size: 14px;
          display: flex;
          align-items: center;
          justify-content: center;
          padding: 6px 0;
          border: 1px solid rgb(23 80 154);
          &.hover-items {
            background: rgb(23 80 154);
            color: #fff;
          }
        }
      }
      .list-box {
        width: 100%;
        .list-item-box {
          width: 100%;
          border-radius: 4px;
          padding: 10px;
          margin-top: 14px;
          background: rgb(241 246 255);
          font-size: 14px;
          .listItem-title-box {
            display: flex;
            align-items: center;
            & span {
              font-size: 14px;
            }
            .span-btn {
              display: inline-block;
              background: #2b8f53;
              margin-left: 6px;
              font-size: 12px;
              color: #fff;
              padding: 6px 12px;
              border-radius: 4px;
              text-align: center;
              cursor: pointer;
            }
            .arrow-down {
              width: 12px;
              height: 12px;
              margin-left: 12px;
              cursor: pointer;
            }
            .arrow-down.show {
              transform: rotate(180deg);
            }
          }
          .items-show-box {
            height: auto;
          }
          .items-show-box.show {
            height: 0;
            overflow: hidden;
          }
          .xqItemError-box {
            padding: 10px 0;
            border-bottom: 1px solid rgb(211, 225, 243);
            .items-titleBox {
              display: flex;
              align-items: center;
              margin-top: 10px;
              .idx-span {
                display: inline-block;
                width: 20px;
                height: 20px;
                border-radius: 50%;
                line-height: 20px;
                text-align: center;
                background: rgb(23 80 154);
                color: #fff;
                margin-right: 4px;
              }
              & ::v-deep .el-tag {
                height: 26px;
                line-height: 26px;
              }
              .arrow-down {
                width: 12px;
                height: 12px;
                margin-left: 12px;
                cursor: pointer;
              }
              .arrow-down.show {
                transform: rotate(180deg);
              }
            }
            .itemsDiv-box {
              height: auto;
            }
            .itemsDiv-box.show {
              height: 0;
              overflow: hidden;
            }
            .items-box {
              margin-top: 10px;
              padding-left: 10px;
              box-sizing: border-box;
              .items-content {
                font-size: 14px;
                margin-top: 6px;
                line-height: 20px;
              }
            }
          }
        }
      }
    }
  }
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
        border: 1px solid #c0c4cc;
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

.keyHight {
  background-color: red !important;
  font-weight: bold !important;
}
.zbjg-box::v-deep .el-button--primary {
  background: #208ab7;
}
</style>
