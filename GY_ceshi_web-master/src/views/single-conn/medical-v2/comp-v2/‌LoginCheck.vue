<!-- 病历生成 - 登录验证页面 -->
<template>
  <div class="login-wrap">
    <div class="login-icon">
      <i class="fas fa-user-shield"></i>
    </div>
    <div class="login-title">登录验证</div>
    <div class="login-subtitle">请输入工号验证身份，开始生成病历文书</div>

    <div class="login-form">
      <div class="form-group">
        <div class="form-label">
          <span class="required">*</span>
          工号
        </div>
        <input v-model="jobId" type="text" class="form-input" placeholder="请输入您的工号" autocomplete="off" @keyup.enter="handleLogin" :class="{ error: hasError }" />
        <div class="form-error" :class="{ show: hasError }">
          <i class="fas fa-exclamation-circle"></i>
          <span>{{ errorMsg }}</span>
        </div>
      </div>

      <button class="btn-lg btn-lg-primary login-btn" @click="handleLogin">登录验证</button>
    </div>
  </div>
</template>

<script>
export default {
  name: 'LoginCheck',
  data() {
    return {
      jobId: '',
      hasError: false,
      errorMsg: '',
    };
  },
  methods: {
    handleLogin() {
      const staff_code = this.jobId.trim();
      this.clearError();

      if (!staff_code) {
        this.setError('请输入工号');
        return;
      }

      this.$axios2
        .post('/big_model/staff_login', { staff_code: staff_code })
        .then(res => {
          console.log('登录成功：', res);
          localStorage.setItem('staffLoginInfo', JSON.stringify(res.data));
          this.$emit('login-success', {
            staff_code: res.data.staff_code,
            staff_name: res.data.staff_name,
            department: res.data.department,
          });
        })
        .catch(e => console.error('获取失败：', e));
    },

    setError(msg) {
      this.hasError = true;
      this.errorMsg = msg;
    },

    clearError() {
      this.hasError = false;
      this.errorMsg = '';
    },
  },
};
</script>

<style scoped>
.page-title {
  position: absolute;
  top: 0px;
  left: 5px;
  font-size: 18px;
  font-weight: 700;
  color: #2C67F6;
  letter-spacing: 0.5px;
  z-index: 10;
}

.login-wrap {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 40px 20px 20px;
  position: relative; 
}
.login-icon {
  width: 64px;
  height: 64px;
  border-radius: 20px;
  background: linear-gradient(135deg, #2f6bff, #1e56cc);
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 16px;
  box-shadow: 0 8px 24px rgba(47, 107, 255, 0.25);
}
.login-icon i {
  font-size: 28px;
  color: #fff;
}
.login-title {
  font-size: 20px;
  font-weight: 700;
  color: #1f2937;
  margin-bottom: 6px;
}
.login-subtitle {
  font-size: 13px;
  color: #7b8794;
  margin-bottom: 28px;
}
.login-form {
  width: 100%;
  max-width: 320px;
}

.form-group {
  margin-bottom: 16px;
}
.form-label {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: 14px;
  font-weight: 600;
  color: #4b5563;
  margin-bottom: 8px;
}
.form-label .required {
  color: #f04438;
  font-size: 14px;
}
.form-input {
  width: 100%;
  height: 40px;
  padding: 0 14px;
  border: 1px solid #d8dee7;
  border-radius: 8px;
  background: #fff;
  font-size: 14px;
  color: #7b8794;
  outline: none;
  transition: all 0.2s ease;
}
.form-input::placeholder {
  color: #7b8794;
  opacity: 1;
}
.form-input:focus {
  border-color: #bfd3ff;
  box-shadow: 0 0 0 3px rgba(27, 100, 176, 0.12);
  color: #1f2937;
}
.form-input.error {
  border-color: #f04438;
  box-shadow: 0 0 0 3px rgba(240, 68, 56, 0.1);
}
.form-error {
  font-size: 12px;
  color: #f04438;
  margin-top: 6px;
  display: none;
  align-items: center;
  gap: 4px;
}
.form-error.show {
  display: flex;
}

.btn-lg {
  min-width: 120px;
  height: 36px;
  padding: 0 20px;
  border-radius: 18px;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  outline: none;
  border: 1px solid transparent;
  transition: all 0.2s ease;
}
.btn-lg-primary {
  background: linear-gradient(135deg, #1b64b0, #155290);
  color: #fff;
  border-color: #1b64b0;
}
.btn-lg-primary:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(27, 100, 176, 0.3);
}
.login-btn {
  width: 100%;
  height: 42px;
  border-radius: 21px;
  font-size: 14px;
  margin-top: 8px;
}
</style>