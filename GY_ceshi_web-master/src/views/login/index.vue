<template>
  <div class="login-container">
    <!--    2025-01-13 gyf -->
    <!--    <div class="logo">-->
    <!--      <img v-if="isProd" class="img1" src="../../assets/images/logo.png" />-->
    <!--      <img v-else :src="login_logo" class="img2" />-->
    <!--    </div>-->
    <div class="right-panel-active container" id="container">
      <div class="overlay-container">
        <div class="overlay">
          <div class="overlay-panel overlay-left">
            <h1>欢迎使用</h1>
            <p>病案数据治理与服务平台</p>
            <img src="../../assets/images/login-sign-bg.png" alt="" class="overlay-left-img" />
          </div>
        </div>
      </div>

      <!-- 登录 -->
      <div class="form-container sign-up-container">
        <p class="sign-title">病案数据治理与服务平台</p>
        <p class="sign-title-1">登录</p>
        <el-form ref="loginForm" :model="loginForm" :rules="loginRules" class="login-form" :class="{ prod: isProd }" auto-complete="on" label-position="left">
          <el-form-item prop="username">
            <span class="svg-container">
              <svg-icon icon-class="user" />
            </span>
            <el-input ref="username" v-model="loginForm.username" placeholder="请输入名称或账号" name="username" type="text" tabindex="1" auto-complete="on" />
          </el-form-item>

          <el-form-item prop="password">
            <span class="svg-container">
              <svg-icon icon-class="password" />
            </span>
            <el-input
              :key="passwordType"
              ref="password"
              v-model="loginForm.password"
              :type="passwordType"
              placeholder="请输入密码"
              name="password"
              tabindex="2"
              auto-complete="on"
              @keyup.enter.native="handleLogin"
            />
            <span class="show-pwd" @click="showPwd">
              <svg-icon :icon-class="passwordType === 'password' ? 'eye' : 'eye-open'" />
            </span>
            <span class="forgot-password" @click="forgotPasswordClick">忘记密码</span>
          </el-form-item>
          <div class="checkbox-box">
            <el-checkbox v-model="checked">记住密码</el-checkbox>
          </div>
          <el-button :loading="loading" type="primary" class="submit-btn" @click.native.prevent="handleLogin">登录</el-button>
        </el-form>
      </div>
      <!-- 登录 -->
      <ForgotPassword ref="forgotPasswordRef"></ForgotPassword>
    </div>
  </div>
</template>

<script>
import { validUsername } from '@/utils/validate';
import menu from '@/menu/menu.js';
import ForgotPassword from './forgotPassword.vue';

export default {
  name: 'Login',
  components: { ForgotPassword },
  data() {
    const validateUsername = (rule, value, callback) => {
      if (value.length == 0) {
        callback(new Error('名称或账号不能为空！'));
      }
      // else if (!validUsername(value)) {
      //   callback(new Error('请填写名称或账号！'));
      // }
      {
        callback();
      }
    };
    const validatePassword = (rule, value, callback) => {
      if (value.length == 0) {
        callback(new Error('密码不能为空！'));
      } else {
        callback();
      }
    };
    return {
      login_bg: require('../../assets/images/loginbj.jpg'),
      login_logo: require('../../assets/images/logo2.png'),
      loginForm: {
        username: '',
        password: '',
      },
      loginRules: {
        username: [{ required: true, trigger: 'blur', validator: validateUsername }],
        password: [{ required: true, trigger: 'blur', validator: validatePassword }],
      },
      loading: false,
      passwordType: 'password',
      redirect: undefined,
      preUrl: '',
      checked: false,
    };
  },
  computed: {
    isProd() {
      return process.env.NODE_ENV === 'production';
    },
  },
  watch: {
    $route: {
      handler: function (route) {
        this.redirect = route.query && route.query.redirect;
        // this.preUrl = !!this.$route.query.preUrl ? this.$route.query.preUrl : sessionStorage.getItem('preUrl')
        this.preUrl = !!this.$route.query.preUrl ? this.$route.query.preUrl : undefined;
      },
      immediate: true,
    },
  },
  created() {
    this.$nextTick(() => {
      // this.preUrl = !!this.$route.query.preUrl ? this.$route.query.preUrl : sessionStorage.getItem('preUrl')
      this.preUrl = !!this.$route.query.preUrl ? this.$route.query.preUrl : undefined;
      if (this.$route.query.code) {
        this.handleHisLogin();
      }
    });
  },
  methods: {
    forgotPasswordClick() {
      this.$refs.forgotPasswordRef.init({});
    },
    showPwd() {
      if (this.passwordType === 'password') {
        this.passwordType = '';
      } else {
        this.passwordType = 'password';
      }
      this.$nextTick(() => {
        this.$refs.password.focus();
      });
    },
    handleHisLogin() {
      this.$store
        .dispatch('user/hisLogin', {
          name: this.$route.query.code,
        })
        .then(() => {
          this.handleLoginNext();
        });
    },
    handleLogin() {
      this.$refs.loginForm.validate(valid => {
        if (valid) {
          this.loading = true;
          this.$store
            .dispatch('user/login', this.loginForm)
            .then(() => {
              this.loading = false;
              this.handleLoginNext();
            })
            .catch(() => {
              this.loading = false;
            });
        } else {
          console.log('error submit!!');
          return false;
        }
      });
    },

    handleLoginNext() {
      console.log('==========', this.$route.query.code);
      menu
        .getMenu()
        .then(e => {
          // const routes = JSON.parse(sessionStorage.getItem('route'))
          const routes = this.$store.state.user.menu;
          let bSwitch = [];
          for (let i = 0; i < routes.length; i++) {
            if (routes[i].path === '/embedIndex') {
              bSwitch.push(2);
            } else if (routes[i].path === '/hospital') {
              bSwitch.push(1);
            } else if (routes[i].path === '/reviewIndex') {
              bSwitch.push(3);
            } else {
              bSwitch.push(0);
            }
          }

          if (this.preUrl) {
            if (this.preUrl === 'whitelist-search-specialty') {
              sessionStorage.setItem('preUrl', 'whitelist-search-specialty');
              this.$router.push({ path: '/whitelist-search-specialty', query: { from: 'kyts' } });
              return;
            }

            if (bSwitch.includes(1) && this.preUrl === 'hospital') {
              sessionStorage.setItem('preUrl', 'hospital');
              this.$router.push({ path: '/hospital' });
            } else if (bSwitch.includes(2) && this.preUrl === 'embedIndex') {
              sessionStorage.setItem('preUrl', 'embedIndex');
              this.$router.push({ path: '/embedIndex' });
            } else if (bSwitch.includes(3) && this.preUrl === 'reviewIndex') {
              sessionStorage.setItem('preUrl', 'reviewIndex');
              this.$router.push({ path: '/reviewIndex' });
            } else {
              this.$message.error('没有页面权限，请联系管理员！');
            }
          } else {
            console.log('=====routes=====', routes);
            // this.$router.push(this.redirect || '/allcase/index');
            this.toHomePage(routes);
          }
        })
        .catch(e => {
          console.log(e);
          this.$message.error('获取导航菜单失败！');
        });
    },

    toHomePage(routes) {
      if (!Array.isArray(routes) || routes.length === 0) {
        console.warn('路由列表为空或格式不正确，无法跳转首页');
        this.$router.push(this.redirect || '/');
        return;
      }

      const firstRoute = routes[0];
      let targetPath = '';
      if (firstRoute.children && Array.isArray(firstRoute.children) && firstRoute.children.length > 0 && firstRoute.children[0].path && firstRoute.children[0].path.trim() !== '') {
        targetPath = firstRoute.children[0].path;
      } else {
        targetPath = firstRoute.path || '/';
      }

      this.$router.push(this.redirect || targetPath);
    },
  },
};
</script>

<style lang="scss">
/* 修复input 背景不协调 和光标变色 */
/* Detail see https://github.com/PanJiaChen/vue-element-admin/pull/927 */
$bg: #ffffff;
$light_gray: #000;
$cursor: #000;

@supports (-webkit-mask: none) and (not (cater-color: $cursor)) {
  .login-container .el-input input {
    color: $cursor;
  }
}
/* reset element-ui css */
.login-container {
  position: relative;
  .el-input {
    display: inline-block;
    width: 85%;

    input {
      background: transparent;
      border: 0px;
      -webkit-appearance: none;
      border-radius: 0px;
      padding: 12px 5px 12px 15px;
      color: $light_gray;
      caret-color: $cursor;

      &:-webkit-autofill {
        transition: background-color 50000s ease-in-out 0s;
        -webkit-text-fill-color: #454545; //记住密码的颜色
        caret-color: #454545; //改变输入框光标颜色,同时又不改变输入框里面的内容的颜色
      }
    }
  }

  .el-form-item {
    border: 1px solid rgba(255, 255, 255, 0.1);
    background: rgba(0, 0, 0, 0.1);
    border-radius: 5px;
    color: #454545;
  }
}
</style>

<style lang="scss" scoped>
$bg: #fff;
$dark_gray: #889aa4;
$light_gray: #000;
::v-deep .el-form-item__content {
  line-height: 1;
}
.submit-btn {
  width: 100%;
  margin-top: 30px;
  background: rgb(1, 78, 133);
}
.support {
  color: rgb(1, 78, 133);
  font-size: 14px;
  text-align: center;
  font-weight: 500;
  margin-bottom: 30px;
}
.logo {
  .img1 {
    padding-top: 40px;
    padding-left: 40px;
    width: 400px;
  }
  .img2 {
    width: 240px;
    padding-top: 20px;
    padding-left: 20px;
  }
}
.login-container {
  min-height: 100%;
  width: 100%;
  background-repeat: no-repeat;
  background-size: 100% 100%;
  overflow: hidden;

  .login-form {
    width: 100%;
    max-width: 100%;
    margin-top: 40px;
    &.prod {
      top: 200px;
      right: 100px;
      width: 360px;
      transform: none;
    }
  }

  .tips {
    font-size: 14px;
    color: #fff;
    margin-bottom: 10px;

    span {
      &:first-of-type {
        margin-right: 16px;
      }
    }
  }

  .svg-container {
    padding: 6px 5px 6px 15px;
    color: $dark_gray;
    vertical-align: middle;
    width: 30px;
    display: inline-block;
  }

  .title-container {
    position: relative;

    .title {
      font-size: 18px;
      color: $light_gray;
      margin: 0px auto 20px auto;
      font-weight: bold;
      text-align: center;
    }
  }

  .show-pwd {
    position: absolute;
    right: 10px;
    top: 14px;
    font-size: 16px;
    color: $dark_gray;
    cursor: pointer;
    user-select: none;
  }
  .forgot-password {
    position: absolute;
    right: 10px;
    top: 64px;
    font-size: 14px;
    color: rgba(52, 124, 175, 1);
    cursor: pointer;
    user-select: none;
  }
}
// ============  新界面样式 ==========
.container {
  background-color: #fff;
  border-radius: 10px;
  box-shadow: 0 14px 28px rgba(0, 0, 0, 0.25), 0 10px 10px rgba(0, 0, 0, 0.22);
  position: relative;
  overflow: hidden;
  width: 860px;
  max-width: 100%;
  min-height: 528px;
  position: fixed;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  display: flex;
}
.overlay-container {
  width: 58%;
  transition: transform 0.6s ease-in-out;
}
.overlay {
  background: #4fb3ff;
  background: linear-gradient(to right, #3eacff, #914dff);
  background-repeat: no-repeat;
  background-size: cover;
  background-position: 0 0;
  color: #ffffff;
  height: 100%;
  width: 100%;
}
.overlay-left {
  padding-top: 50px;
  padding-left: 60px;
}
.overlay-left h1 {
  margin: 0;
}
.overlay-left p {
  padding-top: 10px;
  font-size: 18px;
}

.sign-up-container {
  flex: 1;
  padding-top: 50px;
  padding-left: 20px;
  padding-right: 20px;
}
.sign-title {
  font-size: 18px;
  font-weight: bold;
  color: rgb(58, 98, 215);
  text-align: center;
}
.sign-title-1 {
  font-size: 15px;
  font-weight: bold;
  padding-top: 20px;
}
.overlay-left-img {
  margin: 50px auto 0;
  width: 240px;
  display: block;
}
</style>
