<template>
  <div id="app">
    <router-view />
  </div>
</template>

<script>
import { getToken, setToken } from '@/utils/auth';
// import autofit from "autofit.js"

export default {
  name: 'App',
  data() {
    return {
      lastTime: null,
      currentTime: null,
      timeOut: 0.5 * 3600000,
      token: '',
      timer: '',
    };
  },
  mounted() {
    this.lastTime = new Date().getTime();
    this.token = getToken();
    // autofit.init({
    //   dh: 929,
    //   dw: 1920,
    //   el: "#app",
    //   resize: true
    // },
    //   false
    // )
  },
  // beforeDestroy() {
  //   autofit.off()
  // },
  methods: {
    handleClick() {
      setTimeout(() => {
        this.lastTime = new Date().getTime();
        if (this.$route.path.includes('/hospital') || this.$route.path.includes('/embedIndex')) {
          clearInterval(this.timer);
          this.timer = setInterval(this.isTimeOut, 1000);
        } else {
          clearInterval(this.timer);
          this.lastTime = new Date().getTime();
        }
      }, 1000);
    },
    isTimeOut() {
      this.currentTime = new Date().getTime();
      // 判断上次最后一次点击的时间和这次点击的时间间隔是否大于30分钟
      if (this.currentTime - this.lastTime > this.timeOut) {
        if (null != this.token) {
          // 是否是登录状态
          clearInterval(this.timer);
          this.$message.info('30分钟内无操作，请重新登录。');
          setToken('');
          sessionStorage.removeItem('route');
          this.$router.push({ path: `/login` });
        } else {
          this.lastTime = new Date().getTime();
        }
      }
    },
  },
};
</script>

<style>
* {
  font-size: 14px;
  margin: 0;
  padding: 0;
}
#app {
  position: relative;
}
.eInput {
  /* width:350px; */
  border: none !important;
}

/* 三种方法选择自己喜欢的一个即可 */
/* .el-input--prefix .el-input__inner{
  border: none;
} */
/* .el-input--small .el-input__inner {
    border: none;
} */
/* .textMsg {
  position: absolute;
  top: 80px;
  cursor: pointer;
  left: 3px;
} */

/* 重写input */
.row-bg {
  width: 100%;
}
.flexTable .el-input__inner {
  border-top: none !important;
  border-left: none !important;
  border-right: none !important;
  margin-bottom: 2px;
  border-radius: 0px !important;
}
.tableReach .el-input__inner {
  border-top: none !important;
  border-left: none !important;
  border-right: none !important;
  border-radius: 0px !important;
  border-bottom: 1px solid #f5f5f5 !important;
}
.tableReaches .el-input__inner {
  border-top: none !important;
  border-left: none !important;
  border-right: none !important;
  height: 30px !important;
  line-height: 30px !important;
  border-bottom: 0.5px solid #f5f5f5 !important;
  border-radius: 0px !important;
}
.tableReachCCC .el-input__inner {
  border-top: none !important;
  border-left: none !important;
  border-right: none !important;
  border-radius: 0px !important;
  border-bottom: 0.5px solid #f5f5f5 !important;
}
#hh .el-input__inner {
  border-top: 0px !important;
  border-left: 0px !important;
  border-right: 0px !important;
  border-radius: 0px !important;
  border-bottom: 1px solid #ccc !important;
}
.flexView {
  display: flex;
  width: 100%;
  align-items: center;
  justify-content: center;
}
.tabright .el-input__inner {
  border-top: none !important;
  border-left: none !important;
  border-right: 0.5px !important;
  border-bottom: none !important;
}
.tableReachInput .flexView div {
  width: 33%;
}
.refachInput {
  display: flex;
}
.tablex td {
  border-top: none !important;
  border-left: none !important;
  border-right: none !important;
}
.tablexs span {
  font-size: 12px !important;
}
.refachInput span {
  height: 40px;
  line-height: 40px;
  font-weight: bold;
  text-align: left;
  display: inline-block;
}
.tred td {
  border-top: none !important;
  border-left: none !important;
  border-right: none !important;
}
.refachInput .el-input__inner {
  border-top: none !important;
  border-left: none !important;
  border-right: none !important;
  margin-bottom: 2px;
  border-radius: 0px !important;
}

.msgBoxDetail span {
  margin-left: 10px;
}

/* 质控下拉append */
.zkSelect {
  display: inline-block;
}
.zkSelect .el-input-group__append {
  border-left: 0;
  width: 62px !important;
}
.flextab {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin: 10px 0px;
}
.dashboard-container {
  padding: 0;
  margin: 0 16px 16px 16px !important;
}
</style>
