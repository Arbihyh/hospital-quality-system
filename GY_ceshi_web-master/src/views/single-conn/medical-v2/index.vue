<template>
  <div class="medical-quality-page">
    <div class="page-container">
      <div class="main-content">
        <div class="nav-tabs" v-if="$route.path != '/whitelist-generate-case'">
          <QcTab
            v-if="$route.path != '/whitelist-generate-case'"
            :tabs="tabs"
            :active="activeTab"
            @change="handleTabChange"
          />
          <div v-if="activeTab !== 'generate' && activeTab === 'home'" class="nav-tabs__right">
            <span class="score-num">
              {{ topHomeInfo.score }}
              <span class="unit">分 |</span>
            </span>
            <span class="grade-value">{{ topHomeInfo.scoreLevel }}</span>
          </div>

          <div v-if="activeTab !== 'generate' && activeTab === 'record'" class="nav-tabs__right">
            <span class="score-num">
              {{ topRecordInfo.score }}
              <span class="unit">分 |</span>
            </span>
            <span class="grade-value">{{ topRecordInfo.scoreLevel }}</span>
          </div>

          <div
            v-if="showUserInfoBar"
            style="display: flex; align-items: center; gap: 8px; cursor: pointer; padding: 4px 8px; border-radius: 8px; transition: background 0.2s"
            id="userInfoContainer"
            @click="handleLogout()"
          >
            <div style="display: flex; align-items: center; gap: 6px">
              <div
                style="width: 20px; height: 20px; border-radius: 50%; overflow: hidden; border: 1px solid #cfe0ff"
              >
                <img
                  src="https://trae-api-cn.mchost.guru/api/ide/v1/text_to_image?prompt=cute%20cartoon%20doctor%20avatar%20with%20stethoscope%20on%20blue%20background&amp;image_size=square"
                  style="width: 100%; height: 100%; object-fit: cover"
                  alt="医生头像"
                />
              </div>
              <span
                style="font-size: 12px; color: var(--text-3); max-width: 80px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap"
                id="topUserTag"
                :title="staffTitle"
              >{{ staffInfo.staff_name }}</span>
              <i class="fas fa-chevron-down" style="font-size: 8px; color: #7b8794"></i>
            </div>
          </div>
        </div>
        <div style="display: flex; justify-content: space-between; align-items: center" v-else>
          <div class="page-title-btn">病历生成</div>
          <div
            v-if="staffInfo.staff_name"
            style="display: flex; align-items: center; gap: 8px; cursor: pointer; padding: 4px 8px; border-radius: 8px; transition: background 0.2s"
            id="userInfoContainer"
            @click="handleLogout()"
          >
            <div style="display: flex; align-items: center; gap: 6px">
              <div
                style="width: 20px; height: 20px; border-radius: 50%; overflow: hidden; border: 1px solid #cfe0ff"
              >
                <img
                  src="https://trae-api-cn.mchost.guru/api/ide/v1/text_to_image?prompt=cute%20cartoon%20doctor%20avatar%20with%20stethoscope%20on%20blue%20background&amp;image_size=square"
                  style="width: 100%; height: 100%; object-fit: cover"
                  alt="医生头像"
                />
              </div>
              <span
                style="font-size: 12px; color: var(--text-3); max-width: 80px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap"
                id="topUserTag"
                :title="staffTitle"
              >{{ staffInfo.staff_name }}</span>
              <i class="fas fa-chevron-down" style="font-size: 8px; color: #7b8794"></i>
            </div>
          </div>
        </div>

        <div class="panel-content">
          <div v-show="activeTab === 'home'">
            <div v-if="!homeList.length" class="empty-tip">暂无数据</div>
            <qc-card
              v-for="item in homeList"
              :key="item.id"
              route-type="RUN-HOME"
              :data-form="item"
              @openAppealDialog="openAppealDialog"
              @refresh="getQualityResult()"
            />
          </div>

          <div v-show="activeTab === 'record'">
            <div v-if="!recordList.length" class="empty-tip">暂无数据</div>
            <qc-card
              v-for="item in recordList"
              :key="item.id"
              route-type="RUN-RECORD"
              :data-form="item"
              @openAppealDialog="openAppealDialog"
              @refresh="getTableData()"
            />
          </div>

          <!-- 病历生成 -->
          <gen-editor
            ref="genEditorRef"
            v-show="activeTab === 'generate'"
            @loginSuccess="loginSuccess"
          />
        </div>
      </div>

      <div v-if="showLogout" class="confirm-modal show">
        <div class="confirm-box">
          <div class="confirm-title">退出登录</div>
          <div class="confirm-msg">确定要退出登录吗？</div>
          <div class="confirm-btns">
            <button class="confirm-btn confirm-btn-cancel" @click="logoutCancel">取消</button>
            <button class="confirm-btn confirm-btn-ok" @click="logoutConfirm">确定</button>
          </div>
        </div>
      </div>

      <!-- 底部状态栏 -->
      <bottom-bar
        v-if="activeTab !== 'generate'"
        ref="bottomBarRef"
        :dataEntity="recordData"
        @type-change="typeChange"
        @genPage="genPage"
        @open-message-center="$refs.messageCenterRef.init()"
        @open-quality-control="$refs.qualityControlRecordDialogRef.init()"
      />
    </div>
    <AppealModal ref="AppealModalRef" @onUpdate="handleUpdate()" />

    <!-- 消息中心 -->
    <message-center ref="messageCenterRef"></message-center>
    <!-- 质控记录 -->
    <QualityControlRecordDialog ref="qualityControlRecordDialogRef" />
  </div>
</template>

<script>
import QcTab from './comp/QcTab';
import QcCard from './comp/QcCard';
import GenEditor from './comp-v2/GenEditor';
import BottomBar from './comp/BottomBar';
import AppealModal from '@/components/appealModal/index.vue';
import MessageCenter from '@/views/allcase/components/message-center-dialog';
import QualityControlRecordDialog from '@/views/allcase/components/quality-control-record-dialog.vue';

export default {
  name: 'MedicalQuality',
  components: { QcCard, GenEditor, BottomBar, QcTab, AppealModal, MessageCenter, QualityControlRecordDialog },
  data() {
    return {
      resultsList: {},
      routeType: '',
      showLogout: false,
      isShowUser: false,
      staffInfo: {
        staff_code: '',
        staff_name: '',
        department: '',
      },
      // 顶部评分信息
      topHomeInfo: {
        score: 0,
        scoreLevel: '',
      },
      topRecordInfo: {
        score: 0,
        scoreLevel: '',
      },
      MEDRECID: '',
      activeDocType: '',
      // 病历数据
      recordData: {
        AAA28: '',
        CH: '',
        ZYCS: '',
        appeal_docter: '',
        appeal_document: '',
        data: [],
        is_case: '',
        quality_time: '',
        run_score: '',
        score: '',
        summary: {},
        total: '',
      },
      // Tab配置
      // tabs: [
      //   { key: 'home', label: '首页问题', badge: this.resultsList?.list.length || 0 },
      //   { key: 'record', label: '病历问题', badge: this.recordData?.data.length || 0 },
      //   { key: 'generate', label: '病历生成' },
      // ],
      tabs: [],
      activeTab: this.$route.path === '/whitelist-generate-case' ? 'generate' : 'record',
    };
  },

  mounted() {
    this.getQualityResult();
    this.getTableData();
    // 执行定时逻辑：6秒后第一次执行，之后每3秒一次，共4次
    this.startRefreshTimer();
    this.init();
  },

  computed: {
    staffTitle() {
      if (!this.staffInfo) return '未登录';
      const name = this.staffInfo.staff_name || '';
      const dept = this.staffInfo.department || '';
      return name && dept ? `${name}(${dept})` : name || '未登录';
    },
    showUserInfoBar() {
      return this.activeTab === 'generate' && this.$refs.genEditorRef?.pageFlag === '2';
    },
    showUserInfoBarSingle() {
      const info = localStorage.getItem('staffLoginInfo');
      return !info || info === '';
    },

    homeList() {
      const list = this.resultsList?.list || [];
      return list.map(item => ({
        id: item.id || '',
        level: item.level,
        is_artificial: item.is_artificial || 0,
        recordType: item.error_name || '首页数据',
        notice: item.desc || '暂无描述',
        type: item.type || 0,
        status: item.status || 0,
        score: item.down || '0.0',
        rule_type: item.rule_type || '其他',
        appeal_type: item.appeal_type || 0,
        appeal_status: item.appeal_status || 0,
        entity: item,
      }));
    },

    recordList() {
      let list = this.recordData?.data || [];
      if (this.activeDocType) {
        list = this.recordData.summary[this.activeDocType];
      }
      return list.map(item => ({
        id: item.id || '',
        level: item.level,
        is_artificial: item.is_artificial || 0,
        singleNo: item.one_no || 0,
        recordType: item.error_field || '病历内容',
        notice: item.notice || '暂无描述',
        type: item.type || 0,
        status: item.status || 0,
        evidenceList: item.basis || ['暂无质控依据'],
        score: item.score || '0.0',
        rule_type: item.rule_type || '其他',
        appeal_type: item.appeal_type || 0,
        appeal_status: item.appeal_status || 0,
        isKnow: item.isKnow || 0,
        entity: item,
      }));
    },
  },

  methods: {
    init() {
      const staffLoginInfo = localStorage.getItem('staffLoginInfo');
      if (staffLoginInfo != '' && staffLoginInfo != null) {
        this.staffInfo = JSON.parse(staffLoginInfo);
      }
      this.getRouteType();
      this.initData();
      this.quality_tab_menu();
    },
    startRefreshTimer() {
      let count = 0;
      const totalTimes = 5;
      setTimeout(() => {
        this.getQualityResult();
        this.getTableData();
        count++;

        if (count < totalTimes) {
          const intervalTimer = setInterval(() => {
            this.getQualityResult();
            this.getTableData();
            count++;

            if (count >= totalTimes) {
              clearInterval(intervalTimer);
              console.log('刷新完成，共执行4次');
            }
          }, 3000);
        }
      }, 6000);
    },

    loginSuccess(data) {
      this.staffInfo = data;
      this.isShowUser = true;
    },
    handleLogout() {
      this.showLogout = true;
    },

    // 确认退出
    logoutConfirm() {
      localStorage.setItem('staffLoginInfo', '');
      this.staffInfo.staff_code = '';
      this.staffInfo.staff_name = '';
      this.staffInfo.department = '';
      this.isShowUser = false;
      this.showLogout = false;
      this.$refs.genEditorRef.showLogin();
      this.$forceUpdate();
    },

    logoutCancel() {
      this.showLogout = false;
    },

    initData() {
      if (this.routeType === 'RUN-HOME') {
        this.activeTab = 'home';
        this.$refs.bottomBarRef.showTag('home');
      }

      if (this.routeType === 'RUN-RECORD') {
        this.activeTab = 'record';
        this.$refs.bottomBarRef.showTag('record');
      }
      // this.getTopInfo(this.activeTab);
    },
    typeChange(type) {
      this.activeDocType = type;
      let list = this.recordData.summary[type];
      // this.tabs = [
      //   { key: 'home', label: '首页问题', badge: this.homeList.length },
      //   { key: 'record', label: '病历问题', badge: list.length },
      //   { key: 'generate', label: '病历生成' },
      // ];
      this.tabs = this.tabs.map(item => {
        if (item.key === 'home') return { ...item, badge: this.homeList.length };
        if (item.key === 'record') return { ...item, badge: list.length };
        return item;
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
      this.getQualityResult();
      this.getTableData();
      // this.getAppealMessageNum();
    },

    quality_tab_menu() {
      this.$axios2
        .post('/quality_tab_menu', {
          id: this.$route.query.id,
        })
        .then(res => {
          if (res.code === 200) {
            console.log('quality_tab_menu', res);
            const tabLabels = res.data || [];

            // 👇 动态映射 key + badge
            this.tabs = tabLabels.map(label => {
              if (label === '首页问题') {
                return { key: 'home', label, badge: this.homeList.length || 0 };
              } else if (label === '病历问题') {
                return { key: 'record', label, badge: this.recordList.length || 0 };
              } else if (label === '病历生成') {
                return { key: 'generate', label };
              }
              return { key: label, label };
            });
          }
        })
        .catch(err => {
          console.error('获取数据失败', err);
        });
    },

    handleLeftClick(title) {
      switch (title) {
        case 'login':
          this.$router.push(`/login`);
          break;
        case 'message':
          // 消息的左击处理
          this.$refs.messageCenterRef.init(this.messageCount);
          break;
        case 'record':
          // 质控记录的左击处理
          this.$refs.qualityControlRecordDialogRef.init();

          break;
        case 'generate':
          this.$emit('genPage');
          break;
        case 'ai':
          this.$emit('genPage');
          break;
      }
    },

    getTopInfo(key) {
      try {
        let score = 0;
        let scoreLevel = '';
        let homeBadge = 0;
        let recordBadge = 0;

        // 首页评分逻辑
        if (key === 'home') {
          console.log('首页评分逻辑');
          score = this.resultsList?.score.score || 0;
          if (score > 90) scoreLevel = '优';
          else if (score >= 75) scoreLevel = '良';
          else scoreLevel = '中';
          this.topHomeInfo = { score, scoreLevel };
        }

        // 病历评分逻辑
        if (key === 'record') {
          console.log('病历评分逻辑');
          score = this.recordData?.score || 0;
          if (score > 90) scoreLevel = '甲级';
          else if (score >= 75) scoreLevel = '乙级';
          else scoreLevel = '丙级';
          this.topRecordInfo = { score, scoreLevel };
        }

        homeBadge = this.homeList.length;
        recordBadge = this.recordList.length;

        // this.tabs = [
        //   { key: 'home', label: '首页问题', badge: homeBadge },
        //   { key: 'record', label: '病历问题', badge: recordBadge },
        //   { key: 'generate', label: '病历生成' },
        // ];
        this.tabs = this.tabs.map(item => {
          if (item.key === 'home') {
            return { ...item, badge: homeBadge };
          } else if (item.key === 'record') {
            return { ...item, badge: recordBadge };
          }
          return item;
        });
      } catch (error) {
        console.error('获取顶部信息失败：', error);
      }
    },

    getRouteType() {
      const path = this.$route.path;
      if (path === '/whitelist-qualityResults') {
        this.routeType = 'RUN-HOME';
      } else if (path === '/whitelist-caseControl') {
        this.routeType = 'RUN-RECORD';
      }
    },

    handleTabChange(key) {
      console.log('handleTabChange:', key);
      this.activeTab = key;
      this.getTopInfo(key);
      this.activeDocType = '';

      if (key === 'home') {
        this.getQualityResult();
        this.$refs.bottomBarRef.showTag('home');
      }
      if (key === 'record') {
        this.getTableData();
        this.$refs.bottomBarRef.showTag('record');
      }
      if (key === 'generate') {
        this.$refs.bottomBarRef.showTag('generate');
        if (localStorage.getItem('staffLoginInfo') != '' && localStorage.getItem('staffLoginInfo') != null) {
          this.$refs.genEditorRef.setPageFlag();
        }
      }
    },

    getQualityResult() {
      const { id } = this.$route.query;
      if (!id) return;

      const params = {
        id,
        show_correction: this.$route.path === '/whitelist-qualityResults' ? 2 : 1,
        ...(this.$route.query.from === 'review' && { source: 'appeal' }),
      };

      this.$axios
        .post('/home_quality/getQualityResult', params, {
          headers: { isNoLoading: true },
        })
        .then(res => {
          this.resultsList = res.data || {};
          if (Array.isArray(this.resultsList.list)) {
            this.resultsList.list.forEach(item => (item.show = true));
          }
          this.getTopInfo('home');
        })
        .catch(e => console.error('获取质控结果失败：', e));
    },

    getTableData(isRender = false) {
      const { id } = this.$route.query;
      if (!id) return;

      this.$axios2
        .post(
          '/get_case_quality_v2',
          { id, show_correction: 2 },
          {
            headers: { isNoLoading: true },
          },
        )
        .then(res => {
          this.$nextTick(() => {
            const data = res.data || {};
            if (!isRender || this.recordData.is_case !== data.is_case) {
              this.recordData = data;
            }
            const ruleId = this.storageGet('getDataRule');
            if (Array.isArray(this.recordData.data) && ruleId) {
              this.recordData.data = [...this.recordData.data.filter(item => item.rule_id == ruleId), ...this.recordData.data.filter(item => item.rule_id != ruleId)];
            }
            this.getTopInfo('record');
          });
        })
        .catch(e => console.error('获取首页数据失败：', e));
    },

    storageGet(key) {
      return localStorage.getItem(key) || '';
    },

    handleToggle() {},
    genPage() {
      this.handleTabChange('generate');
    },
  },
};
</script>

<style scoped lang="scss">
.medical-quality-page {
  background: #f5f7fa;
  font-family: 'Microsoft YaHei', sans-serif;
  margin: 0 auto;
  overflow: hidden;
  height: 100%;
}
.page-container {
  width: 100%;
  height: 100%;
  display: flex;
  overflow: hidden;
  flex-direction: column;
}
.main-content {
  flex: 1;
  padding: 5px;
  overflow-y: auto;
  .nav-tabs {
    display: flex;
    align-items: center;
    justify-content: space-between;
  }
}
.panel-content {
  margin-top: 3px;
}

.page-title-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 40px;
  flex: 1;
  max-width: 200px;
  background: #dde8fe;
  color: #333;
  font-size: 14px;
  font-weight: 700;
  white-space: nowrap;
  border: 1px solid transparent;
  border-radius: 8px;
  transition: all 0.22s ease-in-out;
  cursor: pointer;

  &:hover {
    background: #f0f7ff;
  }

  /* 默认激活态 */
  &.active {
    background: #dde8fe;
    color: #2f6bff;
    border-color: #c3d2fb;
  }
}
.empty-tip {
  padding: 40px 0;
  text-align: center;
  color: #999;
  font-size: 14px;
}
.score-num {
  font-size: 14px;
  font-weight: bold;
  color: #f04438;
}
.unit {
  font-size: 14px;
  color: #7b8794;
  margin-left: 2px;
}
.grade-value {
  font-size: 13px;
  font-weight: bold;
  color: #d97706;
  background: #fff6e8;
  border: 1px solid #ffe0b2;
  padding: 2px 8px;
  border-radius: 13px;
  margin-top: 4px;
}

.confirm-modal {
  position: fixed;
  top: 0;
  left: 0;
  width: 100vw;
  height: 100vh;
  background: rgba(0, 0, 0, 0.4);
  display: none;
  align-items: center;
  justify-content: center;
  z-index: 9999;
}
.confirm-modal.show {
  display: flex;
}
.confirm-box {
  width: 320px;
  background: #fff;
  border-radius: 10px;
  padding: 24px;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
}
.confirm-title {
  font-size: 16px;
  font-weight: bold;
  margin-bottom: 12px;
  text-align: center;
}
.confirm-msg {
  font-size: 14px;
  color: #666;
  margin-bottom: 24px;
  text-align: center;
}
.confirm-btns {
  display: flex;
  justify-content: center;
  gap: 16px;
}
.confirm-btn {
  min-width: 80px;
  height: 32px;
  padding: 0 16px;
  border-radius: 16px;
  border: 1px solid transparent;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
}
.confirm-btn-cancel {
  background: #f5f5f5;
  color: #333;
}
.confirm-btn-ok {
  background: #f04438;
  color: #fff;
}
</style>