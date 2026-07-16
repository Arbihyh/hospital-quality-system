<template>
  <div class="CaseQualityBox2">
    <!-- 标题 -->
    <!-- <div class="title-content">
      <div class="title-contentIcon">
        <el-image class="zsIcon" :src="require('@/assets/images/zsicon.png')" fit="contain"></el-image>
        病历智审结果
      </div>
      <i class="el-icon-close" @click="closeClick"></i>
    </div>-->
    <!-- tab选项卡 -->
    <div v-if="showPage === 1">
      <el-tabs
        class="custom-tabs"
        v-model="activeName"
        :stretch="true"
        v-if="!($route.path == '/whitelist-qualityResults' || $route.path == '/whitelist-bmyQualityResult')"
      >
        <el-tab-pane v-for="item in tabList" :key="item.name" :label="item.label" :name="item.name">
          <span slot="label">
            {{ item.label }}
            <span
              v-if="item.hasMessage && item.medical && item.medical !== '0'"
              class="dot"
            >{{ item.medical }}</span>
          </span>
        </el-tab-pane>
      </el-tabs>
      <!-- 病案首页 -->
      <div class="first-content" v-if="activeName === 'first'">
        <div v-if="$route.query.from != 'review'">
          <div
            class="score-box score-box_bl"
            :class="{
            scoreLevel_1_1: scoreLevel_ylzc == '优',
            scoreLevel_2_2: scoreLevel_ylzc == '良',
            scoreLevel_3_3: scoreLevel_ylzc == '中',
            scoreLevel_4_4: scoreLevel_ylzc == '差',
          }"
          >
            <span>首页评分</span>
            <span class="score">{{ resultsList.score && resultsList.score.score }}</span>
            <el-image
              v-if="scoreLevel_ylzc == '优'"
              class="level"
              style="width: 47px; height: 41px"
              :src="require('@/assets/images/you.png')"
              fit="contain"
            ></el-image>
            <el-image
              v-if="scoreLevel_ylzc == '良'"
              class="level"
              style="width: 47px; height: 41px"
              :src="require('@/assets/images/liang.png')"
              fit="contain"
            ></el-image>
            <el-image
              v-if="scoreLevel_ylzc == '中'"
              class="level"
              style="width: 47px; height: 41px"
              :src="require('@/assets/images/zhong.png')"
              fit="contain"
            ></el-image>
            <el-image
              v-if="scoreLevel_ylzc == '差'"
              class="level"
              style="width: 47px; height: 41px"
              :src="require('@/assets/images/cha.png')"
              fit="contain"
            ></el-image>
          </div>
          <div class="legend-box">
            <span class="qz">强制</span>
            <span class="jy">建议</span>
          </div>
        </div>
        <div class="suggest-content" v-for="(items, index) in resultsList.list" :key="index">
          <div class="cont-reight-bottom" @click="toJump(items.basis[0], items, index)">
            <div
              :class="items.level == 1 ? 'cont-reight-bottom-title-null' : 'cont-reight-bottom-title'"
            >
              <span v-if="items.category == 0">A类</span>
              <span v-if="items.category == 1">B类</span>
              <span v-if="items.category == 2">C类</span>
              <span v-if="items.category == 3">D类</span>
              <span v-if="items.category == 4">其他</span>
              <span>-{{ items.down }}</span>
            </div>
            <div class="cont-reight-bottom-conter">
              <div class="cont-reight-bottom-conter-flex">
                <p>
                  <span class="bold">字段：</span>
                  {{ items.error_name }}
                </p>
                <el-image
                  class="zsIcon"
                  v-if="items.is_artificial == 0"
                  :src="require('@/assets/images/zsicon.png')"
                  fit="contain"
                ></el-image>
                <el-image
                  class="ysIcon"
                  v-if="items.is_artificial == 1"
                  :src="require('@/assets/images/ysicon.png')"
                  fit="contain"
                ></el-image>
              </div>
              <p>
                <span class="bold">提示：</span>
                {{ items.desc }}
              </p>
              <!-- <p>
                    <span class="bold">质控依据：</span>
                    {{ items.basis }}
              </p>-->
            </div>
          </div>

          <QualityControlBasis
            :basis-data="items.basis"
            :initially-expanded="items.show"
            :index="index"
            content-type="1"
            @toggle="clickListItem"
          />

          <div class="btn-content">
            <div
              class="btn-left"
              v-if="$route.query.from == 'review' || $route.path == '/whitelist-qualityResults'"
            >
              <div
                class="appeal_progress"
                style="cursor: pointer;"
                v-if="items.type == 2 && items.status == 0"
                @click="openAppealDialog('appeal_ing', items, 1)"
              >申诉中</div>
              <div
                class="appeal_yes"
                v-if="items.type == 2 && items.status == 10000000"
                @click="openAppealDialog('appeal_yes', items, 1)"
              >通过</div>
              <div
                class="appeal_no"
                v-if="items.type == 2 && items.status == 20000000"
                @click="openAppealDialog('appeal_no', items, 1)"
              >驳回</div>
              <div
                class="appeal_in_edit"
                @click="clickAppealEdit(items, 1)"
                v-if="$route.path == '/whitelist-qualityResults' && items.is_artificial == 1"
              >已整改</div>
            </div>
            <div class="btn-left" v-else>
              <div class="appeal_yes" v-if="items.is_correction == 1">已整改</div>
            </div>
            <div
              class="btn-right"
              v-if="$route.path == '/whitelist-qualityResults' && items.type == 0"
            >
              <div class="appeal_in_yes" @click="openAppealDialog('appeal', items, 1)">申诉</div>
              <div class="appeal_in_no" @click="openAppealDialog('appeal_in_ignore', items, 1)">忽略</div>
            </div>
            <div
              class="btn-right"
              v-if="$route.query.from == 'review' && items.type == 2 && items.status == 0"
            >
              <div class="appeal_in_yes" @click="openAppealDialog('appeal_in_yes', items, 1)">通过</div>
              <div class="appeal_in_no" @click="openAppealDialog('appeal_in_no', items, 1)">驳回</div>
            </div>
          </div>
          <div class="hz"></div>
        </div>
      </div>
      <!-- 编码员 -->
      <div class="first-content" v-if="activeName === 'third'">
        <div v-if="$route.query.from != 'review'">
          <div
            class="score-box score-box_bl"
            :class="{
            scoreLevel_1_1: scoreLevel_ylzc == '优',
            scoreLevel_2_2: scoreLevel_ylzc == '良',
            scoreLevel_3_3: scoreLevel_ylzc == '中',
            scoreLevel_4_4: scoreLevel_ylzc == '差',
          }"
          >
            <span>首页评分</span>
            <span class="score">{{ controls.score.score }}</span>
            <el-image
              v-if="scoreLevel_ylzc == '优'"
              class="level"
              style="width: 47px; height: 41px"
              :src="require('@/assets/images/you.png')"
              fit="contain"
            ></el-image>
            <el-image
              v-if="scoreLevel_ylzc == '良'"
              class="level"
              style="width: 47px; height: 41px"
              :src="require('@/assets/images/liang.png')"
              fit="contain"
            ></el-image>
            <el-image
              v-if="scoreLevel_ylzc == '中'"
              class="level"
              style="width: 47px; height: 41px"
              :src="require('@/assets/images/zhong.png')"
              fit="contain"
            ></el-image>
            <el-image
              v-if="scoreLevel_ylzc == '差'"
              class="level"
              style="width: 47px; height: 41px"
              :src="require('@/assets/images/cha.png')"
              fit="contain"
            ></el-image>
          </div>
          <div class="legend-box">
            <span class="qz">强制</span>
            <span class="jy">建议</span>
          </div>
        </div>
        <div class="suggest-content" v-for="(items, index) in controls.list" :key="index">
          <div class="cont-reight-bottom" @click="toJump(items.basis[0], items, index)">
            <div
              :class="items.level == 1 ? 'cont-reight-bottom-title-null' : 'cont-reight-bottom-title'"
            >
              <span v-if="items.category == 0">A类</span>
              <span v-if="items.category == 1">B类</span>
              <span v-if="items.category == 2">C类</span>
              <span v-if="items.category == 3">D类</span>
              <span v-if="items.category == 4">其他</span>
              <span>-{{ items.down }}</span>
            </div>
            <div class="cont-reight-bottom-conter">
              <div class="cont-reight-bottom-conter-flex">
                <p>
                  <span class="bold">字段：</span>
                  {{ items.field_name }}
                </p>
                <!-- <el-image class="zsIcon" :src="require('@/assets/images/zsicon.png')"
                fit="contain"></el-image>-->
                <el-image
                  class="zsIcon"
                  v-if="items.is_artificial == 0"
                  :src="require('@/assets/images/zsicon.png')"
                  fit="contain"
                ></el-image>
                <el-image
                  class="ysIcon"
                  v-if="items.is_artificial == 1"
                  :src="require('@/assets/images/ysicon.png')"
                  fit="contain"
                ></el-image>
              </div>
              <p>
                <span class="bold">提示：</span>
                {{ items.desc }}
              </p>
              <!-- <p>
                <span class="bold">质控依据</span>
                {{ items.basis }}
              </p>-->
            </div>
          </div>
          <!-- <div class="gist" @click="clickListItem(index, 2)">
            <div class="gist-zkyj">质控依据11&gt;&gt;</div>
          </div>
          <div class="list-basis-text-t" :class="items.show ? 'show' : ''">
            <div style="margin-bottom: 10px;">
              {{ items.basis }}
            </div>
          </div>-->
          <QualityControlBasis
            :basis-data="items.basis"
            :initially-expanded="items.show"
            :index="index"
            content-type="2"
            @toggle="clickListItem"
          />
          <div class="btn-content">
            <div
              class="btn-left"
              v-if="$route.query.from == 'review' || $route.path == '/whitelist-bmyQualityResult'"
            >
              <div
                class="appeal_progress"
                style="cursor: pointer;"
                v-if="items.type == 2 && items.status == 0"
                @click="openAppealDialog('appeal_ing', items, 3)"
              >申诉中</div>
              <div
                class="appeal_yes"
                v-if="items.type == 2 && items.status == 1"
                @click="openAppealDialog('appeal_yes', items, 3)"
              >通过</div>
              <div
                class="appeal_no"
                v-if="items.type == 2 && items.status == 2"
                @click="openAppealDialog('appeal_no', items, 3)"
              >驳回</div>
              <div
                class="appeal_in_edit"
                @click="clickAppealEdit(items, 3)"
                v-if="$route.path == '/whitelist-bmyQualityResult' && items.is_artificial == 1"
              >已整改</div>
            </div>
            <div class="btn-left" v-else>
              <div class="appeal_yes" v-if="items.is_correction == 1">已整改</div>
            </div>
            <div
              class="btn-right"
              v-if="$route.path == '/whitelist-bmyQualityResult' && (items.type == 0 || items.type == 1 || (items.type == 2 && items.status == 2))"
            >
              <div class="appeal_in_yes" @click="openAppealDialog('appeal', items, 3)">申诉</div>
              <div class="appeal_in_no" @click="openAppealDialog('appeal_in_ignore', items, 3)">忽略</div>
            </div>
            <div
              class="btn-right"
              v-if="$route.query.from == 'review' && items.type == 2 && items.status == 0"
            >
              <div class="appeal_in_yes" @click="openAppealDialog('appeal_in_yes', items, 3)">通过</div>
              <div class="appeal_in_no" @click="openAppealDialog('appeal_in_no', items, 3)">驳回</div>
            </div>
          </div>
          <div class="hz"></div>
        </div>
      </div>
      <!-- 住院病历 -->
      <div class="second-content" v-if="activeName === 'second'">
        <div
          v-if="$route.query.from != 'review'"
          class="score-box"
          :class="scoreLevel == '甲' ? 'scoreLevel_1' : scoreLevel == '乙' ? 'scoreLevel_2' : scoreLevel == '丙' ? 'scoreLevel_3' : ''"
        >
          <span style="margin-top: -10px">
            病案评分
            <span class="score-f">{{ data.score }}</span>
          </span>
        </div>
        <div class="suggest-content" v-for="(items, index) in medicalRecord" :key="index">
          <div class="cont-reight-bottom">
            <div class="list-left-score" :class="items.level == 1 ? 'hover-1' : 'hover-2'">
              <div>{{ items.level == 1 ? '必改' : '建议' }}</div>
              <div>-{{ items.score }}</div>
            </div>
            <div class="cont-reight-bottom-conter">
              <div class="cont-reight-bottom-conter-flex">
                <p>
                  <span class="bold">字段：</span>
                  {{ items.error_field }}
                </p>
                <el-image
                  class="zsIcon"
                  v-if="items.is_artificial == 0"
                  :src="require('@/assets/images/zsicon.png')"
                  fit="contain"
                ></el-image>
                <el-image
                  class="ysIcon"
                  v-if="items.is_artificial == 1"
                  :src="require('@/assets/images/ysicon.png')"
                  fit="contain"
                ></el-image>
              </div>
              <p>
                <span class="bold">提示：</span>
                {{ items.notice }}
              </p>
            </div>
          </div>
          <div class="gist" @click="clickListItem(index, 3)">
            <div class="gist-zkyj">质控依据&gt;&gt;</div>
          </div>
          <div class="list-basis-text-t" :class="items.show ? 'show' : ''">
            <div v-for="(yItem, yIndex) of items.basis" :key="yIndex" style="margin-bottom: 10px;">
              <div v-if="items.rule_id !== 6">
                <span class="span-index">{{ yIndex + 1 }}</span>
                <span v-if="items.category == '入院记录'">
                  <span
                    v-for="(cItem, cIndex) of yItem"
                    :key="cIndex"
                    @click="hightRight(cItem, 292, item.JZHM)"
                    v-html="cItem"
                  ></span>
                </span>
                <span v-else>
                  <span v-for="(cItem, cIndex) of yItem" :key="cIndex" v-html="cItem"></span>
                </span>
              </div>
              <div v-else>
                <span class="span-index">1</span>
                <span style="font-size: 13px;">{{ yItem[0] }}</span>
              </div>
            </div>
          </div>
          <div class="btn-content">
            <div class="btn-left" v-if="$route.query.from == 'review'">
              <div
                class="appeal_progress"
                style="cursor: pointer;"
                v-if="items.appeal_type == 2 && items.appeal_status == 0"
                @click="openAppealDialog('appeal_ing', items, 2)"
              >申诉中</div>
              <div
                class="appeal_yes"
                v-if="items.appeal_type == 2 && items.appeal_status == 1"
                @click="openAppealDialog('appeal_yes', items, 2)"
              >通过</div>
              <div
                class="appeal_no"
                v-if="items.appeal_type == 2 && items.appeal_status == 2"
                @click="openAppealDialog('appeal_no', items, 2)"
              >驳回</div>
            </div>
            <div class="btn-left" v-else>
              <div class="appeal_yes" v-if="items.is_correction == 1">已整改</div>
            </div>
            <div
              class="btn-right"
              v-if="$route.query.from == 'review' && items.appeal_type == 2 && items.appeal_status == 0"
            >
              <div class="appeal_in_yes" @click="openAppealDialog('appeal_in_yes', items, 2)">通过</div>
              <div class="appeal_in_no" @click="openAppealDialog('appeal_in_no', items, 2)">驳回</div>
            </div>
          </div>
          <div class="hz"></div>
        </div>
      </div>
    </div>
    <AppealModal ref="AppealModalRef" @onUpdate="handleUpdate()" />

    <div v-if="showPage === 2">
      <AiPage ref="AiPageRef" />
    </div>

    <!-- 消息中心 -->
    <message-center ref="messageCenterRef" :titleType="titleType"></message-center>

    <ButtonPopUp
      ref="buttonPopUpRef"
      @showMessage="showMessage"
      :baseInfo="baseInfo"
      @generateMedicalRecords="generateMedicalRecords"
      :middleShow="middleShow"
    />
  </div>
</template>

<script>
import MessageCenter from '@/views/allcase/components/message-center-dialog';
import { getBrry, examineReview, getCaseQualityBazb, getAppealData, getNumberInfo, getAppealNumberInfo } from '@/api/qc';
import AppealModal from '@/components/appealModal/index.vue';
import ButtonPopUp from '@/components/button-pop-up';
import CaseQuality from './medical-record/CaseQualityBox2.vue';
import { setCorrection } from '@/api/qc';
import AiPage from './ai-page.vue';
import QualityControlBasis from '@/components/quality-control-basis/index.vue';

export default {
  emits: ['changeTab'],
  components: {
    AppealModal,
    ButtonPopUp,
    CaseQuality,
    AiPage,
    MessageCenter,
    QualityControlBasis,
  },
  props: {
    linkType: {
      type: String,
      default: '',
    },
  },
  data() {
    return {
      middleShow: false,
      showPage: 1,
      titleType: this.$route.path == '/whitelist-bmyQualityResult' ? '住院病历问题' : '病案首页问题',
      activeName: this.$route.path == '/whitelist-bmyQualityResult' ? 'third' : this.$route.path == '/whitelist-qualityResults' ? 'first' : 'second',
      tabList: [
        {
          label: '病案首页',
          name: 'first',
          hasMessage: true,
          medical: '',
        },
        {
          label: '住院病历',
          name: 'second',
          hasMessage: true,
          medical: '',
        },
        {
          label: '编码员',
          name: 'third',
          hasMessage: true,
          medical: '',
        },
      ],
      dialogVisible: false,
      dialogType: '',
      appealForm: {
        reason: '',
        phone: '',
      },
      appealRules: {
        doctor: [{ required: true, message: '请输入', trigger: 'blur' }],
        reason: [{ required: true, message: '请输入', trigger: 'blur' }],
      },
      data: {
        score: 100, // 示例数据
        data: [], // 示例数据
      },
      resultsList: {},
      controls: {
        ZYH: '',
        score: {
          score: 100,
        },
        list: [],
      },
      zkcodeIndex: 0,
      zk_codes: {
        qz: [],
        jy: [],
      },
      active_zk_index: 0,
      currentId: '',
      MEDRECID: '',
      medicalRecord: [],
      messageStatus: {
        first: true,
        second: false,
        third: false,
      },
      baseInfo: {},
      dialogFormsLabel: {
        zd_field_name: '',
        ts_desc: '',
      },
    };
  },
  computed: {
    scoreLevel() {
      /**
       * 甲＞90分
       * 乙75-90分
       * 丙＜75分
       * */
      let str;
      const { score } = this.data || {};
      console.log(score, 'score12');

      if (score > 90) {
        str = '甲';
      } else if (score < 75) {
        str = '丙';
      } else {
        str = '乙';
      }
      return str;
    },
    scoreLevel_ylzc() {
      /**
       * 优＞=97分
       * 良90-96分
       * 中75~89分
       * 差＜75分
       * */
      let str;
      const { score } = this.controls.score;

      if (score >= 97) {
        str = '优';
      } else if (score < 97 && score >= 90) {
        str = '良';
      } else if (score < 90 && score >= 75) {
        str = '中';
      } else {
        str = '差';
      }
      return str;
    },
  },
  watch: {
    activeName(val) {
      this.getTabsData();
      if (val == 'first' || 'second') {
        this.$emit('changeTab', '1');
      }
      if (val == 'third') {
        this.$emit('changeTab', '2');
      }
    },
  },
  created() {},
  mounted() {
    const { ZYH, id, qualityType } = this.$route.query;
    this.MEDRECID = this.$route.path == '/whitelist-qualityResults' ? id : ZYH;
    if (!(this.$route.path == '/whitelist-qualityResults' || this.$route.path == '/whitelist-bmyQualityResult')) {
      if (this.$route.query.from == 'review') {
        this.getAppealMessageNum();
      } else {
        this.getMessageNum();
      }
    }
    if (qualityType) {
      this.activeName = qualityType == 1 ? 'first' : qualityType == 3 ? 'third' : 'second';
    }
    this.getTabsData();
    this.startRefreshTimer();
    this.getBaseInfo();
  },

  methods: {
    startRefreshTimer() {
      let count = 0;
      const totalTimes = 5;
      setTimeout(() => {
        this.getTabsData();
        count++;

        if (count < totalTimes) {
          const intervalTimer = setInterval(() => {
            this.getTabsData();
            count++;

            if (count >= totalTimes) {
              clearInterval(intervalTimer);
              console.log('刷新完成，共执行5次');
            }
          }, 3000);
        }
      }, 6000);
    },
    formatLineBreak(str) {
      const result = str.replace(/\\n/g, '\n');
      return result;
    },
    getDialogTitle() {
      if (this.dialogType === 'appeal') {
        return '申诉';
      }
      if (this.dialogType === 'appeal_in_yes' || this.dialogType === 'appeal_yes') {
        return '通过';
      }
      if (this.dialogType === 'appeal_in_no' || this.dialogType === 'appeal_no') {
        return '驳回';
      }
    },

    getBaseInfo() {
      getBrry({ zyh: this.MEDRECID }).then(res => {
        if (res.code == 200) {
          this.baseInfo = res.data || {};
        }
      });
    },
    showMessage(type, msgNum) {
      this.showPage = 1;
      this.$refs.messageCenterRef.init(msgNum);
    },

    generateMedicalRecords() {
      this.showPage = 2;
      // this.$refs.AiPageRef.init()
    },

    getTaskNameList() {
      this.$axios2.get('/big_model/get_task_name', {}).then(res => {
        if (res.code == 200) {
          this.taskNameList = res.data || [];
        }
      });
    },
    getMessageNum() {
      getNumberInfo({ ZYH: this.MEDRECID }).then(res => {
        this.tabList[0].medical = res.data.errorV2;
        this.tabList[1].medical = res.data.medicalRecord;
        this.tabList[2].medical = res.data.homeQuality;
      });
    },
    getAppealMessageNum() {
      getAppealNumberInfo({ zyh: this.MEDRECID }).then(res => {
        this.tabList[0].medical = res.data.error_v2;
        this.tabList[1].medical = res.data.case_quality;
        this.tabList[2].medical = res.data.home_quality;
      });
    },
    hasMessage(tabName) {
      return this.messageStatus[tabName];
    },
    // 跳转锚点及高亮
    toJump(item, pItem, pIndex) {
      if (this.active_zk_index !== pIndex) {
        this.zkcodeIndex = 0;
        this.active_zk_index = pIndex;
      }
      const { user, zd, ss } = item.location;
      const arr = [];
      const { level } = pItem;
      const level_arr = {
        qz: [],
        jy: [],
      };
      if (user && user.length) {
        user.map(uItem => {
          const obj = {
            field: uItem,
            key: 'user',
          };
          arr.push(obj);

          if (level) {
            // 建议
            level_arr.jy.push(uItem);
          } else {
            // 强制
            level_arr.qz.push(uItem);
          }
        });
      }

      if (zd && zd.length) {
        zd.map(zItem => {
          zItem.key = 'zd';
          arr.push(zItem);

          var index;
          if (zItem.ZZPB) {
            index = 0;
          } else {
            index = zItem.DIA_ORDER;
          }
          if (level) {
            // 建议
            level_arr.jy.push(`zd-${index}-${zItem.field}`);
          } else {
            // 强制
            level_arr.qz.push(`zd-${index}-${zItem.field}`);
          }
        });
      }
      if (ss && ss.length) {
        ss.map(sItem => {
          sItem.key = 'ss';
          arr.push(sItem);

          var index;
          if (sItem.ZZPB) {
            index = 0;
          } else {
            index = sItem.OPE_ORDER;
          }
          if (level) {
            // 建议
            level_arr.jy.push(`ss-${index}-${sItem.field}`);
          } else {
            // 强制
            level_arr.qz.push(`ss-${index}-${sItem.field}`);
          }
        });
      }
      const maxIndex = arr.length - 1;
      this.$emit('zk', { level: 0, anchor: arr[this.zkcodeIndex], codes: level_arr });
      if (this.zkcodeIndex === maxIndex) {
        this.zkcodeIndex = 0;
      } else {
        this.zkcodeIndex++;
      }
    },
    // 获取编码员数据
    getData() {
      const params = {
        ZYH: this.MEDRECID,
        show_correction: this.$route.path == '/whitelist-bmyQualityResult' ? 2 : 1,
      };

      if (this.$route.query.from == 'review') {
        params.source = 'appeal';
      }

      this.$axios
        .post('/home_bmy_quality/bmyQualityResult', params, {
          headers: { isNoLoading: true },
        })
        .then(res => {
          console.log('编码员数据', res.data);
          const { qz, jy } = res.data;
          const list = [...qz, ...jy];
          this.$set(this, 'controls', { ...res.data, list });

          list.map(item => {
            item.show = true;
            item.basis.map(bItem => {
              const { user, zd, ss } = bItem.location || {};
              if (user && user.length) {
                user.map(uItem => {
                  if (item.level) {
                    // 建议
                    this.zk_codes.jy.push(uItem);
                  } else {
                    // 强制
                    this.zk_codes.qz.push(uItem);
                  }
                });
              }
              if (zd && zd.length) {
                zd.map(zItem => {
                  var index;
                  if (zItem.ZZPB) {
                    index = 0;
                  } else {
                    index = zItem.DIA_ORDER;
                  }
                  if (item.level) {
                    // 建议
                    this.zk_codes.jy.push(`zd-${index}-${zItem.field}`);
                  } else {
                    // 强制
                    this.zk_codes.qz.push(`zd-${index}-${zItem.field}`);
                  }
                });
              }
              if (ss && ss.length) {
                ss.map(sItem => {
                  var index;
                  if (sItem.ZZPB) {
                    index = 0;
                  } else {
                    index = sItem.OPE_ORDER;
                  }
                  if (item.level) {
                    // 建议
                    this.zk_codes.jy.push(`ss-${index}-${sItem.field}`);
                  } else {
                    // 强制
                    this.zk_codes.qz.push(`ss-${index}-${sItem.field}`);
                  }
                });
              }
            });
          });
          this.$emit('codes', this.zk_codes);
        });
    },
    // 获取病案首页
    getQualityResult() {
      const params = {
        id: this.MEDRECID,
        show_correction: this.$route.path == '/whitelist-qualityResults' ? 2 : 1,
      };

      if (this.$route.query.from == 'review') {
        params.source = 'appeal';
      }

      this.$axios
        .post('/home_quality/getQualityResult', params, {
          headers: { isNoLoading: true },
        })
        .then(res => {
          console.log('首页', res.data);
          this.resultsList = res.data; // 清空结果
          if (this.resultsList && this.resultsList.list && Array.isArray(this.resultsList.list)) {
            this.resultsList.list.map(item => {
              item.show = true;
            });
          }
        })
        .catch(e => {
          console.log(e);
        });
    },
    //  住院病历
    qualityBazb() {
      const params = {
        id: this.MEDRECID,
        show_correction: 1,
      };
      if (this.$route.query.from == 'review') {
        params.source = 'appeal';
      }
      getCaseQualityBazb(params).then(res => {
        if (res && res.data) {
          this.data.score = res.data.score;
          for (let i = 0; i < res.data.data.length; i++) {
            res.data.data[i].show = true;
          }
          this.$nextTick(() => {
            this.medicalRecord = res.data.data;
          });
        }
      });
    },
    async openAppealDialog(type, items, quality_type) {
      if (type == 'appeal_in_ignore') {
        this.$refs.AppealModalRef.handleIgnore(items, quality_type);
      } else {
        this.$refs.AppealModalRef.openAppealDialog(type, items, quality_type, 'single');
      }
    },
    handleUpdate() {
      this.getTabsData();
      this.getAppealMessageNum();
    },
    getTabsData(isNeedRefreshNum) {
      // 病案首页
      if (this.activeName == 'first') {
        this.getQualityResult();
      }
      //  住院病历
      if (this.activeName == 'second') {
        this.qualityBazb();
      }
      // 编码
      if (this.activeName == 'third') {
        this.getData();
      }
      if (isNeedRefreshNum) {
        this.getMessageNum();
      }
    },
    clickAppealEdit(item, quality_type) {
      console.log('>>>>', item);
      this.$confirm('是否确认已整改?', '提示', {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning',
        customClass: 'customClass-el-message-box-center',
      })
        .then(() => {
          setCorrection({
            id: item.id,
            quality_type,
          }).then(res => {
            if (res.code == 200) {
              this.$message.success('已整改成功！');
              this.getTabsData();
            }
          });
        })
        .catch(() => {});
    },
    clickListItem(idx, quality_type) {
      if (quality_type == 2) {
        this.controls.list[idx].show = !this.controls.list[idx].show;
      }
      if (quality_type == 3) {
        this.medicalRecord[idx].show = !this.medicalRecord[idx].show;
      }
      if (quality_type == 1) {
        this.resultsList.list[idx].show = !this.resultsList.list[idx].show;
      }
      this.$forceUpdate();
    },
    closeClick() {
      this.$emit('close'); // 触发 close 事件
    },
  },
};
</script>

<style lang="scss" scoped>
::v-deep .el-dialog__header {
  background-color: hsl(205.32deg 43.43% 49.22%);
}

::v-deep .el-dialog__close {
  color: #fff;
  border: 1px solid #fff;
  border-radius: 20px;
}

::v-deep .el-dialog__title {
  color: #fff;
}

::v-deep .el-descriptions-item__label {
  font-weight: bold;
  font-size: 15px;
}

::v-deep .el-tabs__item {
  padding: 0 15px;
}

.list-basis-text-t {
  height: 0;
  overflow: hidden;
  position: relative;

  &.show {
    height: auto;
    padding: 10px 0 10px 10px;
  }
}

.span-index {
  width: 20px;
  height: 20px;
  line-height: 20px;
  text-align: center;
  display: inline-block;
  border-radius: 50%;
  background: #185da6;
  color: #fff;
  margin-right: 10px;
  margin-bottom: 4px;
  font-size: 12px;
}

.btn-content {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: 10px;

  .btn-left {
    display: flex;
    align-items: center;
    gap: 10px;
  }

  .btn-right {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 10px;
    flex: 1;
  }

  .rebuttal {
    width: 28px;
    height: 28px;
    line-height: 28px;
    background-color: rgba(240, 31, 58, 1);
    color: rgba(255, 255, 255, 1);
    font-size: 12px;
    text-align: center;
    font-family: -regular;
    cursor: pointer;
    border-radius: 20px;
  }

  .disabled {
    cursor: not-allowed;
    opacity: 0.6;
  }
}

.suggest-content:last-child {
  .btn-content {
    margin-bottom: 30px;
  }
}

.hz {
  height: 20px;
}

.flow {
  margin: 15px 10px;

  span {
    width: 20px;
    height: 20px;
    display: inline-block;
    color: #fff;
    border-radius: 50%;
    text-align: center;
    line-height: 20px;
    background-color: blue;
  }
}

.gist {
  width: 93%;
  display: flex;
  font-size: 14px;
  cursor: pointer;
  padding: 0px 3px;
  color: #ed3028;
  line-height: 20px;
  align-items: center;
  justify-content: space-between;
  font-family: Source Han Sans CN-Regular, Source Han Sans CN;

  span {
    margin-left: 10px;
  }
}

.zsIcon {
  width: 35px;
  height: 35px;
}

.ysIcon {
  width: 30px;
  height: 30px;
}

.gist-center {
  width: 93%;
  font-size: 14px;
  padding: 0px 3px;
  line-height: 25px;
  align-items: center;
  justify-content: space-between;
  font-family: Source Han Sans CN-Regular, Source Han Sans CN;
}

.second-content {
  padding: 0 10px 10px 10px;
  width: 100%;
  height: calc(100% - 55px) !important;
  overflow-y: scroll;
  box-sizing: border-box;

  .score-second {
    width: 300px;
    height: 60px;
    margin: 0 auto;
    color: #fff;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: rgb(152, 112, 20);
  }

  .suggest-card {
    display: flex;
    align-items: center;
    justify-content: space-between;

    .card-left {
      width: 60px;
      height: 60px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 24px;
      line-height: -20px;
      align-content: center;
      border-right: 3px solid #409eff;
      flex-wrap: wrap;

      div {
        color: #409eff;
      }
    }

    .card-right {
      font-size: 14px;
      display: flex;
      flex-wrap: wrap;
      height: 40px;
      width: 80%;
      align-content: space-around;

      div {
        width: 100%;
      }
    }
  }

  .messageBox {
    flex: 1;
    display: flex;
    flex-direction: column;
    height: 100%;

    .message {
      flex: 1;
      margin-bottom: 20px;
      max-height: 85%;
      overflow-y: auto;
      height: 480px;
      background-color: white;
      border-radius: 10px;

      .user-message {
        display: flex;
        margin: 10px 10px 20px 10px;
        justify-content: right;

        .user-content {
          line-height: 40px;
          margin-right: 10px;
          border-radius: 5px;
          flex: 1;

          & div {
            background: #bee2f8;
            padding: 0 10px;
            float: right;
          }
        }
      }

      .record-message {
        display: flex;
        margin: 10px 10px 20px 10px;

        .record-content {
          margin-left: 10px;
          border-radius: 5px;
          line-height: 40px;
          flex: 1;

          & div {
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
            padding: 0 10px;
            float: right;
          }
        }
      }
    }

    .sendMessageBox {
      margin: 10px;
      padding: 10px;
      border: 1px solid #bbb;
      border-radius: 8px;
      display: flex;
      height: 150px;

      .inputText {
        height: 80px;
        border: none;
        outline: none;
        width: 100%;
        resize: none;
      }
    }
  }

  .score-box {
    width: 100%;
    margin-bottom: 16px;
    padding: 30px 50px 18px 20px;
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    color: #fff;

    div {
      font-size: 20px;
    }

    .ban {
      font-size: 20px;
      font-family: Source Han Sans CN-Medium, Source Han Sans CN;
      font-weight: 500;
      color: #ffffff;
      line-height: 40px;
      vertical-align: middle;
    }

    .score-f {
      padding-left: 20px;
      font-size: 20px;
    }

    .score-dj,
    .score-f {
      font-weight: bold;
      font-size: 40px;
    }
  }

  .score-box.scoreLevel_1 {
    background: rgb(11, 133, 63);
    // background-image: url('@/assets/images/icon-jia.png');
    background-repeat: no-repeat;
    background-size: 47px 41px;
    background-position: 80% 50%;
  }

  .score-box.scoreLevel_2 {
    background: rgb(152, 112, 20);
    // background-image: url('@/assets/images/icon-yi.png');
    background-repeat: no-repeat;
    background-size: 47px 41px;
    background-position: 80% 50%;
  }

  .score-box.scoreLevel_3 {
    background: rgb(199, 54, 13);
    // background-image: url('@/assets/images/icon-bing.png');
    background-repeat: no-repeat;
    background-size: 47px 41px;
    background-position: 80% 50%;
  }
}

.title-content {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px;
  font-size: 14px;

  .title-contentIcon {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-left: -15px;

    .zsIcon {
      width: 30px;
      height: 30px;
    }
  }

  .title-contentIcon span {
    margin-left: 10px;
  }

  .title-contentIcon .el-icon-close {
    cursor: pointer;
  }

  .title-contentIcon .el-icon-close:hover {
    color: red;
  }

  .el-icon-close {
    cursor: pointer;
  }
}

.score-content {
  width: 300px;
  height: 60px;
  margin: 0 auto;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 30px;
  border: 1px solid #000;
  position: relative;

  .triangle {
    width: 0;
    height: 0;
    position: absolute;
    top: 0;
    right: 0;
    border-left: 30px solid transparent;
    border-top: 30px solid red;
  }

  span {
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    position: absolute;
    top: 2px;
    right: 2px;
    color: #fff;
    z-index: 99;
  }
}

.control-box {
  position: fixed;
  top: 106px;
  // top: 70px;
  right: 13px;
  width: 400px;
  padding: 20px;
  background: #fff;
  border-radius: 5px;
  overflow-x: hidden;
  transition: all 0.5s;
}

::v-deep .el-dialog__body {
  padding-top: 10px;
}

.dot {
  display: inline-block;
  width: 20px;
  height: 20px;
  line-height: 20px;
  background-color: red;
  border-radius: 50%;
  color: #fff;
  text-align: center;
  margin: -10px 5px;
}

.first-content {
  padding: 0 10px 10px 10px;
  width: 100%;
  height: calc(100% - 55px) !important;
  overflow-y: scroll;
  box-sizing: border-box;

  .message-tip {
    display: inline-block;
    width: 20px;
    height: 20px;
    line-height: 23px;
    color: #fff;
    text-align: center;
    background-color: red;
    border-radius: 50%;
    margin-left: 5px;
    vertical-align: middle;
    position: absolute;
    top: 0px;
  }
}

.score-box {
  width: 100%;
  margin-bottom: 16px;
  padding: 30px 51px 18px 20px;
  border-radius: 4px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  color: #fff;
}

.score-box div {
  font-size: 20px;
}

.score-box .score-f {
  padding-left: 20px;
  font-size: 20px;
  font-weight: bold;
}

.score-box .score-dj {
  font-weight: bold;
}

.score-box span {
  font-size: 20px;
  font-family: Source Han Sans CN-Medium, Source Han Sans CN;
  font-weight: 500;
  color: #ffffff;
  line-height: 40px;
  vertical-align: middle;
}

.score-box .score {
  font-size: 40px;
  font-family: Source Han Sans CN-Medium, Source Han Sans CN;
  font-weight: 500;
  color: #ffffff;
  line-height: 40px;
  vertical-align: middle;
  margin-left: 20px;
}

.score-box .level {
  float: right;
}

/* score-box 不同等级的背景颜色（旧的等级样式） */
.score-box.scoreLevel_1_1 {
  background: #328240;
}

.score-box.scoreLevel_2_2 {
  background: #8ac410;
}

.score-box.scoreLevel_3_3 {
  background: #ef8a0d;
}

.score-box.scoreLevel_4_4 {
  background: #f0203a;
}

/* score-box 另一种样式 */
.score-box_bl {
  padding: 20px 50px 20px 20px;
}

.score-box_bl .score {
  margin-left: -30px;
}

.suggest-content {
  width: 100%;
  // height: 182px;
  line-height: 20px;
  background-color: #f1f5fe;
  color: rgba(16, 16, 16, 1);
  font-size: 14px;
  text-align: left;
  font-family: -regular;
  padding: 0 5px;
  margin-bottom: 15px;
}

.legend-box {
  text-align: center;
  margin: 10px 0 10px;

  span {
    position: relative;
    font-size: 14px;
    font-family: Source Han Sans CN-Regular, Source Han Sans CN;
    font-weight: 400;
    color: #666666;
    line-height: 20px;

    &:nth-child(1) {
      margin-right: 40px;
    }

    &::before {
      position: absolute;
      top: 5px;
      left: -20px;
      content: '';
      width: 10px;
      height: 10px;
      border-radius: 5px;
    }

    &.qz {
      &::before {
        background: #ed3028;
      }
    }

    &.jy {
      &::before {
        background: #78b2f1;
      }
    }
  }
}

.CaseQualityBox21 {
  height: 100%;
  position: relative;
}

.tabContent {
  height: calc(100% - 94px);
}

.taskNameListAndMessage {
  width: 100%;
  height: 100%;
  overflow-y: auto;
  background-color: white;
  border-radius: 10px;
  display: flex;

  .el-menu-vertical-demo {
    // width:340px;
    height: 100%;
    overflow-y: scroll;
    width: 230px;
  }
}

.bag-code {
  background-color: hsl(200deg 37.5% 96.86%);
  height: 32%;
}

.cont-reight-bottom {
  margin-bottom: 15px;
  display: flex;
  cursor: pointer;

  .list-left-score {
    width: 90px;
    height: 90px;
    text-align: center;
    font-size: 14px;
    font-weight: 700;
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 10px 0;
    flex-shrink: 0;

    &.hover-1 {
      background: rgb(254, 240, 240);
      color: rgb(238, 14, 14);
      border-right: 3px solid rgb(238, 14, 14);
    }

    &.hover-2 {
      background: rgb(236, 245, 255);
      color: rgb(52, 140, 235);
      border-right: 3px solid rgb(52, 140, 235);
    }

    & > div {
      font-size: 14px;
    }
  }

  .list-right-tips {
    flex: 1;
    font-size: 12px;
    padding-left: 10px;

    .notice-box {
      margin-top: 8px;
    }
  }
}

.cont-reight-bottom div span {
  font-size: 14px;
  margin-top: 10px;
}

.cont-reight-bottom-title {
  border-right: 3px solid #ed3028;
  width: 90px;
  height: 90px;
  line-height: 20px;
  background-color: rgba(254, 240, 240, 1);
  color: #ed3028;
  font-size: 14px;
  text-align: center;
  font-family: -regular;
  font-weight: bold;
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.cont-reight-bottom-title-null {
  width: 90px;
  height: 90px;
  background-color: rgba(254, 240, 240, 1);
  border-right: 3px solid #78b2f1;
  text-align: center;
  font-size: 14px;
  font-weight: bold;
  color: #78b2f1;
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.cont-reight-bottom-conter {
  margin-left: 8px;
  min-height: 70px;
  width: 100%;
  margin-top: 7px;
  line-height: 30px;

  .cont-reight-bottom-conter-flex {
    width: 90%;
    display: flex;
    justify-content: space-between;
    align-items: center;
  }

  p {
    font-size: 14px;
    color: #666666;
    width: 100%;
    word-break: break-all;
  }
}

.zkyj {
  font-size: 14px;
  font-family: Source Han Sans CN-Regular, Source Han Sans CN;
  color: #ed3028;
  line-height: 20px;
  cursor: pointer;
  padding: 0px 10px;
}

.bold {
  font-size: 14px !important;
  font-weight: 600 !important;
  color: #202020 !important;
}

.zsIcon {
  width: 35px;
  height: 35px;
}

.states-content {
  margin: 20px auto;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 22px;

  .states-box {
    font-size: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 50%;

    span {
      width: 14px;
      height: 14px;
      border-radius: 50%;
      background: red;
      margin-right: 8px;
    }
  }
}

.first-card {
  display: flex;
  align-items: center;
  justify-content: flex-start;
  width: 300px;
  margin: 0 auto;
  height: 60px;
  margin-bottom: 20px;

  .card-left {
    width: 60px;
    height: 60px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    line-height: -20px;
    align-content: center;
    background: rgba(255, 0, 0, 0.4);
    border-right: 3px solid red;
    flex-wrap: wrap;

    div {
      width: 60px;
      text-align: center;
    }
  }

  .card-right {
    font-size: 14px;
    // display: flex;
    flex-wrap: wrap;
    margin-left: 10px;
    height: 60px;
    line-height: 24px;
    align-content: space-around;
  }
}

.CaseQualityBox2 {
  width: 100%;
  height: 100%;
  display: flex;
  background-color: #fff;
  flex-direction: column;

  .custom-tabs {
    flex: 1;
    width: 100%;

    ::v-deep.el-tabs__content {
      // height: calc(100% - 55px) !important;
      // overflow-y: scroll;
      height: 0;
    }
  }
}

.list-item-title {
  font-family: PingFang-SC, PingFang-SC;
  font-weight: bold;
  font-size: 18px;
  color: #333333;
  line-height: 25px;
  text-align: left;
  font-style: normal;
  margin-bottom: 10px;
}

.list-item-basis-box {
  .list-basis-title {
    padding: 10px 0 10px 10px;
    position: relative;

    & > span {
      font-size: 12px;
      color: rgba(27, 100, 176, 1);
      font-weight: bold;
    }

    .typeImg {
      width: 34px;
      height: 34px;
      position: absolute;
      top: 0;
      right: 12px;
      z-index: 999;
    }
  }

  .list-basis-text {
    height: auto;

    .list-basis-text-t {
      height: 0;
      overflow: hidden;
      position: relative;

      & > div:last-child {
        margin-bottom: 0 !important;
      }

      &.show {
        height: auto;
        // padding: 10px 0 10px 10px;
      }

      .list-basis-text-t-noString {
        & > span:last-child {
          div {
            height: 0 !important;
          }
        }
      }
    }
  }

  .list-basis-bottom-box {
    margin-top: 14px;
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 10px;

    .list-basis-bottom-tips {
      flex: 1;
      display: flex;
      gap: 10px;
    }

    .list-basis-bottom-btn {
      flex: 1;
      display: flex;
      justify-content: flex-end;
      gap: 10px;
      // width: auto;
    }
  }
}
</style>
