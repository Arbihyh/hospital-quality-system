<template>
  <div>
    <el-table v-loading="loading" :data="data" style="width: 100%" @selection-change="handleSelectionChange">
      <!-- <el-table-column type="selection" width="55"></el-table-column> -->
      <el-table-column type="index" label="序号" width="80" />
      <el-table-column prop="title" label="计划名称" width="320" header-align="center">
        <template slot-scope="scope">
          <el-button class="blue-link" type="text" @click="openAddPlanModal('DETAIL', scope.row)">{{ scope.row.title }}</el-button>
        </template>
      </el-table-column>
      <el-table-column prop="" label="进度条" width="280" show-overflow-tooltip header-align="center" align="center">
        <template slot-scope="scope">
          <el-progress :text-inside="true" :stroke-width="24" :percentage="scope.row.progress"></el-progress>
        </template>
      </el-table-column>
      <el-table-column prop="" label="质控周期" width="280" header-align="center" align="center">
        <template slot-scope="scope">
          <span>{{ scope.row.start_time ? moment(scope.row.start_time * 1000).format('YYYY-MM-DD') : '-' }}</span>
          &nbsp;至&nbsp;
          <span>{{ scope.row.end_time ? moment(scope.row.end_time * 1000).format('YYYY-MM-DD') : '-' }}</span>
        </template>
      </el-table-column>
      <el-table-column prop="add_user" label="发布人" header-align="center" align="center" />
      <el-table-column prop="created_at" label="发布时间" header-align="center" align="center">
        <template slot-scope="scope">
          <span>{{ scope.row.created_at ? moment(scope.row.created_at * 1000).format('YYYY-MM-DD') : '' }}</span>
        </template>
      </el-table-column>
      <el-table-column prop="" label="操作" header-align="center" align="center">
        <template slot-scope="scope">
          <el-button type="text" @click="openAddPlanModal('EDIT', scope.row)">修改</el-button>
          <span>|</span>
          <el-button type="text" style="color: #ef1f3a" @click="deleteRow(scope.row)">删除</el-button>
        </template>
      </el-table-column>
    </el-table>
    <AddPlanModalBoxVue ref="AddPlanModalBoxVueRef" @onUpdate="$emit('onUpdate')" />
  </div>
</template>

<script>
import moment from 'moment/moment';
import { examineAppeal, planSaveAndEdit, planList, planDel } from '@/api/qc';
import AddPlanModalBoxVue from '@/views/recordsRoom/qc/components/qualityControlPlan/AddPlanModal2.vue';

export default {
  components: {
    AddPlanModalBoxVue,
  },
  emits: ['onUpdate'],
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
    },
  },
  data() {
    return {
      selectedArray: [],
    };
  },
  methods: {
    moment,
    formatTime(timestamp) {
      if (!timestamp || timestamp <= 0) {
        return '-';
      }
      return moment(timestamp * 1000).format('YYYY-MM-DD');
    },
    toPage(row) {
      const { ZYH } = row;
      this.$router.push({
        path: '/qc/caseViews',
        query: {
          ZYH,
          from: 'review',
        },
        meta: {
          title: '申诉详情',
        },
      });
    },
    openAddPlanModal(action, row) {
      this.$refs.AddPlanModalBoxVueRef.openModal(action, row);
    },
    deleteRow(row) {
      this.$confirm('确认删除此条数据?', '提示', {
        type: 'warning',
      }).then(() => {
        this.$axios
          .post('/case_quality_plan/delete', { id: row.id })
          .then(res => {
            this.$message.success('删除成功');
            this.$emit('onUpdate');
          })
          .catch(error => {
            this.$message.error('删除失败');
            this.$emit('onUpdate');
          });
        // planDel({ id: row.id })
        //   .then(res => {
        //     this.$message.success('删除成功');
        //     this.$emit('onUpdate');
        //   })
        //   .catch(error => {
        //     this.$message.error('删除失败');
        //     this.$emit('onUpdate');
        //   });
      });
    },

    handleSelectionChange(val) {
      this.selectedArray = val;
    },
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
  font-weight: 500;
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
  font-weight: 500;
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
  color: #a26d0a;
}
</style>