<template>
  <div class="page-container" v-if="visible">
    <div class="feedback-box">
      <div class="feedback-header">
        <div class="feedback-title">反馈建议</div>
        <button class="feedback-close" @click="handleClose">
          <i class="fas fa-times"></i>
        </button>
      </div>

      <div class="feedback-group">
        <label class="feedback-label">反馈人</label>
        <input v-model="form.name" type="text" class="feedback-input" placeholder="请输入您的姓名" />
      </div>

      <div class="feedback-group">
        <label class="feedback-label">反馈科室</label>
        <input v-model="form.dept" type="text" class="feedback-input" placeholder="请输入您的科室" />
      </div>

      <div class="feedback-group">
        <label class="feedback-label">使用体验</label>
        <div class="feedback-radio-group">
          <label class="feedback-radio">
            <input v-model="form.experience" type="radio" value="满意" />
            <span>满意</span>
          </label>
          <label class="feedback-radio">
            <input v-model="form.experience" type="radio" value="一般" />
            <span>一般</span>
          </label>
          <label class="feedback-radio">
            <input v-model="form.experience" type="radio" value="不满意" />
            <span>不满意</span>
          </label>
        </div>
      </div>

      <div class="feedback-group">
        <label class="feedback-label">优化建议</label>
        <textarea v-model="form.suggestion" class="feedback-textarea" placeholder="请输入您的优化建议"></textarea>
      </div>

      <div class="feedback-footer">
        <button class="feedback-btn feedback-btn-cancel" @click="handleClose">取消</button>
        <button class="feedback-btn feedback-btn-submit" @click="handleSubmit">提交</button>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'FeedbackModal',
  props: {
    visible: {
      type: Boolean,
      default: false,
    },
  },
  data() {
    return {
      form: {
        name: '',
        dept: '',
        experience: '满意',
        suggestion: '',
      },
    };
  },
  methods: {
    handleClose() {
      this.$emit('close');
    },
    handleSubmit() {
      this.$emit('submit', {
        ...this.form,
      });
      // 提交后清空
      this.form = {
        name: '',
        dept: '',
        experience: '满意',
        suggestion: '',
      };
    },
  },
};
</script>

<style lang="scss" scoped>
.page-container {
  position: fixed;
  top: 0px;
  left: 0px;
  inset: 0px;
  background: rgba(0, 0, 0, 0.4);
  z-index: 300;
  display: flex;
  align-items: center;
  justify-content: center;
}
.feedback-box {
  width: 90%;
  background: #ffffff;
  border-radius: 12px;
  box-shadow: 0 6px 30px rgba(0, 0, 0, 0.15);
  z-index: 9999;
  overflow: hidden;
}

.feedback-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 14px 16px;
  border-bottom: 1px solid #e2e8f0;
}

.feedback-title {
  font-size: 16px;
  font-weight: bold;
  color: #1f2937;
}

.feedback-close {
  background: none;
  border: none;
  font-size: 16px;
  color: #6b7280;
  cursor: pointer;
}

.feedback-group {
  padding: 12px 16px;
}

.feedback-label {
  display: block;
  margin-bottom: 6px;
  font-size: 14px;
  color: #374151;
  font-weight: 500;
}

.feedback-input {
  width: 100%;
  box-sizing: border-box;
  height: 36px;
  padding: 0 10px;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  font-size: 14px;
  outline: none;
  transition: border-color 0.2s;

  &:focus {
    border-color: #1b64b0;
  }
}

.feedback-radio-group {
  display: flex;
  gap: 20px;
  margin-top: 4px;
}

.feedback-radio {
  display: flex;
  align-items: center;
  gap: 6px;
  cursor: pointer;
  font-size: 14px;
  color: #374151;
}

.feedback-textarea {
  width: 100%;
  box-sizing: border-box;
  min-height: 100px;
  padding: 10px;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  font-size: 14px;
  resize: vertical;
  outline: none;

  &:focus {
    border-color: #1b64b0;
  }
}

.feedback-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  padding: 12px 16px;
  border-top: 1px solid #e2e8f0;
}

.feedback-btn {
  padding: 6px 18px;
  border-radius: 6px;
  font-size: 14px;
  cursor: pointer;
  border: none;
  transition: all 0.2s;
}

.feedback-btn-cancel {
  background: #f3f4f6;
  color: #4b5563;
}

.feedback-btn-submit {
  background: #1b64b0;
  color: #fff;
}
</style>