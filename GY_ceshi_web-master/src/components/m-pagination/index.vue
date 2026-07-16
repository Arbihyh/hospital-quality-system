<template>
  <!-- 分页控制 -->
  <div class="table-pagination" style="padding: 15px 0px">
    <div class="total-current">
      <div class="page-total">共{{ paginationData.total }}条记录</div>
      <div
        class="page-current"
      >第{{ paginationData.currentPage }}/{{ Math.ceil(paginationData.total / paginationData.pageSize) }}页</div>
    </div>
    <el-pagination
      background
      :layout="layout"
      :total="paginationData.total"
      :page-size="paginationData.pageSize"
      :page-sizes="[10, 30, 50, 100, 300, 500]"
      :current-page.sync="paginationData.currentPage"
      layout="sizes, prev, pager, next, slot"
      @current-change="currentPageChange"
      @size-change="handleSizeChange"
    >
      <div class="jump-input">
        <span>第</span>
        <el-input size="small" class="jump-input-area" v-model.number="inputPage"></el-input>
        <span>页</span>
      </div>
      <div class="jump-btn pointer" @click="jumpPage" v-if="jumpFlag">跳转</div>
      <div class="no-jump-btn" v-else>跳转</div>
    </el-pagination>
  </div>
</template>

<script>
export default {
  data() {
    return {
      paginationData: {
        total: 0,
        currentPage: 1,
        pageSize: 10,
      },
      inputPage: 1,
    };
  },
  props: {
    data: {
      type: Object,
      default: null,
    },
    layout: {
      type: String,
      default() {
        return 'prev, pager, next, slot';
      },
    },
  },
  computed: {
    jumpFlag() {
      return this.paginationData.total > this.paginationData.pageSize;
    },
  },
  methods: {
    currentPageChange(current) {
      this.paginationData.currentPage = current;
      this.$emit('pageChangeEvent', this.paginationData);
    },
    handleSizeChange(size) {
      this.paginationData.pageSize = size;
      this.$emit('sizeChange', size);
    },
    jumpPage() {
      // 判断是否是数字
      let reg = /^[0-9]+.?[0-9]*$/;
      if (!reg.test(this.inputPage)) {
        this.inputPage = '';
        this.$message({
          type: 'error',
          message: '请输入正整数!',
        });
        return false;
      }
      // 判断范围
      if (this.inputPage < 1) {
        this.inputPage = 1;
      }
      if (this.inputPage > Math.ceil(this.paginationData.total / this.paginationData.pageSize)) {
        this.inputPage = Math.ceil(this.paginationData.total / this.paginationData.pageSize);
      }
      // 设置跳转
      this.currentPageChange(this.inputPage);
    },
  },
  created() {
    this.paginationData = this.data;
  },
};
</script>

<style lang="scss">
.table-pagination {
  display: flex;
  justify-content: flex-end;
  align-items: center;
  margin-bottom: 0;
  padding-right: 40px;
  .total-current {
    display: flex;
    color: #666666;
    font-size: 14px;
    margin-right: 15px;
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
}
</style>
