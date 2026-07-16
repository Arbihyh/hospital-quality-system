<template>
  <el-dialog
    :visible.sync="dialogVisible"
    class="custom-dialog"
    :modal="false"
    :title="getDialogTitle()"
    :width="dialogWidth"
  >
    <div class="medical-record" style="margin-top: 30px">
      <el-descriptions>
        <el-descriptions-item label="病案号">{{ baseInfo.AAA28 }}</el-descriptions-item>
        <el-descriptions-item label="床号">{{ baseInfo.CH }}</el-descriptions-item>
        <el-descriptions-item label="患者姓名">{{ baseInfo.BRXM }}</el-descriptions-item>
      </el-descriptions>
    </div>
    <div class="reason" style="margin-top: 20px">
      <el-form :model="appealForm" :rules="appealRules" ref="appealFormRef" label-width="80px">
        <div v-if="dialogType === 'pending'">
          <el-form-item label="解锁人">
            <el-input
              disabled
              v-model="appealForm.unlock_applicant"
              placeholder="请输入解锁人"
            ></el-input>
          </el-form-item>
          <el-form-item label="解锁原因">
            <el-input
              disabled
              type="textarea"
              v-model="appealForm.unlock_reason"
              placeholder="请输入解锁原因"
              :rows="2"
            ></el-input>
          </el-form-item>
        </div>

        <div v-else-if="isAuditFormType">
          <el-form-item :label="`${getDialogTitle()}医师`" prop="doctor">
            <el-select
              style="width:100%"
              v-model="appealForm.doctor"
              filterable
              clearable
              :placeholder="`请选择${getDialogTitle()}医师姓名及工号`"
            >
              <el-option
                v-for="item of staffList"
                :key="item.code"
                :label="`${item.name}`"
                :value="`${item.name}`"
              />
            </el-select>
          </el-form-item>
          <el-form-item :label="`${getDialogTitle()}原因`" prop="reason">
            <el-input
              type="textarea"
              v-model="appealForm.reason"
              :placeholder="`请输入${getDialogTitle()}原因`"
              :rows="2"
            ></el-input>
          </el-form-item>
        </div>
      </el-form>
    </div>
    <div class="cont-reight-bottom-conter" style="color: #606266 !important">
      <p>
        <span class="bold" style="color: #606266 !important">字段：</span>
        {{ dialogFormsLabel.zd_field_name }}
      </p>
      <p style="margin-top: 10px">
        <span class="bold" style="color: #606266 !important">提示：</span>
        {{ dialogFormsLabel.ts_desc }}
      </p>
    </div>
    <template #footer>
      <div class="dialog-footer-container justify-end">
        <div class="btn-right" v-if="dialogType === 'pending'">
          <div class="appeal_in_yes" @click="submitAppealHandle('appeal_in_yes')">通过</div>
          <div class="appeal_in_no" @click="submitAppealHandle('appeal_in_no')">驳回</div>
        </div>
        <el-button
          v-if="isAuditFormType"
          class="btn-right"
          type="primary"
          :style="{
            backgroundColor: dialogType === 'appeal_in_no' ? '#ef1f3a' : '#1b64b0',
            borderColor: dialogType === 'appeal_in_no' ? '#ef1f3a' : '#1b64b0',
          }"
          @click="submitAppeal()"
        >{{ getDialogTitle() }}</el-button>
      </div>
    </template>
  </el-dialog>
</template>
<script>
import { getBrry, getStaffListData } from '@/api/qc';
export default {
  emits: ['onUpdate'],
  data() {
    return {
      dialogVisible: false,
      staffList: [],
      dialogType: '',
      enteredFromPending: false,
      row: {},
      appealForm: {
        reason: '',
        doctor: '',
        unlock_applicant: '',
        unlock_reason: '',
      },
      currentId: '',
      MEDRECID: '',
      dialogFormsLabel: {
        zd_field_name: '',
        ts_desc: '',
      },
      baseInfo: {},
      dialogWidth: '30%',
    };
  },
  computed: {
    isAuditFormType() {
      return this.dialogType === 'appeal_in_yes' || this.dialogType === 'appeal_in_no';
    },
    appealRules() {
      return {
        doctor: [
          { required: true, message: '请选择医师', trigger: 'change' },
        ],
        reason: [
          {
            required: this.dialogType === 'appeal_in_no',
            message: `请输入${this.getDialogTitle()}原因`,
            trigger: 'blur',
          },
        ],
      };
    },
  },
  watch: {
    dialogVisible(val) {
      if (!val) {
        this.$refs.appealFormRef && this.$refs.appealFormRef.resetFields();
      }
    },
  },
  mounted() {
    const { ZYH, id } = this.$route.query;
    this.MEDRECID = this.$route.path === '/whitelist-caseControl' || this.$route.path === '/whitelist-qualityResults' ? id : ZYH;
    this.setDialogWidth();
    window.addEventListener('resize', this.setDialogWidth);
    this.getstaffList();
  },
  beforeDestroy() {
    window.removeEventListener('resize', this.setDialogWidth);
  },

  methods: {
    setDialogWidth() {
      const width = window.innerWidth < 768 ? '80%' : '30%';
      this.dialogWidth = width;
    },
    getDialogTitle() {
      if (this.dialogType === 'pending') {
        return '解锁审核';
      }
      if (this.dialogType === 'appeal_in_yes') {
        return this.enteredFromPending ? '审核' : '通过';
      }
      if (this.dialogType === 'appeal_in_no') {
        return '驳回';
      }
      return '解锁审核';
    },

    getBaseInfo() {
      getBrry({ zyh: this.MEDRECID }).then(res => {
        if (res.code == 200) {
          this.baseInfo = res.data || {};
        }
      });
    },

    openAppealDialog(type, items) {
      this.row = items;
      this.dialogType = type;
      this.enteredFromPending = type === 'pending';
      this.currentId = items.id;
      this.dialogFormsLabel.zd_field_name = items.category;
      this.dialogFormsLabel.ts_desc = items.rule_name;

      this.appealForm = {
        doctor: localStorage.getItem('realname') || '',
        reason: '',
        unlock_applicant: items.unlock_applicant || '',
        unlock_reason: items.unlock_reason || '',
      };

      this.dialogVisible = true;
      this.getBaseInfo();
    },

    submitAppealHandle(type) {
      this.dialogType = type;
      this.appealForm.doctor = localStorage.getItem('realname') || '';
      this.appealForm.reason = '';
      this.$nextTick(() => {
        this.$refs.appealFormRef && this.$refs.appealFormRef.clearValidate();
      });
    },

    submitAppeal() {
      this.$refs.appealFormRef.validate(valid => {
        if (!valid) {
          return false;
        }

        const params = {
          id: this.currentId,
          status: this.dialogType === 'appeal_in_yes' ? 1 : 2,
          reason: this.appealForm.reason,
          auditor: this.appealForm.doctor,
        };
        this.$axios2.post('/case-quality/shizhong_quality_unlock_audit', params).then(res => {
          if (res.code == 200) {
            this.$message({
              message: `${this.getDialogTitle()}成功`,
              type: 'success',
            });
            this.dialogVisible = false;
            this.$emit('onUpdate');
          } else {
            this.$message({
              message: res.msg,
              type: 'error',
            });
          }
        });
      });
    },

    getstaffList() {
      getStaffListData().then(res => {
        const { data = [] } = res;
        this.staffList = data;
      });
    },
  },
};
</script>

<style lang="scss" scoped>
::v-deep .el-dialog__header {
  background-color: hsl(205.32deg 43.43% 49.22%);
}

::v-deep .el-dialog__close {
  color: #fff;
  border: 1px solid #fff;
  border-radius: 20px;
}

::v-deep .el-dialog__title {
  color: #fff;
}

::v-deep .el-descriptions-row {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
}

::v-deep .el-descriptions-item__label {
  font-weight: bold;
  font-size: 15px;
  white-space: nowrap;
}

::v-deep .el-descriptions-item__content {
  white-space: nowrap;
}

.dialog-footer-container {
  display: flex;
  justify-content: space-between;
  align-items: center;
  width: 100%;

  &.justify-end {
    justify-content: flex-end;
  }
}

.btn-right {
  display: flex;
  gap: 10px;
}
</style>
