<template>
  <div class="handle-dialog">
    <el-dialog title="反馈信息确认" v-el-drag-dialog :visible.sync="dialogVisible" width="500px" :before-close="handleClose" center>
      <div class="case-info-card">
        <div class="case-info-item" v-for="(item, index) in caseInfoList" :key="index">
          <div class="case-info-label">{{ item.label }}</div>
          <div class="case-info-value">
            {{ item.value || '-' }}
          </div>
        </div>
      </div>

      <span slot="footer" class="dialog-footer">
        <el-button @click="dialogVisible = false">取 消</el-button>
        <el-button type="primary" @click="handleConfirm">确 定</el-button>
      </span>
    </el-dialog>
  </div>
</template>

<script>
export default {
  name: 'CaseFeedbackDialog',
  props: {
    basicData: {
      type: Object,
      default() {
        return {
          ks: '-',
          sxys: '-',
        };
      },
    },
  },
  data() {
    return {
      dialogVisible: false,
      caseInfoList: [],
      caseDetail: {
        mzh: '', // 门诊号
        rule_id: '', // 反馈问题rule_id
        xm: '', // 患者姓名
        jzsj: '', // 就诊时间
        ks: '', // 就诊科室（前端显示）
        KS_CODE: '', // 就诊科室编码（传递后端）
        sxys: '', // 书写医师（前端显示）
        SXYS_CODE: '', // 书写医师编码（传递后端）
        blbh: '',
      },
    };
  },
  watch: {
    caseDetail: {
      deep: true,
      handler(newVal) {
        this.formatCaseInfo(newVal);
      },
    },
    dialogVisible(val) {
      if (val) {
        this.formatCaseInfo(this.caseDetail);
      }
    },
  },
  methods: {
    init(row) {
      this.dialogVisible = true;
      this.caseDetail = row;
    },
    formatCaseInfo(caseData) {
      this.caseInfoList = [
        { label: '门诊号：', value: caseData.mzh, key: 'mzh' },
        { label: '患者姓名：', value: caseData.xm, key: 'xm' },
        { label: '就诊时间：', value: caseData.jzsj, key: 'jzsj' },
        { label: '就诊科室：', value: this.basicData.ks, key: 'ks' },
        { label: '书写医师：', value: this.basicData.sxys, key: 'sxys' },
      ];
    },
    handleClose(done) {
      this.$confirm('确认关闭反馈确认窗吗？未确认的信息将不会存档。')
        .then(_ => {
          done();
          this.dialogVisible = false;
        })
        .catch(_ => {});
    },
    handleConfirm() {
      console.log('this.caseDetail', this.caseDetail);
      const params = {
        blbh: this.caseDetail.BLBH,
        rule_id: this.caseDetail.rule_id,
      };
      this.$axios2
        .post('/mz_feedback', params)
        .then(res => {
          if (res.code == 200) {
            this.$emit('confirmFeedback');
            this.dialogVisible = false;
            this.$message({
              message: '反馈成功',
              type: 'success',
            });
          }
        })
        .catch(err => {
          console.error('反馈请求失败：', err);
        });
    },
  },
};
</script>

<style lang="scss" scoped>
.handle-dialog {
  ::v-deep .el-dialog__header {
    background-color: hsl(205.32deg 43.43% 49.22%);
    padding: 12px 20px;
  }

  ::v-deep .el-dialog__headerbtn {
    top: 14px !important;
  }
  ::v-deep .el-dialog__close {
    color: #fff;
    border: 1px solid #fff;
    border-radius: 50%;
    width: 22px;
    height: 22px;
    line-height: 20px;
    // top: 12px;
  }

  ::v-deep .el-dialog__title {
    color: #fff;
    font-size: 16px;
    font-weight: 500;
  }

  ::v-deep .el-dialog__body {
    padding: 20px;
    background-color: #fafafa;
    padding-bottom: 10px;
  }

  .case-info-card {
    width: 100%;
    background: #fff;
    border-radius: 4px;
    padding: 16px 20px;
    border: 1px solid #ebeef5;
  }

  .case-info-item {
    display: flex;
    align-items: flex-start;
    justify-content: flex-start;
    margin-bottom: 12px;
    &:last-child {
      margin-bottom: 0;
    }
  }

  .case-info-label {
    width: 100px;
    text-align: right;
    color: #606266;
    font-weight: 500;
    padding-right: 16px;
    line-height: 24px;
  }

  .case-info-value {
    flex: 1;
    color: #303133;
    line-height: 24px;
    word-break: break-all;
  }

  .dialog-footer {
    display: flex;
    justify-content: center;
    padding: 12px 0;
  }
}
</style>