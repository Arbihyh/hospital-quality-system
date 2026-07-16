<!-- 全屏展示 -->
<template>
  <div class="max-container" :class="{ maximized: isMaximized }" ref="maxContainerRef" :style="customStyle">
    <!-- 最大化/退出按钮 -->
    <button class="max-toggle-btn" @click="handleToggleMax" :style="btnStyle" v-if="showBtn">
      {{ isMaximized ? closeText : openText }}
    </button>

    <div class="max-content">
      <slot></slot>
    </div>

    <!-- 全屏遮罩层 -->
    <div class="max-mask" v-if="isMaximized && showMask"></div>

    <!-- 退出提示框 -->
    <div class="esc-tip" v-if="isMaximized && isShowEscTip">按 ESC 键 退出全屏</div>
  </div>
</template>

<script>
export default {
  name: 'FullscreenContainer',
  props: {
    customStyle: {
      // 自定义样式
      type: Object,
      default: () => ({}),
    },
    btnStyle: {
      // 按钮样式
      type: Object,
      default: () => ({}),
    },
    openText: {
      // 按钮文字
      type: String,
      default: '全屏显示',
    },
    closeText: {
      // 按钮文字
      type: String,
      default: '退出全屏',
    },
    showBtn: {
      type: Boolean,
      default: true,
    },
    showMask: {
      // 是否显示遮罩层
      type: Boolean,
      default: false,
    },
    escClose: {
      // 按 ESC 退出全屏
      type: Boolean,
      default: true,
    },
    initMax: {
      type: Boolean,
      default: false,
    },
    showEscTip: {
      type: Boolean,
      default: false,
    },
    escTipDuration: {
      // 提示框显示时长 0 表示一直显示
      type: Number,
      default: 2000,
    },
  },
  data() {
    return {
      isMaximized: false,
      originStyle: {},
      escTipTimer: null,
      isShowEscTip: this.showEscTip,
    };
  },
  watch: {
    initMax: {
      immediate: true,
      handler(val) {
        this.isMaximized = val;
        if (val) {
          this.setMaxStyle();
          this.openEscTip();
        }
      },
    },
    isMaximized(newVal) {
      if (newVal) {
        this.openEscTip();
      } else {
        this.clearEscTipTimer();
      }
    },
    showEscTip(newVal) {
      this.isShowEscTip = newVal;
    },
  },
  mounted() {
    if (this.escClose) {
      document.addEventListener('keydown', this.handleEscKey);
    }
  },
  beforeDestroy() {
    if (this.escClose) {
      document.removeEventListener('keydown', this.handleEscKey);
    }
    this.clearEscTipTimer();
  },
  methods: {
    handleToggleMax() {
      this.isMaximized = !this.isMaximized;

      if (this.isMaximized) {
        this.setMaxStyle();
      } else {
        this.resetOriginStyle();
      }

      this.$emit('change', this.isMaximized);
    },

    setMaxStyle() {
      const el = this.$refs.maxContainerRef;
      if (!el) return;

      this.originStyle = {
        position: el.style.position,
        top: el.style.top,
        left: el.style.left,
        width: el.style.width,
        height: el.style.height,
        zIndex: el.style.zIndex,
        margin: el.style.margin,
        borderRadius: el.style.borderRadius,
      };

      el.style.position = 'fixed';
      el.style.top = '0';
      el.style.left = '0';
      el.style.width = '100vw';
      el.style.height = '100vh';
      el.style.zIndex = '9999';
      el.style.margin = '0';
      el.style.borderRadius = '0';
    },

    openEscTip() {
      this.clearEscTipTimer();
      if (this.showEscTip) {
        this.isShowEscTip = true;
        if (this.escTipDuration > 0) {
          this.escTipTimer = setTimeout(() => {
            this.isShowEscTip = false;
          }, this.escTipDuration);
        }
      }
    },
    clearEscTipTimer() {
      if (this.escTipTimer) clearTimeout(this.escTipTimer);
    },

    resetOriginStyle() {
      const el = this.$refs.maxContainerRef;
      if (!el) return;

      Object.keys(this.originStyle).forEach(key => {
        el.style[key] = this.originStyle[key] || '';
      });
    },

    handleEscKey(e) {
      if (e.key === 'Escape' && this.isMaximized) {
        this.handleToggleMax();
      }
    },

    openMax() {
      if (!this.isMaximized) {
        this.handleToggleMax();
      }
    },

    closeMax() {
      if (this.isMaximized) {
        this.handleToggleMax();
      }
    },
  },
};
</script>

<style lang="scss" scoped>
.max-container {
  position: relative;
  border-radius: 4px;
  background: #f0f2f5;
  transition: all 0.3s ease;
  box-sizing: border-box;

  .max-toggle-btn {
    position: absolute;
    top: 10px;
    right: 10px;
    padding: 6px 12px;
    background: #409eff;
    color: #fff;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    z-index: 10;
    outline: none;
  }

  .max-toggle-btn:hover {
    background: #66b1ff;
  }

  .max-content {
    overflow: auto;
  }

  .maximized {
    overflow: auto;
    box-shadow: 0 0 15px rgba(0, 0, 0, 0.2);
  }

  .max-mask {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    background: rgba(0, 0, 0, 0.5);
    z-index: 9998;
  }

  .esc-tip {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    padding: 4px 12px;
    background: rgba(0, 0, 0, 0.7);
    color: #ffffff;
    font-size: 16px;
    border-radius: 4px;
    z-index: 11;
    user-select: none;
    pointer-events: none;
    transition: opacity 0.3s ease;
    opacity: 1;
  }
}
</style>