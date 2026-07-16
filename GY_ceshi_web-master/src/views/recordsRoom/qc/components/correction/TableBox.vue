<template>
  <el-table
    v-loading="loading"
    :data="data"
    style="width: 100%"
    @selection-change="handleSelectionChange"
    @sort-change="handleSortChange"
    :row-class-name="tableRowClassName"
  >
    <el-table-column type="selection" width="55"></el-table-column>
    <el-table-column type="index" label="序号" width="80" />
    <el-table-column prop="BRKS_MC" label="病人科室" width="120" show-overflow-tooltip sortable/>
    <el-table-column prop="BRXM" label="患者姓名" width="100" />

    <el-table-column prop="AAA28" label="病案号" width="100">
      <template slot-scope="scope">
        <el-button class="blue-link" type="text" @click="toPage(scope.row)">{{ scope.row.AAA28 }}</el-button>
      </template>
    </el-table-column>
    <el-table-column prop="CWH" label="床号" width="100" />
    <el-table-column prop="AAB01" label="入院时间" width="160" sortable/>
    <el-table-column prop="AAC01" label="出院时间" width="160" sortable/>
    <!-- <el-table-column prop="CWH" label="床号" width="100" /> -->
    <el-table-column prop label="质控类型" width="80" show-overflow-tooltip>
      <template slot-scope="scope">
        <div :class="`quality-type-${scope.row.type}`">
          <span v-if="scope.row.type == 1">运行首页</span>
          <span v-if="scope.row.type == 2">运行病历</span>
          <span v-if="scope.row.type == 3">编目首页</span>
        </div>
      </template>
    </el-table-column>
    <el-table-column prop label="整改状态" width="80" show-overflow-tooltip>
      <template slot-scope="scope">
        <div :class="`status-${scope.row.is_correction}`">
          <span v-if="scope.row.is_correction == 0">未整改</span>
          <span v-if="scope.row.is_correction == 1">已整改</span>
        </div>
      </template>
    </el-table-column>
    <el-table-column width="180" prop="basis" label="缺陷问题" show-overflow-tooltip sortable/>
    <!-- <el-table-column
      prop=""
      label="整改级别"
      width="80"
      show-overflow-tooltip
    >
      <template slot-scope="scope">
        <span>
          <el-tag style="max-width: 80px" :type="scope.row.levels === 1 ? 'danger' : ''">
            {{ scope.row.levels == 1?'必改':'建议' }}
          </el-tag>
        </span>
      </template>
    </el-table-column>-->
    <el-table-column width="120" prop="JSR" label="接收医生" />
    <el-table-column width="160" prop="created_at" label="接收时间" sortable/>
    <!-- <el-table-column
      prop="appeal_document"
      label="申诉科室"
      show-overflow-tooltip
    />-->
    <el-table-column width="120" prop="ZKR" label="质控医师" sortable/>
    <!-- <el-table-column prop="" label="科室质控医师" width="120">
      <template slot-scope="scope">
        <span>{{Array.isArray(scope.row.ZKR) && scope.row.ZKR.map(item => item.ZKR).join(',') || ''}}</span>
      </template>
    </el-table-column>-->
    <el-table-column width="160" prop="created_at" label="质控时间" sortable/>
    <!-- <el-table-column prop="review_time" label="科室审核时间" width="160"/> -->
    <!-- <el-table-column prop="" label="病历得分" width="120">
      <template slot-scope="scope">
        <span v-if="scope.row.score" :class="`score-level-${
          scope.row.score_lv == '甲' ? '1' : 
          scope.row.score_lv == '乙' ? '3' : '4'}`"
        >{{scope.row.score}} / {{scope.row.score_lv}}</span>
      </template>
    </el-table-column>-->

    <!-- <el-table-column prop="" label="首页得分" width="120">
      <template slot-scope="scope">
        <span v-if="scope.row.home_ysz_score" :class="`score-level-${
          scope.row.home_ysz_score_lv == '优' ? '1' : 
          scope.row.home_ysz_score_lv == '良' ? '2' : 
          scope.row.home_ysz_score_lv == '中' ? '3' :  '4'}`"
        >{{scope.row.home_ysz_score}} / {{scope.row.home_ysz_score_lv}}</span>
      </template>
    </el-table-column>-->

    <!-- <el-table-column prop="ZKY" label="审核问题数量" width="120" /> -->
    <!-- <el-table-column prop="in_hospital" label="是否在院" width="120">
      <template slot-scope="scope">
        <span>{{scope.row.in_hospital == 1 ? '是' : '否'}}</span>
      </template>
    </el-table-column>-->

    <el-table-column prop="GCYSMC" label="管床医师" width="120" />
    <el-table-column prop="ZRYS_MC" label="主治医师" width="120" />
    <el-table-column prop="YLZZ" label="诊疗组长" width="120" />
    <el-table-column prop="ZRYS_MC" label="科主任" width="120" />
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
    };
  },
  methods: {
    moment,
    toPage(row) {
      const { ZYH, type } = row;
      // this.$router.push({ path: '/qc/caseViews', query: {
      //   ZYH,
      //   qualityType: type
      // }});

      this.storageSet('getData', ZYH);
      this.storageSet('getDataRule', '');
      this.goto(`/caseViews?pageType=DETAIL-KS&ZYH=${ZYH}&qualityType=${type}`);
    },

     tableRowClassName({ row }) {
      let classes = [];
      if (row.selected) {
        classes.push('selected-row');
      }
      classes.push('table-row-hover');
      return classes.join(' ');
    },

    handleSortChange(column) {
      const { prop, order } = column;
      let order_type = '';
      let order_key = '';
      if (order === 'descending') {
        order_type = 'desc';
      } else if (order === 'ascending') {
        order_type = 'asc';
      } else {
        order_type = 'desc';
      }
      order_key = prop;
      this.$emit('search', order_type, order_key);
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

::v-deep .table-row-hover:hover {
  background-color: #f8f9fa !important;
}

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