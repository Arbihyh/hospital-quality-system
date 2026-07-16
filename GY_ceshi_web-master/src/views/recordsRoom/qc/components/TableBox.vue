<template>
  <div>
    <el-row class="filter-list-action" style="width: 100%" type="flex" justify="start" align="middle">
      <el-button type="primary" @click="toExamine">批量审核</el-button>
      <el-button @click="onRevoke">撤销审核</el-button>
      <!-- <el-switch v-model="status" style="margin-left: 20px;" :active-value="1" :inactive-value="0"
        inactive-text="是否自动质控" @change="updateQualityControl">
      </el-switch> -->
    </el-row>
    <el-table class="filter-list-table" :row-class-name="tableRowClassName" v-loading="loading" :data="data" style="width: 100%" @selection-change="handleSelectionChange">
      <el-table-column type="selection" width="45"></el-table-column>
      <el-table-column type="index" label="序号" width="50" />
      <el-table-column prop="BRKS_MC" label="病人科室" width="120" show-overflow-tooltip />
      <el-table-column prop="BRXM" label="患者姓名" width="80" show-overflow-tooltip />
      <el-table-column prop="AAA28" label="病案号" width="80">
        <template slot-scope="scope">
          <el-button class="blue-link" type="text" @click="toPage(scope.row)">{{ scope.row.AAA28 }}</el-button>
        </template>
      </el-table-column>

      <el-table-column prop="CWH" label="床号" width="80" show-overflow-tooltip />
      <el-table-column prop="review_status" label="审核状态" width="80">
        <template slot-scope="scope">
          <div :class="`review-status-${scope.row.review_status}`">
            {{
              scope.row.review_status == 1
                ? '审核中'
                : scope.row.review_status == 2
                ? '已通过'
                : scope.row.review_status == 3
                ? '未通过'
                : scope.row.review_status == 0
                ? '未审核'
                : ''
            }}
          </div>
        </template>
      </el-table-column>
      <el-table-column prop="" label="整改状态" width="100">
        <template slot-scope="scope">
          <span>{{ scope.row.correction_success_nums }} / {{ scope.row.correction_total }}</span>
        </template>
      </el-table-column>

      <el-table-column prop="" label="病历得分" width="100">
        <template slot-scope="scope">
          <span v-if="scope.row.score" :class="`score-level-${scope.row.score_lv == '甲' ? '1' : scope.row.score_lv == '乙' ? '3' : '4'}`">
            {{ scope.row.score }} / {{ scope.row.score_lv }}
          </span>
        </template>
      </el-table-column>

      <el-table-column prop="" label="首页得分" width="100">
        <template slot-scope="scope">
          <span
            v-if="scope.row.home_ysz_score"
            :class="`score-level-${scope.row.home_ysz_score_lv == '优' ? '1' : scope.row.home_ysz_score_lv == '良' ? '2' : scope.row.home_ysz_score_lv == '中' ? '3' : '4'}`"
          >
            {{ scope.row.home_ysz_score }} / {{ scope.row.home_ysz_score_lv }}
          </span>
        </template>
      </el-table-column>
      <el-table-column prop="" label="审核医师" width="120">
        <template slot-scope="scope">
          <span>{{ (Array.isArray(scope.row.ZKR) && scope.row.ZKR.map(item => item.ZKR).join(',')) || '' }}</span>
        </template>
      </el-table-column>
      <el-table-column prop="review_time" label="审核时间" width="120" show-overflow-tooltip />
      <!-- <el-table-column prop="ZKY" label="审核问题数量" width="120" /> -->
      <!-- <el-table-column prop="in_hospital" label="是否在院" width="120">
          <template slot-scope="scope">
            <span>{{scope.row.in_hospital == 1 ? '是' : '否'}}</span>
          </template>
        </el-table-column> -->
      <el-table-column prop="AAB01" label="入院时间" width="120" show-overflow-tooltip />
      <el-table-column prop="AAC01" label="出院时间" width="120" show-overflow-tooltip />
      <el-table-column prop="GCYSMC" label="管床医师" width="120" />
      <el-table-column prop="AEE03_name" label="主治医师" width="120" />
      <el-table-column prop="YLZZ" label="诊疗组长" width="120" />
      <el-table-column prop="AEE01_name" label="科主任" width="120" />
    </el-table>
    <el-dialog title="撤销审核申请" width="50%" :visible.sync="dialogVisible">
      <el-form ref="alertForm" :model="alertForm" label-width="120px" :rules="rules">
        <el-form-item label="撤销审核原因" prop="reason">
          <el-input v-model="alertForm.reason" type="textarea" placeholder="原因" />
        </el-form-item>
        <el-form-item>
          <el-button @click="dialogVisible = false">取 消</el-button>
          <el-button type="primary" @click="addSubmit">确 定</el-button>
        </el-form-item>
      </el-form>
    </el-dialog>
  </div>
</template>

<script>
import { applyForReview, updateQualityControl, getQualityControlStatus } from '@/api/qc';
export default {
  props: {
    data: {
      type: Array,
      default() {
        return [];
      },
      multipleSelection: [],
    },
    loading: {
      type: Boolean,
      default() {
        return false;
      },
    },
    // status: {
    //   type: Number,
    //   default() {
    //     return false;
    //   },
    // },
  },
  data() {
    return {
      dialogVisible: false,
      status: 0,
      alertForm: {
        reason: '',
      },
      rules: {
        reason: [{ required: true, message: '请填写撤销审核原因', trigger: 'blur' }],
      },
    };
  },
  beforeDestroy() {
    localStorage.removeItem('getData');
  },
  mounted() {
    this.getQualityControlStatus();
  },
  methods: {
    tableRowClassName({ row }) {
      console.log('>>>>><<<<<<<', row);
      if (row.selected) {
        return 'selected-row';
      }
      return '';
    },
    toPage(row) {
      const { ZYH } = row;
      // this.$router.push({
      //   path: '/qc/caseViews', query: {
      //     ZYH
      //   }
      // });
      this.storageSet('getData', ZYH);
      this.storageSet('getDataRule', '');
      this.goto(`/caseViews?pageType=LIST-KS&ZYH=${ZYH}`);
    },

    /**
     * 选中
     * @param val
     */
    handleSelectionChange(val) {
      this.multipleSelection = val;
    },

    /**
     * 更新开关状态
     */
    updateQualityControl() {
      updateQualityControl({ status: this.status })
        .then(res => {
          this.$message.success(res.msg || '更新成功');
          this.getQualityControlStatus();
        })
        .catch(error => {
          console.log(error);
        });
    },

    /**
     * 获取开关状态
     */
    getQualityControlStatus() {
      getQualityControlStatus()
        .then(res => {
          this.status = Number(res.data.status);
        })
        .catch(error => {
          console.log(error);
        });
    },

    /**
     * 批量审核
     */
    toExamine() {
      var ZYH = this.multipleSelection.map(row => row['ZYH']);
      applyForReview({ ZYH: ZYH, status: 2 })
        .then(res => {
          this.$message.success(res.msg || '申请成功');
          this.$parent.getList();
        })
        .catch(error => {
          if (error && error.message) {
            const errorMsg = error.message;
            this.$alert(errorMsg, '批量审核', {
              confirmButtonText: '确定',
              callback: action => {},
            });
          }
        });
    },

    /**
     * 撤销审核
     */
    onRevoke() {
      this.dialogVisible = true;
    },

    /**
     * 提交撤销审核原因
     */
    addSubmit() {
      var ZYH = this.multipleSelection.map(row => row['ZYH']);
      if (this.alertForm.reason === '') {
        this.$message.error('请填写撤销审核原因！');
        return false;
      }
      applyForReview({ ZYH: ZYH, status: 0, reason: this.alertForm.reason })
        .then(res => {
          this.$message.success(res.msg || '撤销成功');
          this.dialogVisible = false;
          this.$parent.getList();
        })
        .catch(error => {
          if (error && error.message) {
            const errorMsg = error.message;
            this.$alert(errorMsg, '撤销审核', {
              confirmButtonText: '确定',
              callback: action => {},
            });
          }
        });
    },
  },
};
</script>

<style lang="scss" scoped>
::v-deep.el-table {
  .selected-row {
    background-color: #ecf5ff !important;
  }
}

@mixin review-status() {
  width: 60px;
  text-align: center;
  border-width: 1px;
  border-style: solid;
  border-radius: 4px;
  font-weight: 500;
}

.review-status-0 {
  @include review-status();
  background-color: #fccbd4;
  border-color: #ef1f3a;
  color: #ef1f3a;
}

.review-status-1 {
  @include review-status();
  background-color: #fbe6cd;
  border-color: #ec890e;
  color: #ec890e;
}

.review-status-2 {
  @include review-status();
  background-color: #d2e4d6;
  border-color: #318240;
  color: #318240;
}

.review-status-3 {
  @include review-status();
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
</style>
