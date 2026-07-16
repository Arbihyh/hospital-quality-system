<template>
  <div class="table-container">
    <!-- 上方分页 - 左上角 -->
    <div class="top-pagination">
      <div class="btn-box">
        <span class="page-msg">
          共找到
          <span class="num">{{ paginationData.total }}</span>
          条结果

          <span class="page">
            <span @click="jumpPage('prev')" :class="{ disabled: paginationData.currentPage <= 1 }">
              <i class="el-icon-arrow-left"></i>
            </span>
            <!-- 为当前页码添加了current-page类 -->
            <span class="current-page">{{ paginationData.currentPage }}</span>
            <span class="page-divider">/</span>
            {{ Math.ceil(paginationData.total / paginationData.pageSize) || 1 }}
            <span @click="jumpPage('next')" :class="{ disabled: paginationData.currentPage >= Math.ceil(paginationData.total / paginationData.pageSize) || 0 }">
              <i class="el-icon-arrow-right"></i>
            </span>
          </span>

          <span class="page_limit_box">
            显示
            <el-select v-model="paginationData.pageSize" size="mini" @change="handleLimitChange" style="width: 100px">
              <el-option label="10条/页" :value="10"></el-option>
              <el-option label="20条/页" :value="20"></el-option>
              <el-option label="30条/页" :value="30"></el-option>
              <el-option label="40条/页" :value="40"></el-option>
              <el-option label="50条/页" :value="50"></el-option>
              <el-option label="100条/页" :value="100"></el-option>
            </el-select>
          </span>
        </span>
        <el-button @click="btnEvent" type="primary" icon="el-icon-plus" size="small">{{ btnStr }}</el-button>
      </div>
    </div>

    <!-- 表格插槽 - 中间部分 -->
    <div class="table-content">
      <slot name="table"></slot>
    </div>

    <!-- 下方分页 - 右下角 -->
    <div class="bottom-pagination">
      <el-pagination
        :total="paginationData.total"
        background
        :page-size="paginationData.pageSize"
        :current-page.sync="paginationData.currentPage"
        layout="total, sizes, prev, pager, next, jumper"
        @size-change="handleSizeChange"
        @current-change="currentPageChange"
      />
    </div>
  </div>
</template>

<script>
export default {
  data() {
    return {
      inputPage: 1,
    };
  },
  props: {
    paginationData: {
      type: Object,
      default: () => ({
        total: 0,
        currentPage: 1,
        pageSize: 10,
      }),
    },
    btnStr: {
      type: String,
      default: '新增',
    },
    layout: {
      type: String,
      default: 'slot,jumper',
    },
  },
  computed: {
    jumpFlag() {
      return this.paginationData.total > this.paginationData.pageSize;
    },
  },
  methods: {
    btnEvent() {
      this.$emit('btnEvent');
    },
    currentPageChange(current) {
      this.paginationData.currentPage = current;
      this.$emit('pageChangeEvent', this.paginationData);
    },
    handleSizeChange(size) {
      this.paginationData.pageSize = size;
      this.paginationData.currentPage = 1; // 页码重置为1
      this.$emit('sizeChange', size);
    },
    handleLimitChange(size) {
      // 处理显示条数变更
      this.paginationData.pageSize = size;
      this.paginationData.currentPage = 1; // 页码重置为1
      this.$emit('sizeChange', size);
    },
    jumpPage(type) {
      if (type === 'prev' && this.paginationData.currentPage > 1) {
        this.paginationData.currentPage--;
      } else if (type === 'next') {
        const maxPage = Math.ceil(this.paginationData.total / this.paginationData.pageSize) || 1;
        if (this.paginationData.currentPage < maxPage) {
          this.paginationData.currentPage++;
        }
      }

      // 边界检查
      if (this.paginationData.currentPage < 1) {
        this.paginationData.currentPage = 1;
      }
      const maxPage = Math.ceil(this.paginationData.total / this.paginationData.pageSize) || 1;
      if (this.paginationData.currentPage > maxPage) {
        this.paginationData.currentPage = maxPage;
      }

      if (this.paginationData.currentPage != 1 && this.paginationData.currentPage != maxPage) {
        this.currentPageChange(this.paginationData.currentPage);
      }
    },
    openAddPlanModal() {
      this.$emit('openAddModal');
    },
  },
  created() {
    if (this.data) {
      this.paginationData = { ...this.paginationData, ...this.data };
    }
    this.inputPage = this.paginationData.currentPage;
  },
  watch: {
    data(newVal) {
      if (newVal) {
        this.paginationData = { ...this.paginationData, ...newVal };
        this.inputPage = this.paginationData.currentPage;
      }
    },
    'paginationData.currentPage'(newVal) {
      this.inputPage = newVal;
    },
  },
};
</script>

<style lang="scss">
.table-container {
  width: 100%;
  display: flex;
  flex-direction: column;
  gap: 15px;
  padding: 15px;
  box-sizing: border-box;
}

// 上方分页 - 左上角
.top-pagination {
  width: 100%;
  display: flex;
  justify-content: flex-start;
  align-items: center;
}

// 表格内容区域
.table-content {
  width: 100%;
  flex: 1;
  overflow: auto;
}

// 下方分页 - 右下角
.bottom-pagination {
  width: 100%;
  display: flex;
  justify-content: flex-end;
  align-items: center;
  padding: 5px 0;
  gap: 20px;
}

// 分页通用样式
.el-pagination {
  margin: 0;
}

.total-current {
  display: flex;
  color: #666666;
  font-size: 14px;
  .page-current {
    margin-left: 18px;
  }
}

.jump-input {
  display: inline-block;
  span {
    color: #666666;
    text-align: center;
    font-size: 14px;
    font-weight: 400;
  }
  .jump-input-area {
    width: 60px;
    .el-input__inner {
      height: 28px;
    }
  }
}

.jump-btn {
  display: inline-block;
  width: 60px;
  height: 28px;
  line-height: 28px;
  margin-left: 20px;
  text-align: center;
  font-size: 14px;
  font-weight: 400;
  color: #fff;
  background: linear-gradient(60deg, #5ebfff, #008fee);
  border-radius: 2px;
  cursor: pointer;
}

.no-jump-btn {
  display: inline-block;
  width: 60px;
  height: 28px;
  line-height: 28px;
  margin-left: 20px;
  text-align: center;
  font-size: 14px;
  font-weight: 400;
  color: #fff;
  background: #ccc;
  border-radius: 2px;
}

.el-pagination.is-background .el-pager li:not(.disabled).active {
  background: linear-gradient(60deg, #5ebfff, #008fee);
}

.pointer {
  cursor: pointer;
}

.btn-box {
  width: 100%;
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 15px;

  .page-msg {
    display: flex;
    align-items: center;
    height: 40px;
    line-height: 40px;
    font-size: 14px;

    .num {
      margin: 0 4px;
      color: #ef1f3a;
      font-size: 16px;
      text-align: right;
      font-family: PingFangSC-regular;
    }

    .page {
      margin-left: 40px;
      cursor: pointer;
      display: flex;
      align-items: center;
      // gap: 8px;

      // 当前页码样式 - 添加淡灰色边框
      .current-page {
        display: inline-block;
        min-width: 34px;
        height: 22px;
        line-height: 22px;
        text-align: center;
        border: 1px solid #e0e0e0; // 淡灰色边框
        border-radius: 6px;
        margin: 0 4px;
      }
      .page-divider {
        font-size: 18px; /* 增大斜杠尺寸 */
        margin: 0 8px; /* 前后间距 */
        color: #666; /* 斜杠颜色 */
      }
      // 禁用状态样式
      .disabled {
        color: #ccc;
        cursor: not-allowed;
      }
    }

    .page_limit_box {
      margin-left: 40px;
    }
  }
}
</style>
