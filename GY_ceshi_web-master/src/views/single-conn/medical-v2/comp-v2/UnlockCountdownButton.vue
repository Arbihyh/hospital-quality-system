<template>
  <div class="unlock-countdown">
    <el-dialog
      title="问题解锁"
      :visible.sync="modalVisible"
      :width="dialogWidth"
      :close-on-click-modal="false"
      append-to-body
      @closed="onDialogClosed"
      @opened="onDialogOpened"
    >
      <div class="unlock-dialog__desc">请填写解锁原因及解锁人，确认后该问题将临时解锁{{ durationMinutes }}分钟。</div>
      <el-form ref="unlockForm" :model="form" :rules="rules" class="unlock-dialog__form">
        <el-form-item label="解锁原因" prop="unlock_reason">
          <el-input
            ref="reasonInput"
            v-model="form.unlock_reason"
            type="textarea"
            :rows="3"
            placeholder="请输入解锁原因"
          />
        </el-form-item>
        <el-form-item label="解锁人" prop="unlock_applicant">
          <el-input v-model="form.unlock_applicant" placeholder="请输入解锁人" />
        </el-form-item>
      </el-form>
      <span slot="footer" class="unlock-dialog__footer">
        <el-button @click="closeModal">取消</el-button>
        <el-button type="primary" @click="confirmUnlock">确认解锁</el-button>
      </span>
    </el-dialog>

    <UnlockStatusButton
      :rowData="rowData"
      :unlocked="isUnlocked"
      :left-seconds="leftSeconds"
      @click="openModal"
    />
  </div>
</template>

<script>
import UnlockStatusButton from './UnlockStatusButton.vue';

export default {
  name: 'UnlockCountdownButton',

  components: {
    UnlockStatusButton,
  },

  props: {
    duration: {
      type: Number,
      default: 60 * 60,
    },
    value: {
      type: Boolean,
      default: false,
    },
    remainingSeconds: {
      type: Number,
      default: 0,
    },
    rowData: {
      type: Object,
      default: () => ({
        unlock_status: '未解锁',
        rule_id: 0,
        expire_time: '',
      }),
    },
  },

  data() {
    return {
      modalVisible: false,
      isUnlocked: this.value,
      dialogWidth: '30%',
      leftSeconds: 0,
      form: {
        unlock_reason: '',
        unlock_applicant: '',
      },
      rules: {
        unlock_reason: [{ required: true, message: '请输入解锁原因', trigger: 'blur' }],
        unlock_applicant: [{ required: true, message: '请输入解锁人', trigger: 'blur' }],
      },
      timer: null,
    };
  },

  computed: {
    durationMinutes() {
      return Math.floor(this.duration / 60);
    },
  },

  watch: {
    value(val) {
      if (val && !this.isUnlocked) {
        this.startCountdown('', '');
      } else if (!val && this.isUnlocked) {
        this.resetLock();
      }
    },

    remainingSeconds(val) {
      if (val > 0 && this.isUnlocked) {
        this.restartTimer(val);
      }
    },

    'rowData.unlock_status'(status) {
      if (status === '已解锁' && !this.isUnlocked) {
        this.syncUnlockedFromRow();
      } else if (status === '未解锁' && this.isUnlocked) {
        this.resetLock();
      }
    },

    'rowData.expire_time'(expireTime) {
      if (this.isUnlocked && expireTime) {
        const seconds = this.parseExpireTimeToSeconds(expireTime);
        if (seconds > 0) {
          this.restartTimer(seconds);
        } else {
          this.onLockExpired();
        }
      }
    },
  },

  mounted() {
    this.setDialogWidth();
    window.addEventListener('resize', this.setDialogWidth);
    if (this.rowData.unlock_status === '已解锁') {
      this.syncUnlockedFromRow();
    } else if (this.value && this.remainingSeconds > 0) {
      this.isUnlocked = true;
      this.startTimer(this.remainingSeconds);
    }
  },
  

  beforeDestroy() {
    this.clearTimer();
    window.removeEventListener('resize', this.setDialogWidth);
  },


  methods: {
    setDialogWidth() {
      const width = window.innerWidth < 768 ? '80%' : '30%';
      this.dialogWidth = width;
    },
    openModal() {
      this.modalVisible = true;
    },

    closeModal() {
      this.modalVisible = false;
    },

    onDialogOpened() {
      this.$nextTick(() => {
        if (this.$refs.reasonInput) {
          this.$refs.reasonInput.focus();
        }
      });
    },

    onDialogClosed() {
      if (this.$refs.unlockForm) {
        this.$refs.unlockForm.resetFields();
      }
    },

    confirmUnlock() {
      this.$refs.unlockForm.validate(valid => {
        if (!valid) return;

        const unlock_reason = this.form.unlock_reason.trim();
        const unlock_applicant = this.form.unlock_applicant.trim();
        this.$axios2
          .post('/case-quality/shizhong_quality_rule_unlock', {
            zyh: this.$route.query.id,
            rule_id: this.rowData.rule_id,
            unlock_applicant: unlock_applicant,
            unlock_reason: unlock_reason,
          })
          .then(res => {
            if (res.code === 200) {
              this.closeModal();
              this.startCountdown(unlock_reason, unlock_applicant);
              this.$emit('unlocked', { unlock_reason, unlock_applicant, duration: this.duration });
              this.$emit('refresh');
            }
          })
          .catch(err => {
            console.error('获取数据失败', err);
          });
      });
    },

    startCountdown(unlock_reason, unlock_applicant) {
      this.isUnlocked = true;
      this.leftSeconds = this.duration;
      this.startTimer(this.duration);

      this.$emit('input', true);
      this.$emit('change', {
        unlocked: true,
        unlock_reason,
        unlock_applicant,
        leftSeconds: this.leftSeconds,
      });
    },

    startTimer(seconds) {
      this.clearTimer();
      this.leftSeconds = seconds;

      this.timer = setInterval(() => {
        this.leftSeconds -= 1;

        this.$emit('tick', this.leftSeconds);

        if (this.leftSeconds <= 0) {
          this.onLockExpired();
        }
      }, 1000);
    },

    restartTimer(seconds) {
      this.clearTimer();
      this.startTimer(seconds);
    },

    onLockExpired() {
      this.clearTimer();
      this.resetLock();
      this.$emit('locked');
      this.$emit('change', { unlocked: false, leftSeconds: 0 });
      this.$emit('refresh');
    },

    getRemainSeconds() {
      const entity = this.rowData || {};
      if (entity.expire_time) {
        return this.parseExpireTimeToSeconds(entity.expire_time);
      }
      if (this.remainingSeconds > 0) return this.remainingSeconds;
      return this.duration;
    },

    parseExpireTimeToSeconds(expireTime) {
      if (!expireTime) return 0;
      const expireMs = new Date(String(expireTime).replace(/-/g, '/')).getTime();
      if (Number.isNaN(expireMs)) return 0;
      return Math.max(0, Math.floor((expireMs - Date.now()) / 1000));
    },

    syncUnlockedFromRow() {
      const serverSeconds = this.getRemainSeconds();
      const hasExpireTime = !!(this.rowData && this.rowData.expire_time);

      this.isUnlocked = true;

      if (this.timer && !hasExpireTime) {
        this.$emit('input', true);
        return;
      }

      if (serverSeconds > 0) {
        this.leftSeconds = serverSeconds;
        this.startTimer(serverSeconds);
      } else if (hasExpireTime) {
        this.onLockExpired();
        return;
      } else {
        this.leftSeconds = this.duration;
        this.startTimer(this.duration);
      }
      this.$emit('input', true);
    },

    resetLock() {
      this.isUnlocked = false;
      this.leftSeconds = this.duration;
      this.form.unlock_reason = '';
      this.form.unlock_applicant = '';
      this.$emit('input', false);
    },

    clearTimer() {
      if (this.timer) {
        clearInterval(this.timer);
        this.timer = null;
      }
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
.unlock-countdown {
  display: inline-block;
}

.unlock-dialog {
  &__desc {
    font-size: 13px;
    line-height: 1.6;
    color: #4b5563;
    margin-bottom: 4px;
  }

  &__form {
    ::v-deep .el-form-item__label {
      font-size: 13px;
      font-weight: 700;
      color: #4b5563;
      padding-bottom: 6px;
      line-height: 1;
    }

    ::v-deep .el-textarea__inner,
    ::v-deep .el-input__inner {
      font-size: 13px;
    }
  }

  &__footer {
    display: inline-flex;
    gap: 8px;
  }
}
</style>
