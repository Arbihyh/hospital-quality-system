<template>
  <div :class="{'hidden':hidden}" class="pagination-container">
    <span class="cus-total">共{{ total }} 条记录 第 {{ page }}/{{ Math.ceil( total / limit ) }} 页</span>
    <el-pagination
      :background="background"
      :current-page.sync="currentPage"
      :page-size.sync="pageSize"
      :layout="layout"
      :page-sizes="pageSizes"
      :total="total"
      v-bind="$attrs"
      @size-change="handleSizeChange"
      @current-change="handleCurrentChange"
    />
  </div>
</template>

<script>
// import { scrollTo } from '@/utils/scroll-to'

export default {
  name: 'Pagination',
  props: {
    total: {
      required: true,
      type: Number
    },
    page: {
      type: Number,
      default: 1
    },
    limit: {
      type: Number,
      default: 10
    },
    pageSizes: {
      type: Array,
      default() {
        return [10, 20, 30, 50]
      }
    },
    layout: {
      type: String,
      default: 'prev, pager, next, jumper'
    },
    background: {
      type: Boolean,
      default: true
    },
    autoScroll: {
      type: Boolean,
      default: true
    },
    hidden: {
      type: Boolean,
      default: false
    }
  },
  computed: {
    currentPage: {
      get() {
        return this.page
      },
      set(val) {
        this.$emit('update:page', val)
      }
    },
    pageSize: {
      get() {
        return this.limit
      },
      set(val) {
        this.$emit('update:limit', val)
      }
    }
  },
  methods: {
    handleSizeChange(val) {
      this.$emit('pagination', { page: this.currentPage, limit: val })
      // if (this.autoScroll) {
      //   scrollTo(0, 800)
      // }
    },
    handleCurrentChange(val) {
      this.$emit('pagination', { page: val, limit: this.pageSize })
      // if (this.autoScroll) {
      //   scrollTo(0, 800)
      // }
    }
  }
}
</script>

<style scoped>
.el-pagination {
  float: right;
}
.cus-total {
  float: left;
  font-size: 13px;
  color: #606266;
  font-family: Helvetica Neue, Helvetica, PingFang SC, Hiragino Sans GB, Microsoft YaHei, Arial, sans-serif;
  line-height: 28px;
}
.pagination-container {
  background: #fff;
  padding: 0 16px;
  overflow: hidden;
}
.pagination-container.hidden {
  display: none;
}
</style>
