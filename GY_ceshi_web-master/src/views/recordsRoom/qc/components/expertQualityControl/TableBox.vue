<template>
  <el-table
    v-loading="loading"
    :data="data"
    style="width: 100%"
    @sort-change="handleSortChange"
    ref="filterTableRef"
  >
    <!-- <el-table-column type="selection" width="40"></el-table-column> -->
    <el-table-column type="index" label="序号" width="50" />
    <el-table-column prop="AAA28" label="病案号" width="80">
      <template slot-scope="scope">
        <el-button type="text" @click="toPage(scope.row)">{{ scope.row.AAA28 }}</el-button>
      </template>
    </el-table-column>
    <el-table-column prop="BRXM" label="患者姓名" width="90" />
    <el-table-column prop="AAB01" label="入院时间" sortable/>
    <el-table-column prop="AAC01" label="出院时间" sortable/>
  </el-table>
</template>

<script>
import moment from 'moment/moment';

export default {
  props: {
    data: {
      type: Array,
      default() {
        return [];
      },
    },
    loading: {
      type: Boolean,
      default() {
        return false;
      },
    }
  },
  data() {
    return {
      selectedArray: []
    }
  },
  emits: ['onClickRow', 'sortChange'],
  methods: {
    moment,
    toPage(row) {
      this.$emit('onClickRow', row)
    },

    handleSelectionChange(val) {
      this.selectedArray = val;
    },
    handleSortChange(column) {
      this.$emit('sortChange', column)
    }
  },
};
</script>

<style lang="scss" scoped>
// ::v-deep.el-table {
//   .selected-row {
//     background-color:#ecf5ff !important;
//   }
// }

@mixin status() {
    width: 60px;
    text-align: center;
    border-width: 1px;
    border-style: solid;
    border-radius: 4px;
    font-weight: 500
}

.status-0 {
  @include status();
  background-color: #fccbd4;
  border-color: #ef1f3a;
  color: #ef1f3a;
}
.status-1 {
  @include status();
  background-color: #d2e4d6;
  border-color: #318240;
  color: #318240;
}
.status-2 {
  @include status();
  background-color: #ef1f3a;
  border-color: #ef1f3a;
  color: #ffffff;
}

@mixin score-level() {
    font-weight: 500
}

.score-level-1 {
  @include score-level();
  color: #318240;
}
.score-level-2 {
  @include score-level();
  color: #89c30f;
}
.score-level-3 {
  @include score-level();
  color: #ec890e;
}
.score-level-4 {
  @include score-level();
  color: #ef1f3a;
}

.quality-type-1 {
  @include score-level();
  color: #07818a;
}
.quality-type-2 {
  @include score-level();
  color: #3c108f;
}
.quality-type-3 {
  @include score-level();
  color: #a26d0a
}
</style>