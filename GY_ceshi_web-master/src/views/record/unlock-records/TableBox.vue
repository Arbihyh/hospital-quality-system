<template>
  <el-table
    ref="tableRef"
    v-loading="loading"
    :data="data"
    style="width: 100%"
    @selection-change="handleSelectionChange"
  >
    <el-table-column type="index" label="序号" width="80" />
    <el-table-column prop="department" label="病人科室" width="120" show-overflow-tooltip />
    <el-table-column prop="patient_name" label="患者姓名" width="80" show-overflow-tooltip />
    <el-table-column prop="medical_record_no" label="病案号" width="100" show-overflow-tooltip>
      <template slot-scope="scope">
        <el-button
          class="blue-link"
          type="text"
          @click="toPage(scope.row, scope.$index)"
        >{{ scope.row.medical_record_no || '-' }}</el-button>
      </template>
    </el-table-column>
    <el-table-column prop="bed_no" label="床号" width="80" show-overflow-tooltip />
    <el-table-column label="审核状态" width="100" show-overflow-tooltip>
      <template slot-scope="scope">
        <div :class="`status-${scope.row.audit_status}`">
          <span>{{ getAuditText(scope.row.audit_status) }}</span>
        </div>
      </template>
    </el-table-column>
    <el-table-column prop="unlock_applicant" label="解锁医师" width="100" show-overflow-tooltip />
    <el-table-column prop="unlock_reason" label="解锁原因" width="180" show-overflow-tooltip />
    <el-table-column prop="unlock_time" label="解锁时间" width="160" show-overflow-tooltip>
      <template slot-scope="scope">
        <span>{{ scope.row.unlock_time || '-' }}</span>
      </template>
    </el-table-column>
    <el-table-column prop="rule_name" label="质控规则" width="180" show-overflow-tooltip />
    <el-table-column prop="auditor" label="审核医师" width="120" show-overflow-tooltip />
    <el-table-column prop="audit_time" label="审核时间" width="160" show-overflow-tooltip>
      <template slot-scope="scope">
        <span>{{ scope.row.audit_time || '-' }}</span>
      </template>
    </el-table-column>
    <el-table-column prop="audit_reason" label="通过或驳回原因" width="160" show-overflow-tooltip />
    <el-table-column prop="rule_type" label="质控类型" width="90" show-overflow-tooltip />
    <el-table-column prop="admission_time" label="入院时间" width="160" />
    <el-table-column prop="discharge_time" label="出院时间" width="160">
      <template slot-scope="scope">
        <span>{{ scope.row.discharge_time || '未出院' }}</span>
      </template>
    </el-table-column>
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
    },
  },
  data() {
    return {
      selectedArray: [],
      lastClickedIndex: -1,
    };
  },
  activated() {
    if (this.lastClickedIndex > -1) {
      this.restoreScrollPosition();
    }
  },
  methods: {
    moment,
    toPage(row, index) {
      console.log("toPage",row, index);
      this.lastClickedIndex = index;
      const { zyh } = row;
      let tabType = 'REVIEW';
      this.storageSet('getData', zyh);
      this.storageSet('getDataRule', '');
      this.goto(`/caseViews?pageType=LIST-KS&from=review&ZYH=${zyh}&desc=${row.rule_name}&tabType=${tabType}&isNotSource=OK`);
    },
    getAuditText(status) {
      console.log("getAuditText",status);
      const map = {
        0: '审核中',
        1: '通过',
        2: '驳回',
      };
      return map[status] || '-';
    },
    restoreScrollPosition() {
      this.$nextTick(() => {
        const table = this.$refs.tableRef;
        const row = table.$el.querySelectorAll('.el-table__row')[this.lastClickedIndex];
        if (row) {
          row.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        this.lastClickedIndex = -1;
      });
    },

    handleSelectionChange(val) {
      this.selectedArray = val;
    },
  },
};
</script>

<style lang="scss" scoped>
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
.status-3 {
  @include status();
  background-color: #ffcc0088;
  border-color: #ffcc0088;
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

@mixin status-tag {
  display: inline-block;
  width: 60px;
  text-align: center;
  border-width: 1px;
  border-style: solid;
  border-radius: 4px;
  font-weight: 500;
  padding: 2px 0;
}

.status-0 {
  @include status-tag();
  background-color: #fff7e6;
  border-color: #faad14;
  color: #faad14;
}
.status-1 {
  @include status-tag();
  background-color: #d2e4d6;
  border-color: #318240;
  color: #318240;
}
.status-2 {
  @include status-tag();
  background-color: #fff2f0;
  border-color: #ef1f3a;
  color: #ef1f3a;
}

.blue-link {
  color: #1b64b0;
  padding: 0;
}
.blue-link:hover {
  color: #0f4a8c;
}
</style>