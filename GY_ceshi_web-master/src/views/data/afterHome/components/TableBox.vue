<template>
  <div class="part-box">
    <div class="btn-box">
      <span class="page-msg">
        共找到 <span class="num">{{ page.total }}</span> 条结果
        <span class="page" v-if="page.total">{{ page.page }}/{{ Math.ceil(page.total / page.limit) }}</span>
        <span class="page_limit_box">
          显示
          <el-select v-model="page.limit" size="mini" @change="handleLimitChange" style="width: 100px;">
            <el-option label="10条/页" :value="10"></el-option>
            <el-option label="20条/页" :value="20"></el-option>
            <el-option label="30条/页" :value="30"></el-option>
            <el-option label="50条/页" :value="50"></el-option>
          </el-select>
        </span>
      </span>
      <el-button type="primary" icon="el-icon-download" @click="onExport" class="export-btn">导出数据</el-button>
    </div>
    <el-table
      v-loading="loading"
      border
      ref="tableRef"
      :data="data"
      :row-key="getRowKey"
      style="width: 100%">
      <el-table-column
        type="index"
        label="序号"
        align="center"
        width="120">
      </el-table-column>
      <el-table-column
        prop="field"
        label="缺陷字段"
        width="200">
      </el-table-column>
      <el-table-column
        prop="desc"
        label="缺陷描述">
      </el-table-column>
      <el-table-column
        prop=""
        label="缺陷数量"
        width="200">
        <template slot-scope="scope">
          <span class="link"  @click="toPage(scope.row, scope.$index)">{{ scope.row.count }}</span>
        </template>
      </el-table-column>
      <el-table-column
        prop="level"
        label="缺陷分级"
        width="200">
      </el-table-column>
      <el-table-column
        prop="type"
        label="缺陷归类"
        width="200">
      </el-table-column>
    </el-table>
  </div>
</template>

<script>

export default {
  props: {
    data: {
      type: Array,
      default() {
        return []
      }
    },
    type_name:{  // 'lc' 临床
      type: String,
      default() {
        return ''
      }
    },
    loading: {
      type: Boolean,
      default() {
        return false
      }
    },
    hospital_name:{
      type: String,
      default() {
        return ''
      }
    },
    search: {
      type: Object,
      default() {
        return {}
      }
    },
    page: {
      type: Object,
      default() {
        return {
          total: 0,
          page: 1,
          limit: 10,
        }
      }
    }
  },
  data() {
    return {
      lastClickedIndex: -1
    }
  },
  activated() {
    this.restoreScrollPosition();
  },
  watch: {
    data() {
      this.$nextTick(() => {
        this.restoreScrollPosition();
      });
    }
  },
  methods: {
    // 模板导出
    onExport() {
      this.$emit('export')
    },
    // 获取行的唯一标识
    getRowKey(row) {
      return row.error_rule || JSON.stringify(row);
    },
    toPage(row, index) {
      // 记录点击的位置
      this.lastClickedIndex = index;
      sessionStorage.setItem('lastClickedIndex', index.toString());
      const { error_rule } = row
      this.$router.push({ 
        path: '/defectRuleProblem', 
        query: {
          type_name: this.type_name,
          error_rule,
          hospital_name: this.hospital_name,
          start_time: this.search.start_time,
          end_time: this.search.end_time,
          zk_start_time: this.search.zk_start_time,
          zk_end_time: this.search.zk_end_time,
          ry_start_time: this.search.ry_start_time,
          ly_end_time: this.search.ly_end_time,
          zyhm: this.search.zyhm,
          cykb: this.search.cykb
        }
      })
    },

     // 恢复滚动位置
    restoreScrollPosition() {
      const savedIndex = sessionStorage.getItem('lastClickedIndex');
      if (savedIndex && this.$refs.tableRef && this.data.length > 0) {
        const index = parseInt(savedIndex);
        if (index >= 0 && index < this.data.length) {
          this.$nextTick(() => {
            const table = this.$refs.tableRef;
            const row = table.$el.querySelectorAll('.el-table__row')[index];
            if (row) {
              row.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
            // 清除记录，避免重复定位
            sessionStorage.removeItem('lastClickedIndex');
            this.lastClickedIndex = -1;
          });
        }
      }
    },
    handleLimitChange(val) {
      this.$emit('limit_change', val)
    }
  }
}
</script>

<style lang="scss" scoped>
.btn-box {
  text-align: right;
  margin-bottom: 15px;
  .page-msg {
    float: left;
    height: 40px;
    line-height: 40px;
    font-size: 14px;
    .num {
      color: #F56C6C;
    }
    .page {
      margin-left: 40px;
    }
    .page_limit_box {
      margin-left: 40px;
    }
  }
}
.link {
  cursor: pointer;
  color: #409EFF;
}
</style>
