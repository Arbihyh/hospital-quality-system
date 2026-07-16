<template>
  <div class="message-center-container">
    <!-- 标题栏 -->
    <div class="dialog-header">
      <div class="header-content">
        <div class="dialog-title">消息中心({{ msgNum1 }})</div>
        <div class="clear-btn" @click="clearMsg">
          <svg t="1756646537142" class="icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="6705" width="16" height="16">
            <path d="M954.2 408.6L636 90.4c-8.8-8.8-23-8.8-31.8 0L158.8 535.9c-8.7 8.7-8.8 22.8-0.2 31.6l203.9 209.2c4.2 4.3 10 6.8 16.1 6.8H602c6 0 11.7-2.4 15.9-6.6l336.4-336.4c8.7-8.8 8.7-23.1-0.1-31.9z m-47.7 15.9l-314 314H388L206.3 552l413.8-413.8 286.4 286.3z" fill="#515151" p-id="6706"></path>
            <path d="M827 504.1l-31.9 31.8-286.3-286.4 31.8-31.8zM939 848.2v45H129v-45z" fill="#515151" p-id="6707"></path>
          </svg>
          <span>清除消息</span>
        </div>
      </div>
    </div>

    <!-- 筛选按钮区域 -->
    <div class="filter-container">
      <div class="filter-buttons-container">
        <div :class="['filter-btn', 'all-btn', { 'active': filterStatus === -1 }]" @click="handleFilterChange(-1)">
          全部
        </div>
        <div :class="['filter-btn', 'unread-btn', { 'active': filterStatus === 0 }]" @click="handleFilterChange(0)">
          未读
          <span v-if="msgNum > 0" class="unread-badge">{{ msgNum }}</span>
        </div>
        <div :class="['filter-btn', 'read-btn', { 'active': filterStatus === 1 }]" @click="handleFilterChange(1)">
          已读
        </div>
      </div>
    </div>

    <!-- 消息列表区域 -->
    <div class="message-list" @scroll="handleScroll" ref="messageListRef">
      <div v-if="loading" class="loading-message">
        <span>加载中...</span>
      </div>

      <div v-else-if="messageList.length === 0" class="no-message">
        暂无消息
      </div>

      <div v-else>
        <medicalRecordReminder 
          v-for="(message, index) in messageList" 
          :key="message.id || index" 
          :titleType="titleType" 
          :message="message" 
          @markAsRead="handleMarkAsRead(message.id)" 
        />
      </div>

      <!-- 加载更多提示 -->
      <div v-if="loadingMore && messageList.length > 0" class="loading-more">
        <el-loading-spinner size="small"></el-loading-spinner>
        <span>加载更多...</span>
      </div>

      <!-- 没有更多数据提示 -->
      <div v-if="!hasMore && messageList.length > 0" class="no-more">
        没有更多消息了
      </div>
    </div>
  </div>
</template>

<script>
import medicalRecordReminder from '@/components/medical-record-reminder/index.vue'
import { getMessageList, clearAllMessages, markMessageAsRead } from '@/api/message'

export default {
  name: 'MessageCenterSingle',
  components: {
    medicalRecordReminder
  },
  props: {
    baseInfo: {
      type: Object,
      default: () => ({})
    },
    unreadCount: {
      type: Number,
      default: 0
    },
    titleType: {
      type: String,
      default: '病历时效性提醒'
    }
  },
  data() {
    return {
      filterStatus: -1,
      msgNum: 0,
      msgNum1: 0,
      messageList: [], // 消息列表数据
      loading: false, // 初始加载状态
      loadingMore: false, // 加载更多状态
      hasMore: true, // 是否还有更多数据
      zyh:  this.$route.query.zyh ,
      // 分页参数
      pageParams: {
        page: 1,
        page_size: 10,
        is_read: null
      },
      // 总页数
      totalPages: 0
    }
  },
  mounted() {
    this.get_msg_count()
    this.resetPageParams()
    this.loadMessageList()
  },
  methods: {
    resetPageParams() {
      this.pageParams = {
        page: 1,
        page_size: 3,
        is_read: this.filterStatus
      };
      this.messageList = [];
      this.totalPages = 0;
      this.hasMore = true;
    },

    // 加载消息列表
    async loadMessageList() {
      // 如果是第一页，显示加载状态
      if (this.pageParams.page === 1) {
        this.loading = true;
      } else {
        this.loadingMore = true;
      }

      try {
        this.pageParams.zyh = this.$route.query.zyh;
        const response = await getMessageList(this.pageParams);
        const { data } = response;
        this.totalPages = Math.ceil(data.total / this.pageParams.page_size);

        // 如果是第一页，直接替换数据；否则追加数据
        if (this.pageParams.page === 1) {
          this.messageList = data.list;
        } else {
          this.messageList = [...this.messageList, ...data.list];
        }

        this.msgNum1 = data.total;
        // 判断是否还有更多数据
        this.hasMore = this.pageParams.page < this.totalPages;
      } catch (error) {
        console.error('加载消息列表失败:', error);
        // this.$message.error('加载消息失败，请重试');
      } finally {
        // 关闭加载状态
        this.loading = false;
        this.loadingMore = false;
      }
    },

    // 处理滚动事件，实现滚动到底部加载更多
    handleScroll(e) {
      const element = e.target;
      if (element.scrollHeight - element.scrollTop <= element.clientHeight + 20) {
        if (!this.hasMore || this.loading || this.loadingMore) {
          return;
        }

        // 检查当前页是否小于总页数
        if (this.pageParams.page >= this.totalPages) {
          this.hasMore = false;
          return;
        }

        // 增加页码并加载更多数据
        this.pageParams.page++;
        this.loadMessageList();
      }
    },

    get_msg_count() {
      this.$axios2.get(`/tk/get_msg_count?zyh=${this.zyh}`).then(res => {
        this.msgNum = res.data.unread_count;
        this.msgNum1 = res.data.read_count
      });
    },

    // 处理筛选条件变化
    handleFilterChange(status) {
      this.filterStatus = status;
      this.pageParams.is_read = status === -1 ? null : status;
      // 重置分页并重新加载数据
      this.pageParams.page = 1;
      this.loadMessageList();
    },

    // 标记消息为已读
    async handleMarkAsRead() {
      try {
        this.loadMessageList()
      } catch (error) {
        console.error('标记消息为已读失败:', error);
        // this.$message.error('更新消息状态失败，请重试');
      }
    },

    // 清除消息方法
    clearMsg() {
      this.$confirm('确定要清除所有消息吗?', '提示', {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
      }).then(async () => {
        try {
          const params = {
            zyh:  this.$route.query.zyh 
          }
          await clearAllMessages(params);
          this.$message.success('消息已清除');
          this.messageList = [];
          this.msgNum = 0;
          this.$emit('clear-message');
        } catch (error) {
          console.error('清除消息失败:', error);
          // this.$message.error('清除消息失败，请重试');
        }
      }).catch(() => {
        console.log('已取消清除');
      });
    }
  }
}
</script>

<style lang="scss" scoped>
// 整体容器样式
.message-center-container {
  width: 100%;
  background: #fff; 
}

.dialog-header {
  padding: 15px 20px;
  border-bottom: 1px solid #eee;

  .header-content {
    display: flex;
    align-items: center;
    justify-content: space-between; 
    gap: 20px;
  }

  .dialog-title {
    color: #0a0101;
    font-size: 20px;
    font-family: PingFangSC-bold, sans-serif;
  }

  .clear-btn {
    padding: 5px 0;
    line-height: 24px;
    color: rgba(16, 34, 71, 1);
    font-size: 12px;
    text-align: left;
    font-family: PingFangSC-regular, sans-serif;
    cursor: pointer;
    display: inline-flex;
    align-items: center;

    .icon {
      margin-right: 4px;
      vertical-align: middle;
    }

    &:hover {
      color: #409eff;
      background-color: transparent;
    }
  }
}

.filter-container {
  padding: 10px 20px;
  border-bottom: 1px solid #f0f0f0;

  .filter-buttons-container {
    display: flex;
    justify-content: center; 
    width: 100%;
    margin: 0 auto;
  }
}

.filter-btn {
  width: 180px;
  height: 36px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 2px;
  font-size: 14px;
  font-family: SourceHanSansSC-bold, sans-serif;
  color: rgba(76, 93, 117, 1);
  margin: 0 8px;
  cursor: pointer;
  user-select: none;
  transition: all 0.2s ease;
  position: relative;
}

.all-btn {
  background-color: rgba(240, 242, 245, 1);
}

.unread-btn {
  background-color: rgba(222, 233, 245, 1);
  padding-right: 18px;
}

.read-btn {
  background-color: rgba(240, 242, 245, 1);
}

.unread-badge {
  position: absolute;
  top: -2px;
  right: 20px; 
  background-color: #ff4d4f;
  color: white;
  border-radius: 50%;
  min-width: 18px;
  height: 18px;
  font-size: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0 4px;
}

.filter-btn.active {
  font-weight: bold;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  background-color: rgba(191, 219, 254, 1);
}

.filter-btn:hover:not(.active) {
  opacity: 0.9;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
}

.message-list {
  height: 500px; 
  overflow-y: auto;
  padding: 20px;
}

.no-message {
  text-align: center;
  padding: 50px 0;
  color: #999;
  font-size: 14px;
}

.loading-message {
  text-align: center;
  padding: 50px 0;
  color: #666;
  font-size: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-direction: column;

  ::v-deep .el-loading-spinner {
    margin-bottom: 10px;
  }
}

.loading-more {
  text-align: center;
  padding: 15px 0;
  color: #666;
  font-size: 12px;
  display: flex;
  align-items: center;
  justify-content: center;

  ::v-deep .el-loading-spinner {
    margin-right: 8px;
  }
}

.no-more {
  text-align: center;
  padding: 15px 0;
  color: #999;
  font-size: 12px;
}
</style>