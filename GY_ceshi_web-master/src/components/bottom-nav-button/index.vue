<template>
  <!-- 仅在show为true时显示按钮 -->
  <div v-if="show" class="bottom-nav-button cursor-pointer" @click="$emit('click', link)"
    @contextmenu.prevent="$emit('right-click', link)" :style="{ width: buttonWidth, height: buttonHeight }">
    <div class="inner-container">
      <!-- 图片部分 - 支持FontAwesomeAwesome图标或自定义图片 -->
      <div class="icon-container">
        <div v-if="useIcon" class="icon-wrapper">
          <i :class="['fa', iconClass, iconSizeClass]"></i>
        </div>
        <div v-else class="icon-wrapper">
          <img :src="imageSrc" alt="" class="nav-image" :style="{ width: iconSize, height: iconSize }">
        </div>
        
        <!-- 消息数量提示 - 仅消息类型按钮显示 -->
        <div v-if="isMessageType && messageCount > 0" class="badge">
          <span class="badge-count">{{ formatMessageCount(messageCount) }}</span>
        </div>
      </div>

      <!-- 文字描述部分 -->
      <span class="text-wrapper text-xs" :style="{ color: textColor }">{{ name }}</span>
    </div>
  </div>
</template>

<script>
export default {
  name: 'BottomNavButton',
  props: {
    // 控制按钮是否显示
    show: {
      type: Boolean,
      default: true
    },
    // 按钮名称
    name: {
      type: String,
      required: true,
      default: '按钮'
    },
    // 按钮类型，用于判断是否显示消息提示
    type: {
      type: String,
      default: '' // 当值为'message'时显示消息提示
    },
    // 消息数量
    messageCount: {
      type: Number,
      default: 0
    },
    // 按钮对应的链接
    link: {
      type: String,
      default: '#'
    },
    // 图片路径 - 优先使用图标
    imageSrc: {
      type: String,
      default: ''
    },
    // 是否使用FontAwesome图标
    useIcon: {
      type: Boolean,
      default: false
    },
    // 图标类名 (当useIcon为true时有效)
    iconClass: {
      type: String,
      default: 'el-icon-s-home'
    },
    // 图标大小
    iconSize: {
      type: String,
      default: '24px'
    },
    // 文字颜色
    textColor: {
      type: String,
      default: '#333333'
    },
    // 按钮宽度
    buttonWidth: {
      type: String,
      default: '80px'
    },
    // 按钮高度
    buttonHeight: {
      type: String,
      default: '60px'
    }
  },
  computed: {
    // 根据图标大小生成对应的FontAwesome类名
    iconSizeClass() {
      const size = parseInt(this.iconSize)
      if (size >= 32) return 'fa-lg'
      if (size >= 24) return 'fa-md'
      return ''
    },
    // 判断是否为消息类型按钮
    isMessageType() {
      return this.name === '消息'
    }
  },
  methods: {
    // 格式化消息数量，超过99显示99+
    formatMessageCount(count) {
      if (count > 99) {
        return '9+'
      }
      return count
    }
  }
}
</script>

<style scoped>
.bottom-nav-button {
  transition: all 0.2s;
  position: relative;
}

.inner-container {
  width: 100%;
  height: 100%;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 2px;
}

.icon-container {
  position: relative;
}

.icon-wrapper {
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 1px;
}

.nav-image {
  object-fit: contain;
}

.text-wrapper {
  text-align: center;
  white-space: nowrap;
}

.bottom-nav-button:hover {
  background-color: #f3f4f6;
}

.bottom-nav-button:active {
  background-color: #e5e7eb;
}

.active {
  color: #42b983;
}

/* 禁用状态样式 */
.bottom-nav-button[disabled] {
  opacity: 0.5;
  cursor: not-allowed;
  pointer-events: all !important;
}

/* 消息数量提示样式 */
.badge {
  position: absolute;
  top: -14px; /* 向上多移动3px */
  right: -14px; /* 向右多移动3px */
  background-color: #ff4d4f;
  color: white;
  border-radius: 50%;
  min-width: 18px;
  height: 18px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  padding: 0 4px;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
  /* 新增：确保徽章显示在最上层 */
  z-index: 10;
}

/* 如果图标本身比较大，可以适当增加偏移量 */
/* 可选：针对大图标单独调整 */
.icon-wrapper:nth-child(1) ~ .badge {
  top: -12px;
  right: -14px;
}
</style>
