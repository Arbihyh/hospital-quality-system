<template>
  <div class="reminder-container" :class="{ 'read-status': is_read === 0 }">
    <div class="first-row">
      <div class="left-content">
        <svg v-if="is_read === 0" t="1756654734803" class="reminder-icon" viewBox="0 0 1024 1024" version="1.1"
          xmlns="http://www.w3.org/2000/svg" p-id="13727" width="22" height="22">
          <path
            d="M798.9 71.5H225.3c-70.6 0-128 57.4-128 128V896c0 70.6 57.4 128 128 128h573.6c70.6 0 128-57.4 128-128V199.5c0.1-70.6-57.4-128-128-128zM585.3 781.2H268.6V730h316.7v51.2z m163.9-207.5H268.6v-51.2h480.6v51.2z m0-203.1H268.6v-51.2h480.6v51.2z"
            fill="#2868F0" p-id="13728"></path>
          <path d="M317.3 0.1h51.2v156h-51.2zM657.8 0.1H709v156h-51.2z" fill="#043EB8" p-id="13729"></path>
        </svg>
        <svg v-else t="1756654734803" class="reminder-icon" viewBox="0 0 1024 1024" version="1.1"
          xmlns="http://www.w3.org/2000/svg" p-id="13727" width="22" height="22">
          <path
            d="M798.9 71.5H225.3c-70.6 0-128 57.4-128 128V896c0 70.6 57.4 128 128 128h573.6c70.6 0 128-57.4 128-128V199.5c0.1-70.6-57.4-128-128-128zM585.3 781.2H268.6V730h316.7v51.2z m163.9-207.5H268.6v-51.2h480.6v51.2z m0-203.1H268.6v-51.2h480.6v51.2z"
            fill="#dbdbdb" p-id="13728"></path>
          <path d="M317.3 0.1h51.2v156h-51.2zM657.8 0.1H709v156h-51.2z" fill="#dbdbdb" p-id="13729"></path>
        </svg>
        <span class="reminder-title">{{ titleType }}</span>
      </div>
      <span class="reminder-time">{{ message.create_time }}</span>
    </div>

    <!-- 第二行：添加医师问候语和描述信息 -->
    <div class="second-row-container">
      <!-- <span class="greeting">尊敬的【<strong>提醒</strong>{{ doctorName }} 】医师：</span> -->
      <div class="description-content" v-html="formattedText"></div>
    </div>

    <div class="third-row">
      <button v-if="is_read === 1" class="btn-know" @click="handleConfirm">知道了</button>
      <button class="btn-view" @click="$emit('view')">去查看</button>
    </div>

    <div class="dashed-line"></div>
  </div>
</template>
<script>
import { getMessageList, clearAllMessages, markMessageAsRead } from '@/api/message'
export default {
  name: 'MedicalRecordReminder',
  props: {
    titleType: {
      type: String,
      required: true,
      default: '病历时效提醒'
    },
    message: {
      type: Object,
      default: () => ({})
    },
  },
  computed: {
    formattedText() {
      // 将换行符替换为<br>，同时可处理其他特殊字符
      return this.message.msg
        .replace(/\n/g, '<br>')
        .replace(/\t/g, '&nbsp;&nbsp;&nbsp;&nbsp;'); // 处理制表符
    }
  },
  data() {
    return {
      is_read: 0, // 记录是否已读状态
      loading: false // 加载状态
    }
  },
  methods: {
    async handleConfirm() {
      // 防止重复点击
      if (this.loading) return;

      this.loading = true;
      try {

        markMessageAsRead({ id: this.message.id }).then(() => {
          this.$emit('marked-as-read');
        });

      } catch (error) {
        console.error('标记已读失败:', error);
        // 可添加错误提示
      } finally {
        this.loading = false;
      }
    }
  }
}
</script>

<style scoped>
.reminder-container {
  position: relative;
  padding: 12px 44px;
  background-color: #fff;
  width: 100%;
  box-sizing: border-box;
}

/* 已读状态样式 - 所有文字变为#858F9B */
.reminder-container.read-status {

  .reminder-title,
  .reminder-time,
  .greeting,
  .description-content {
    color: #858F9B !important;
  }
}

/* 第一行样式 */
.first-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  width: 100%;
  height: 26px;
  margin-bottom: 10px;
}

.left-content {
  display: flex;
  align-items: center;
}

.reminder-icon {
  width: 22px;
  height: 22px;
  margin-right: 13px;
}

.reminder-title {
  height: 26px;
  line-height: 28px;
  color: rgba(16, 38, 71, 1);
  font-size: 14px;
  text-align: left;
  font-family: PingFangSC-bold, sans-serif;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.reminder-time {
  height: 26px;
  line-height: 28px;
  color: rgba(135, 144, 163, 1);
  font-size: 12px;
  text-align: right;
  font-family: PingFangSC-regular, sans-serif;
  white-space: nowrap;
}

/* 第二行样式 */
.second-row-container {
  width: 100%;
  margin-bottom: 15px;
  box-sizing: border-box;
}

.greeting {
  display: inline-block;
  width: 100%;
  line-height: 28px;
  color: rgba(16, 38, 71, 1);
  font-size: 14px;
  text-align: left;
  font-family: PingFangSC-regular, sans-serif;
  margin-bottom: 8px;
}

.greeting strong {
  font-weight: bold;
}

.description-content {
  width: 100%;
  min-height: 10px;
  line-height: 28px;
  color: rgba(16, 38, 71, 1);
  font-size: 14px;
  text-align: left;
  font-family: PingFangSC-regular, sans-serif;
  padding-left: 28px;
  box-sizing: border-box;
}

/* 第三行按钮区域 */
.third-row {
  display: flex;
  justify-content: flex-end;
  gap: 11px;
  height: 30px;
  /* margin-bottom: 15px; */
  width: 100%;
}

.btn-know {
  width: 82px;
  height: 30px;
  line-height: 32px;
  border-radius: 2px;
  background-color: rgba(255, 255, 255, 1);
  color: rgba(0, 0, 0, 1);
  font-size: 12px;
  text-align: center;
  font-family: SourceHanSansSC-regular, sans-serif;
  border: 1px solid rgba(207, 211, 218, 1);
  cursor: pointer;
  padding: 0;
  transition: all 0.2s ease;
}

.btn-know:hover {
  background-color: #f5f5f5;
}

.btn-know:disabled {
  cursor: not-allowed;
  opacity: 0.7;
}

.btn-view {
  width: 82px;
  height: 30px;
  line-height: 30px;
  border-radius: 2px;
  background-color: rgba(32, 108, 207, 1);
  color: rgba(255, 255, 255, 1);
  font-size: 12px;
  text-align: center;
  font-family: SourceHanSansSC-regular, sans-serif;
  border: none;
  cursor: pointer;
  padding: 0;
  transition: all 0.2s ease;
}

.btn-view:hover {
  background-color: #1e7bcc;
}

/* 底部虚线 */
.dashed-line {
  width: 100%;
  height: 1px;
  background: linear-gradient(to right, transparent, #ddd, transparent);
  margin-top: 8px;
}

/* 字体回退设置 */
@font-face {
  font-family: 'PingFangSC-bold';
  src: local('PingFang SC Bold'), local('Microsoft YaHei Bold');
}

@font-face {
  font-family: 'PingFangSC-regular';
  src: local('PingFang SC Regular'), local('Microsoft YaHei');
}

@font-face {
  font-family: 'SourceHanSansSC-regular';
  src: local('Source Han Sans SC Regular'), local('Microsoft YaHei');
}
</style>