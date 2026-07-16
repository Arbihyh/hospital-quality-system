<template>
  <div class="login-container" :style="{backgroundImage: isProd ? 'url('+ pageBg +')' : null}">
    <!-- <div class="logo">
      <img v-if="isProd" class="img1" src="../../assets/images/logo.png" />
      <img v-else src="../../assets/images/logo2.png" class="img2" />
    </div> -->
    <el-form ref="loginForm" :model="loginForm" :rules="loginRules" class="login-form" :class="{prod: isProd}" auto-complete="on" label-position="left">
      <div class="title-container">
        <h3 class="title">
          <!-- <span v-if="isProd" class="title">滨州医学院烟台附属医院<br></span>
          病案数据治理与服务平台 -->
          滨州医学院烟台附属医院
        </h3>
      </div>

      <el-form-item>
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
      </el-form-item>

      <el-button :loading="loading" type="primary" class="submit-btn" @click.native.prevent="handleLogin">登录</el-button>
      <div class="support">技术支持: 滨州医学院烟台附属医院</div>
    </el-form>
  </div>
</template>

<script>
import { validUsername } from '@/utils/validate';
import menu from "@/menu/menu.js"

export default {
  name: 'Login',
  data() {
    const validateUsername = (rule, value, callback) => {
      if (!validUsername(value)) {
        callback(new Error('请填写名称或账号！'));
      } else {
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
      pageBg: require('../../assets/images/loginbg_new.jpg'),
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
      preUrl: ''
    };
  },
  computed: {
    isProd() {
      return process.env.NODE_ENV === 'production'
    }
  },
  watch: {
    $route: {
      handler: function (route) {
        this.redirect = route.query && route.query.redirect;
        // this.preUrl = !!this.$route.query.preUrl ? this.$route.query.preUrl : sessionStorage.getItem('preUrl')
        this.preUrl = !!this.$route.query.preUrl ? this.$route.query.preUrl : undefined
      },
      immediate: true,
    },
  },
  created() {
    this.$nextTick(() => {
      // this.preUrl = !!this.$route.query.preUrl ? this.$route.query.preUrl : sessionStorage.getItem('preUrl')
      this.preUrl = !!this.$route.query.preUrl ? this.$route.query.preUrl : undefined
    })
  },
  methods: {
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
    handleLogin() {
      this.$refs.loginForm.validate(valid => {
        if (valid) {
          this.loading = true;
          this.$store.dispatch('user/login', this.loginForm).then(() => {
              this.loading = false;
              //获取权限菜单
              menu.getMenu().then( () =>{
                const routes = JSON.parse(sessionStorage.getItem('route'))
                let bSwitch = []
                for(let i=0; i<routes.length; i++) {
                  if(routes[i].path === '/embedIndex') {
                    bSwitch.push(2)
                  } else if(routes[i].path === '/hospital') {
                    bSwitch.push(1)
                  } else if(routes[i].path === '/reviewIndex') {
                    bSwitch.push(3)
                  } else {
                    bSwitch.push(0)
                  }

                }
                if (this.preUrl) {
                  if (bSwitch.includes(1) && this.preUrl === 'hospital') {
                    sessionStorage.setItem('preUrl', 'hospital')
                    this.$router.push({ path: '/hospital'});
                  } else if (bSwitch.includes(2) && this.preUrl === 'embedIndex') {
                    sessionStorage.setItem('preUrl', 'embedIndex')
                    this.$router.push({ path: '/embedIndex' });
                  } else if (bSwitch.includes(3) && this.preUrl === 'reviewIndex') {
                    sessionStorage.setItem('preUrl', 'reviewIndex')
                    this.$router.push({ path: '/reviewIndex' });
                  } else {
                    this.$message.error('没有页面权限，请联系管理员！')
                  }
                } else {
                  this.$router.push(this.redirect || '/');
                }
              }).catch((e) => {
                console.log(e)
                this.$message.error('获取导航菜单失败！');
              })
            }).catch(() => {
              this.loading = false;
            });
        } else {
          console.log('error submit!!');
          return false;
        }
      });
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
        caret-color: #454545;//改变输入框光标颜色,同时又不改变输入框里面的内容的颜色
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
.submit-btn {
  width: 100%;
  margin-bottom: 30px;
  background: rgb(1, 78, 133);
}
.support {
  color: rgb(1, 78, 133);
  font-size: 14px;
  text-align: center;
  font-weight: 500;
  margin-bottom: 30px;
}
$bg: #fff;
$dark_gray: #889aa4;
$light_gray: #000;
::v-deep .el-form-item__content {
  line-height: 1;
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
    background: #fff;
    position: absolute;
    width: 400px;
    top: 50%;
    right: 50%;
    transform: translate(50%, -50%);
    max-width: 100%;
    padding: 35px 35px 0;
    border-radius: 12px;
    &.prod {
      top: 50%;
      right: 100px;
      width: 360px;
      margin-top: -160px;
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
}
</style>
