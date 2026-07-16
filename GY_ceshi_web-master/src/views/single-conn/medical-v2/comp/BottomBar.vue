<template>
  <div class="bottom-bar" id="bottomBar">
    <div class="bottom-top">
      <div class="bottom-text">
        <span class="bt-item">
          <span class="bt-label">病案号：</span>
          <span class="bt-value">{{ patientInfo.AAA28 }}</span>
        </span>
        <span class="bt-item">
          <span class="bt-label">床号：</span>
          <span class="bt-value">{{ patientInfo.CH }}</span>
        </span>
        <span class="bt-item">
          <span class="bt-label">姓名：</span>
          <span class="bt-value">{{ patientInfo.BRXM }}</span>
        </span>
      </div>
      <div class="collapse-up" id="bottomToggle" @click="toggleCollapse" :class="{ collapsed: isCollapsed }"></div>
    </div>

    <div class="bottom-extra" id="bottomExtra" v-show="!isCollapsed">
      <div class="stat-scroll-wrapper" v-if="isShowBtn">
        <div class="bottom-stats">
          <div class="stat-row">
            <div class="stat-item">
              <span class="stat-label">问题数量</span>
              <span class="stat-value">{{ totalCount }}</span>
            </div>
            <div class="stat-item">
              <span class="stat-label">必改问题</span>
              <span class="stat-value urgent">{{ mustCount }}</span>
            </div>
            <div class="stat-item">
              <span class="stat-label">建议问题</span>
              <span class="stat-value suggestion">{{ suggestCount }}</span>
            </div>
            <div class="stat-item">
              <span class="stat-label">质控状态</span>
              <span class="status-pill status-wait" v-if="dataEntity.is_case === 0">未质控</span>
              <span class="status-pill status-running" v-if="dataEntity.is_case === 1">质控中</span>
              <span class="status-pill status-done" v-if="dataEntity.is_case === 2">已质控</span>
            </div>
          </div>

          <div class="stat-time">
            <div class="doc-menu-btn" @click="toggleDocMenu">
              <span class="menu-btn-text">文书问题分布</span>
            </div>

            <span class="bchip">质控时间：{{ dataEntity.quality_time }}</span>
          </div>
        </div>
      </div>

      <!-- <div class="bottom-nav">
        <el-button v-for="item in menuList" :key="item.id" v-show="item && item.is_show === 1" size="small" class="nav-btn nav-login" @click="handleNavClick(item.title)">
          {{ item.title }}
        </el-button>
      </div> -->

      <div class="bottom-nav">
        <div class="nav-btn-list">
          <el-button v-for="item in menuList" :key="item.id" v-show="item && item.is_show === 1" size="small" class="nav-btn nav-login" @click="handleNavClick(item.title)">
            {{ item.title }}
          </el-button>
        </div>
      </div>
    </div>

    <div class="doc-menu" :class="{ show: showDocMenu }">
      <div class="doc-menu-header">文书类型</div>
      <div class="doc-menu-list">
        <div class="doc-item" @click="handleDocClick('ryjl')">
          <span class="doc-name">入院记录</span>
          <span class="doc-count">({{ dataEntity.summary.ryjl ? dataEntity.summary.ryjl.length : 0 }})</span>
        </div>
        <div class="doc-item" @click="handleDocClick('bcjl')">
          <span class="doc-name">病程记录</span>
          <span class="doc-count">({{ dataEntity.summary.bcjl ? dataEntity.summary.bcjl.length : 0 }})</span>
        </div>
        <div class="doc-item" @click="handleDocClick('ssjl')">
          <span class="doc-name">手术记录</span>
          <span class="doc-count">({{ dataEntity.summary.ssjl ? dataEntity.summary.ssjl.length : 0 }})</span>
        </div>
        <div class="doc-item" @click="handleDocClick('cyjl')">
          <span class="doc-name">出院记录</span>
          <span class="doc-count">({{ dataEntity.summary.cyjl ? dataEntity.summary.cyjl.length : 0 }})</span>
        </div>
        <div class="doc-item" @click="handleDocClick('tys')">
          <span class="doc-name">同意书</span>
          <span class="doc-count">({{ dataEntity.summary.tys ? dataEntity.summary.tys.length : 0 }})</span>
        </div>
        <div class="doc-item" @click="handleDocClick('qt')">
          <span class="doc-name">其他文书</span>
          <span class="doc-count">({{ dataEntity.summary.qt ? dataEntity.summary.qt.length : 0 }})</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { examineAppeal, getAppealData, getBrry } from '@/api/qc';

export default {
  name: 'BottomBar',
  props: {
    dataEntity: Object,
  },
  mounted() {
    this.getBaseInfo();
    this.get_menu();
  },
  computed: {
    navBtnClassMap() {
      return {
        record: 'nav-record',
        generate: 'nav-generate',
        ai: 'nav-ai',
        message: 'nav-message',
        login: 'nav-login',
      };
    },

    totalCount() {
      const summary = this.dataEntity?.summary;
      if (!summary) return 0;

      let list = [];
      if (this.currentDocType) {
        list = summary[this.currentDocType] || [];
      } else {
        Object.values(summary).forEach(arr => {
          list = list.concat(arr || []);
        });
      }
      return list.length;
    },

    mustCount() {
      const summary = this.dataEntity?.summary;
      if (!summary) return 0;

      let list = [];
      if (this.currentDocType) {
        list = summary[this.currentDocType] || [];
      } else {
        Object.values(summary).forEach(arr => {
          list = list.concat(arr || []);
        });
      }
      return list.filter(i => i.level != 2).length;
    },

    suggestCount() {
      const summary = this.dataEntity?.summary;
      if (!summary) return 0;

      let list = [];
      if (this.currentDocType) {
        list = summary[this.currentDocType] || [];
      } else {
        Object.values(summary).forEach(arr => {
          list = list.concat(arr || []);
        });
      }
      return list.filter(i => i.level == 2).length;
    },
  },
  watch: {
    isShowBtn() {
      this.get_menu();
    },
  },
  data() {
    return {
      showDocMenu: false,
      currentDocType: null,

      menuList: [],
      isShowBtn: true,
      isCollapsed: true,
      patientInfo: {},
      messageCount: 0,
      activeNav: '',
      navList: [
        { key: 'record', label: '质控记录', class: 'nav-record' },
        { key: 'generate', label: '病历生成', class: 'nav-generate' },
        { key: 'ai', label: 'AI提醒', class: 'nav-ai' },
        { key: 'message', label: '消息', class: 'nav-message' },
        { key: 'login', label: '登录', class: 'nav-login' },
      ],
    };
  },
  methods: {
    toggleCollapse() {
      this.isCollapsed = !this.isCollapsed;
      if (!this.isCollapsed) {
        this.currentDocType = null;
      }
    },
    handleTypeClick(type) {
      this.$emit('type-change', type);
    },
    handleNavClick(key) {
      this.activeNav = key;
      // this.$emit('nav-change', key);
      this.handleLeftClick(key);
    },

    get_menu() {
      this.$axios2.get('/tk/get_menu').then(res => {
        if (res.code === 200) {
          this.menuList = res.data;
          if (this.menuList.length > 0) {
            if (!this.isShowBtn) {
              this.menuList = this.menuList.filter(item => item.title != '质控记录');
            }
          }
        }
      });
    },

    toggleDocMenu() {
      this.showDocMenu = !this.showDocMenu;
      if (this.showDocMenu) {
        setTimeout(() => {
          document.addEventListener('click', this.closeDocMenu);
        }, 0);
      }
    },
    closeDocMenu(e) {
      const menu = document.querySelector('.doc-menu');
      const btn = document.querySelector('.doc-menu-btn');
      if (menu && !menu.contains(e.target) && !btn.contains(e.target)) {
        this.showDocMenu = false;
        document.removeEventListener('click', this.closeDocMenu);
      }
    },
    handleDocClick(type) {
      this.currentDocType = type;

      this.handleTypeClick(type);
      this.showDocMenu = false;
    },
    handleLeftClick(title) {
      switch (title) {
        case '登录':
          this.$router.push(`/login`);
          break;
        case '消息':
          // 消息的左击处理
          // this.$refs.messageCenterRef.init(this.messageCount);
          this.$emit('open-message-center');
          break;
        case '质控记录':
          // 质控记录的左击处理
          // this.$refs.qualityControlRecordDialogRef.init();
          this.$emit('open-quality-control');
          break;
        case '病历生成':
          this.$emit('genPage');
          break;
        case 'AI提醒':
          this.$emit('genPage');
          break;
      }
    },
    showTag(type) {
      this.isCollapsed = true;
      if (type == 'record') {
        this.isShowBtn = true;
      } else {
        this.isShowBtn = false;
      }
    },

    funGoto(val) {
      // this.params.id = val;
      this.$emit('refresh');
    },
    get_msg_count() {
      this.$axios2.get(`/tk/get_msg_count?zyh=${this.zyh}`).then(res => {
        this.messageCount = res.data.unread_count;
      });
    },
    getBaseInfo() {
      getBrry({ zyh: this.$route.query.id }).then(res => {
        if (res.code == 200) {
          this.patientInfo = res.data || {};
        }
      });
    },
  },
};
</script>

<style lang="scss" scoped>
$blue: #2f6bff;
$blue-soft: #edf4ff;
$blue-line: #cfe0ff;
$text-2: #4b5563;

.bottom-bar {
  background: #ffffff;
  border-top: 1px solid #e5eaf1;
  z-index: 10;
  flex-shrink: 0;

  .bottom-top {
    height: 44px;
    padding: 0 16px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    // border-bottom: 1px solid #edf2f7;

    .bottom-text {
      flex: 1;
      display: flex;
      align-items: center;
      justify-content: space-around;
      font-size: 14px;

      .bt-item {
        display: inline-flex;
        align-items: center;
        white-space: nowrap;
      }
      .bt-label {
        color: #000;
        font-weight: bold;
      }
      .bt-value {
        color: #333;
        font-weight: normal;
        margin-left: 4px;
      }
    }

    .collapse-up {
      width: 24px;
      height: 24px;
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='24' height='24' viewBox='0 0 24 24' fill='none' stroke='%234b5563' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='18 15 12 9 6 15'/%3E%3C/svg%3E");
      background-repeat: no-repeat;
      background-position: center;
      cursor: pointer;
      transition: transform 0.2s ease;

      &.collapsed {
        transform: rotate(180deg);
      }
    }
  }

  .bottom-extra {
    padding: 12px 16px;

    .stat-scroll-wrapper {
      width: 100%;
      overflow-x: auto;
      margin-bottom: 10px;

      .bottom-stats {
        background: #f8fafc;
        border-radius: 8px;
        padding: 12px;
        border: 1px solid #e2e8f0;

        .stat-row {
          display: flex;
          align-items: center;
          justify-content: space-between;
          gap: 16px;
          margin-bottom: 8px;

          .stat-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            flex: 1;

            .stat-label {
              font-size: 10px;
              color: #94a3b8;
              margin-bottom: 4px;
              font-weight: 700;
            }

            .stat-value {
              font-size: 16px;
              font-weight: 700;
              color: #1e293b;
            }

            .urgent {
              color: #f04438;
            }
            .suggestion {
              color: #2f6bff;
            }

            .status-pill {
              font-size: 14px;
              padding: 3px 10px;
              border-radius: 12px;
              font-weight: normal;

              &.status-wait {
                background: #f3f4f6;
                color: #6b7280;
              }
              &.status-running {
                background: #edf4ff;
                color: #2f6bff;
              }
              &.status-done {
                background: #e6f7f0;
                color: #069460;
              }
            }
          }
        }

        .stat-time {
          display: flex;
          align-items: center;
          justify-content: space-between;
          gap: 1px;
          font-size: 10px;
          color: #94a3b8;
          padding-top: 8px;
          border-top: 1px solid #e2e8f0;

          .doc-menu-btn {
            background: #f5f9ff;
            border: 1px solid #cfe0ff;
            color: #2f6bff;
            padding: 4px 12px;
            border-radius: 16px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            white-space: nowrap;

            &:hover {
              background: #2f6bff;
              color: #ffffff;
              border-color: #2f6bff;
              transform: translateY(-1px);
            }
          }
        }
      }

      &::-webkit-scrollbar {
        display: none;
      }
      -ms-overflow-style: none;
      scrollbar-width: none;

      .stat-list {
        display: flex;
        align-items: center;
        justify-content: space-between;
        min-width: 100%;
      }
    }

    .bottom-line {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 10px;

      .line-group {
        display: flex;
        gap: 12px;
      }
    }

    .bottom-nav {
      display: flex;
      // align-items: center;
      justify-content: flex-start; /* 左对齐 */
      gap: 6px;
      margin-top: 10px;

      .nav-btn {
        width: 100px; /* 固定按钮宽度 */
        flex: none !important;
        flex: 1 1 0;
        height: 32px;
        padding: 0 10px;
        border-radius: 16px;
        border: 1px solid #e6edf5;
        background: #ffffff;
        color: #4b5563;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        transition: all 0.2s ease;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
      }

      .nav-btn:hover {
        border-color: var(--blue-line);
        background: var(--blue-soft);
        color: var(--blue);
        transform: translateY(-1px);
        box-shadow: 0 2px 4px rgba(47, 107, 255, 0.1);
      }

      .nav-btn.active {
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        transform: translateY(-2px);
      }

      .nav-btn.active:hover {
        transform: translateY(-3px);
        box-shadow: 0 6px 12px rgba(0, 0, 0, 0.25);
      }

      .nav-login {
        background: linear-gradient(135deg, #2f6bff, #1e56cc);
        border-color: #2f6bff;
        color: #ffffff;
      }

      .nav-login:hover {
        background: linear-gradient(135deg, #1e56cc, #1442a0);
        border-color: #1e56cc;
        transform: translateY(-1px);
      }

      .nav-message {
        background: linear-gradient(135deg, #6f42ef, #5b34c1);
        border-color: #6f42ef;
        color: #ffffff;
      }

      .nav-message:hover {
        background: linear-gradient(135deg, #5b34c1, #4a2a99);
        border-color: #5b34c1;
        transform: translateY(-1px);
      }

      .nav-record {
        background: linear-gradient(135deg, #f59e0b, #d97706);
        border-color: #f59e0b;
        color: #ffffff;
      }

      .nav-record:hover {
        background: linear-gradient(135deg, #d97706, #b45309);
        border-color: #d97706;
        transform: translateY(-1px);
      }

      .nav-generate {
        background: linear-gradient(135deg, #10b981, #059669);
        border-color: #10b981;
        color: #ffffff;
      }

      .nav-generate:hover {
        background: linear-gradient(135deg, #059669, #047857);
        border-color: #059669;
        transform: translateY(-1px);
      }

      .nav-ai {
        background: linear-gradient(135deg, #f04438, #dc2626);
        border-color: #f04438;
        color: #ffffff;
      }

      .nav-ai:hover {
        background: linear-gradient(135deg, #dc2626, #b91c1c);
        border-color: #dc2626;
        transform: translateY(-1px);
      }
    }
  }
  .doc-menu {
    position: absolute;
    left: 16px;
    bottom: 70px;
    width: 180px;
    background: rgba(33, 40, 48, 0.96);
    backdrop-filter: blur(2px);
    border: 1px solid rgba(255, 255, 255, 0.06);
    border-radius: 12px;
    color: #e5e7eb;
    box-shadow: 0 10px 24px rgba(0, 0, 0, 0.18);
    z-index: 9999;
    overflow: hidden;
    display: none;

    &.show {
      display: block;
    }

    .doc-menu-header {
      padding: 10px 12px;
      font-size: 12px;
      color: #cbd5e1;
      border-bottom: 1px solid rgba(255, 255, 255, 0.06);
    }

    .doc-menu-list {
      display: flex;
      flex-direction: column;
      padding: 6px 0;
      max-height: 240px;
      overflow-y: auto;
    }

    .doc-item {
      height: 34px;
      padding: 0 12px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      font-size: 13px;
      cursor: pointer;

      &:hover {
        background: rgba(59, 130, 246, 0.18);
      }
    }
  }

  .bchip {
    font-size: 14px;
    color: #333333;
    background: transparent;
    padding: 2px 10px;
    font-weight: normal;
    white-space: nowrap;
    border: 1px solid #e0f0ff;
    border-radius: 4px;
    margin: 0 4px;
  }
}
</style>