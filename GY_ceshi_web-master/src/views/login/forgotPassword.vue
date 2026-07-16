<!-- 忘记密码 -->
<template>
  <div>
    <DraggableDialog :dialogKey="2">
      <el-dialog :visible.sync="forgotPwdVisible" width="460px" title="" :modal="true" :show-close="false" append-to-body border="0">
        <div slot="title" class="dialog-header">
          <div class="dialog-title">验证账号信息</div>
          <el-image class="close-btn" :src="CloseBg" fit="contain" @click="handleClose(1)"></el-image>
        </div>
        <div class="forgot-password-dialog">
          <div class="dialog-form-content">
            <el-form ref="formRef" :model="form" :rules="formRules" class="form-box" label-width="0">
              <el-form-item prop="account" class="form-item" label-width="100" label="账号：">
                <el-input v-model="form.account" class="form-input" placeholder="请输入医师账号"></el-input>
              </el-form-item>

              <el-form-item prop="name" class="form-item" style="margin-top: 40px" label-width="100" label="姓名：">
                <el-input v-model="form.name" class="form-input" placeholder="请输入医师姓名"></el-input>
              </el-form-item>
            </el-form>
          </div>

          <div class="btn-container">
            <button class="reset-btn" @click="handleResetPwd">重置密码</button>
          </div>
        </div>
      </el-dialog>
    </DraggableDialog>

    <DraggableDialog :dialogKey="1">
      <el-dialog :visible.sync="resetPwdVisible" width="460px" title="" :modal="true" :show-close="false" append-to-body border="0">
        <div slot="title" class="dialog-header">
          <div class="dialog-title">重置密码</div>
          <el-image class="close-btn" :src="CloseBg" fit="contain" @click="handleClose(2)"></el-image>
        </div>
        <div class="forgot-password-dialog">
          <div class="dialog-form-content">
            <el-form ref="newFormRef" :model="newForm" :rules="newFormRules" class="form-box" label-width="0">
              <el-form-item prop="newPwd" class="form-item" label-width="100" label="新密码：">
                <div class="pwd-input-bx">
                  <el-input ref="password" v-model="newForm.newPwd" class="form-input" placeholder="请输入新密码" :type="passwordType"></el-input>
                  <span class="show-pwd" @click="showPwd">
                    <svg-icon :icon-class="passwordType === 'password' ? 'eye' : 'eye-open'" />
                  </span>
                </div>
              </el-form-item>
            </el-form>
          </div>

          <div class="btn-container">
            <DialogFooterBtn @cancel="handleClose(2)" @confirm="savePwd" />
          </div>
        </div>
      </el-dialog>
    </DraggableDialog>
  </div>
</template>

<script>
import CloseBg from '@/assets/images/close.png';
import DraggableDialog from '@/components/draggable-dialog';
import DialogFooterBtn from '@/components/DialogFooterBtn';

export default {
  name: 'forgotPassword',
  components: { DraggableDialog, DialogFooterBtn },
  data() {
    const validatePass = (rule, value, callback) => {
      if (!value) {
        callback(new Error('密码长度至少8位,必须包含大写字母,小写字母以及数字'));
      } else {
        // ^(?=.*[a-z]) 必须包含小写字母
        // (?=.*[A-Z]) 必须包含大写字母
        // (?=.*\d)    必须包含数字
        // .{8,}$      任意字符（除换行），长度至少8位
        const reg = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/;
        if (reg.test(value)) {
          callback();
        } else {
          callback(new Error('密码长度至少8位,必须包含大写字母,小写字母以及数字'));
        }
      }
    };
    return {
      CloseBg,
      forgotPwdVisible: false,
      resetPwdVisible: false,
      passwordType: 'password',
      accountName: '',
      form: {
        account: '',
        name: '',
      },
      newForm: {
        newPwd: '',
      },
      formRules: {
        account: [{ required: true, message: '为完成信息核验，请输入原登录账号', trigger: 'blur' }],
        name: [{ required: true, message: '为完成信息核验，请输入原登录账号关联的医师姓名', trigger: 'blur' }],
      },
      newFormRules: {
        newPwd: [
          { required: true, message: '密码长度至少8位,必须包含大写字母,小写字母以及数字', trigger: 'blur' },
          { validator: validatePass, trigger: 'blur' },
        ],
      },
    };
  },
  methods: {
    init(row) {
      console.log('弹窗初始化入参', row);
      this.forgotPwdVisible = true;
    },

    showPwd() {
      this.passwordType = this.passwordType === 'password' ? 'text' : 'password';
      this.$nextTick(() => {
        this.$refs.password?.focus();
      });
    },

    handleClose(type) {
      if (type == 1) {
        this.forgotPwdVisible = false;
        this.form = { account: '', name: '' };
        this.$refs.formRef?.clearValidate();
      } else {
        this.resetPwdVisible = false;
        this.newForm = { newPwd: '' };
        this.$refs.newFormRef?.clearValidate();
      }
    },

    async handleResetPwd() {
      this.$refs.formRef.validate(async valid => {
        if (!valid) {
          this.$message.warning('请完善账号和姓名信息后重试');
          return false;
        }
        try {
          const params = {
            name: this.form.account,
            realname: this.form.name,
          };

          const res = await this.$axios.post('/verifyForReset', params);
          if (res?.code === 200) {
            this.$message.success('账号信息验证通过');
            this.accountName = this.form.account;
            this.handleClose(1);
            this.resetPwdVisible = true;
          } else {
            // this.$message.error(res?.msg || '账号或姓名验证失败，请核对信息后重试');
          }
        } catch (err) {
          console.error('账号验证接口请求异常：', err);
          // this.$message.error('网络异常，请稍后重试');
        }
      });
    },

    async savePwd() {
      this.$refs.newFormRef.validate(async valid => {
        if (!valid) {
          this.$message.warning('请输入符合规则的新密码');
          return false;
        }
        try {
          const params = {
            name: this.accountName,
            password: this.newForm.newPwd,
          };

          const res = await this.$axios.post('/resetPassword', params);
          if (res.code == 200) {
            this.$message.success('密码重置成功');
            this.handleClose(2);
            this.$emit('savePwd');
          } else {
            // this.$message.error(res?.msg || '密码重置失败，请稍后重试');
          }
        } catch (err) {
          console.error('重置密码接口请求异常：', err);
          // this.$message.error('网络异常，密码重置失败');
        }
      });
    },
  },
};
</script>

<style lang="scss" scoped>
::v-deep .el-dialog {
  width: 460px !important;
  margin: 0 auto;
  padding: 0;
  background: none;
}
::v-deep .el-dialog__header {
  padding: 0 !important;
}
::v-deep .el-dialog__body {
  padding: 0 !important;
}

::v-deep .el-form-item__error {
  line-height: 17px !important;
  color: rgba(189, 49, 36, 1) !important;
  font-size: 12px !important;
  text-align: left !important;
  font-family: PingFangSC-bold !important;
  padding-top: 4px !important;
  margin-left: 0 !important;
}

.forgot-password-dialog {
  width: 460px;
  height: 350px;
  background: #ffffff;
  border: 1px solid rgba(27, 100, 176, 1);
  border-radius: 0px 0px 8px 8px;
  overflow: hidden;
  box-sizing: border-box;
}

.dialog-header {
  width: 460px;
  height: 57px;
  line-height: 20px;
  border-radius: 8px 8px 0px 0px;
  background-color: rgba(27, 100, 176, 1);
  color: rgba(16, 16, 16, 1);
  font-size: 14px;
  text-align: center;
  font-family: PingFangSC-regular;
  border: 1px solid rgba(27, 100, 176, 1);
  box-sizing: border-box;
  padding: 0 16px;
  display: flex;
  justify-content: flex-start;
  align-items: center;
  position: relative;
  cursor: move;
  user-select: none;
}

.dialog-title {
  color: #ffffff;
  font-size: 16px;
  font-weight: 500;
  margin-left: 20px;
}

.close-btn {
  width: 24px;
  height: 24px;
  cursor: pointer;
  position: absolute;
  right: 16px;
  top: 50%;
  transform: translateY(-50%);
}

.dialog-form-content {
  padding: 40px 30px;
  box-sizing: border-box;
}
.form-box {
  width: 100%;
  height: 150px;
}
.form-item {
  width: 380px;
  display: flex;
  margin-top: 20px;
}
.form-input {
  width: 300px;
  height: 42px;
  border-radius: 4px;
  border: 1px solid #e5e5e5;
  font-size: 14px;
  box-sizing: border-box;
}

.btn-container {
  width: 100%;
  padding: 0 30px 30px;
  display: flex;
  justify-content: center;
  align-items: center;
  box-sizing: border-box;
}

.reset-btn {
  width: 151px;
  height: 52px;
  line-height: 23px;
  border-radius: 4px;
  background-color: rgba(233, 157, 66, 1);
  color: rgba(255, 255, 255, 1);
  font-size: 16px;
  text-align: center;
  font-family: PingFangSC-regular;
  border: none;
  outline: none;
  cursor: pointer;
  transition: all 0.2s ease;
  &:hover {
    background-color: rgba(221, 145, 54, 1);
  }
}

// 密码框小眼睛样式
.pwd-input-box {
  position: relative;
  width: 300px;
}
.show-pwd {
  position: absolute;
  right: 12px;
  top: 50%;
  transform: translateY(-50%);
  font-size: 18px;
  color: #999;
  cursor: pointer;
  z-index: 10;
  &:hover {
    color: #666;
  }
}
</style>