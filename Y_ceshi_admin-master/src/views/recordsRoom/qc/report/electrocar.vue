<template>
  <!-- 心电图诊断报告单 -->
  <div id="electrocarA">
    <div v-for="(dataObj, index) in dataObjArr" :key="index" class="elect-cont">
      <div class="cont-title-description">滨州医学院烟台附属医院心电图诊断报告</div>
      <div class="examType">{{ dataObj.ExamType }}</div>
      <div class="elect-cont-header" />

      <div class="elect-cont-time elect-cont-mian-border">
        <div class="elect-cont-time-name elect-cont-time-title">检查日期：</div>
        <div class="elect-cont-time-item">{{ dataObj.JYSJ }}</div>
        <div class="elect-cont-time-name elect-cont-time-title">报告时间：</div>
        <div class="elect-cont-time-item">{{ dataObj.BGSJ }}</div>
      </div>
      <div class="elect-cont-mian">
        <div class="elect-cont-mian-name elect-cont-main-title">姓名：</div>
        <div class="elect-cont-main-item">{{ dataObj.BRXM }}</div>
        <div class="elect-cont-main-name elect-cont-main-title">性别：</div>
        <div class="elect-cont-main-item">{{ dataObj.BRXB == 1 ? '男' : '女' }}</div>
        <div class="elect-cont-main-name elect-cont-main-title">年龄：</div>
        <div class="elect-cont-main-item">{{ dataObj.SQKSMC }}</div>
      </div>
      <div class="elect-cont-mian">
        <div class="elect-cont-mian-name elect-cont-main-title">科室:</div>
        <div class="elect-cont-main-item">{{ dataObj.SQKSMC }}</div>
        <div class="elect-cont-main-name elect-cont-main-title">住院号：</div>
        <div class="elect-cont-main-item">{{ dataObj.JZLSH }}</div>
        <div class="elect-cont-main-name elect-cont-main-title">检查号：</div>
        <div class="elect-cont-main-item">{{ dataObj.CT464003 }}</div>
      </div>

      <div class="elect-cont-infor-line" />

      <div class="elect-cont-infor-text">{{ dataObj.JCMC }}</div>

      <div class="elect-cont-chief elect-cont-mg20">
        <div class="elect-cont-chief-title">
          心电图提示:
          <span class="elect-cont-textnor" />
        </div>
        <div v-for="(jitem, index) in dataObj.YXZD" :key="index" class="caseit-cont-chief-cont">{{ jitem }}</div>
      </div>

      <div class="elect-cont-sign elect-cont-sign-top">
        <div>
          <span class="elect-cont-sign-bolb">报告时间：</span>
          <span class="elect-cont-sign-cont">{{ dataObj.BGSJ }}</span>
        </div>
        <div>
          <span class="elect-cont-sign-bolb">审核医师：</span>
          <span class="elect-cont-sign-cont">{{ dataObj.SHRXM }}</span>
        </div>
      </div>
      <div class="elect-cont-sign">
        <div>
          <span class="elect-cont-sign-bolb">开单时间：</span>
          <span class="elect-cont-sign-cont">{{ dataObj.KDSJ }}</span>
        </div>
        <div>
          <span class="elect-cont-sign-bolb">检查科室：</span>
          <span class="elect-cont-sign-cont">{{ dataObj.JCKSMC }}</span>
        </div>
      </div>
      <!-- <div class="elect-cont-physique">
            <div class="elect-cont-physique-text">
              【本报告仅供临床医师诊断参考使用】
            </div>
          </div> -->
    </div>
  </div>
</template>
<script>
export default {
  name: '',
  components: {
    // OtherComponent
  },
  directives: {},
  filters: {},
  extends: {},
  mixins: {},
  props: {
    dataObjArr: {
      type: null,
      default: false
    }
  },
  data() {
    return {
      ifFile: false
    }
  },
  computed: {},
  watch: {},
  beforeCreate() {
    // 生命周期钩子：组件实例刚被创建，组件属性计算之前，如 data 属性等
  },
  created() {
    // 生命周期钩子：组件实例创建完成，属性已绑定，但 DOM 还未生成，el 属性还不存在
    // 初始化渲染页面
  },
  beforeMount() {
    // 生命周期钩子：模板编译/挂载之前
  },
  mounted() {
    // 生命周期钩子：模板编译、挂载之后（此时不保证已在 document 中）
  },
  beforeUpate() {
    // 生命周期钩子：组件更新之前
  },
  updated() {
    // 生命周期钩子：组件更新之后
  },
  activated() {
    // 生命周期钩子：keep-alive 组件激活时调用
  },
  deactivated() {
    // 生命周期钩子：keep-alive 组件停用时调用
  },
  beforeDestroy() {
    // 生命周期钩子：实例销毁前调用
  },
  destroyed() {
    // 生命周期钩子：实例销毁后调用
  },
  errorCaptured(err, vm, info) {
    // 生命周期钩子：当捕获一个来自子孙组件的错误时被调用。此钩子会收到三个参数：错误对象、发生错误的组件实例以及一个包含错误来源信息的字符串。
    console.log(err, vm, info)
  },
  methods: {}
}
</script>
<style lang="scss" scoped>
#electrocarA {
  padding: 0;
  margin: 0 30px;
  .elect-cont {
    width: 100%;
    display: flex;
    flex-direction: column;
    justify-content: flex-start;
    align-items: center;
    margin-bottom: 30px;
    .cont-title-description {
      margin: 20px;
      font-size: 24px;
      font-weight: bold;
      color: #2c3240;
      text-align: center;
    }
    .cont-textbold {
      font-weight: bold;
    }
    .elect-cont-header {
      height: 50px;
      width: 100%;
      display: flex;
      justify-content: flex-end;
      align-items: center;
      // margin-top: 20px;
      margin-bottom: 20px;
      padding-right: 30px;
    }
    .elect-cont-infor {
      width: 100%;
      height: 30px;
      display: flex;
      justify-content: center;
      align-items: center;
      margin-top: 5px;
      .elect-cont-infor-list {
        width: 15%;
      }
      .elect-cont-infor-title {
        width: 8%;
        text-align: right;
      }
      .elect-cont-infor-listw {
        width: 38%;
      }
      .elect-cont-infor-listwl {
        width: 30%;
      }
      .elect-cont-infor-text {
        text-align: right;
      }
    }

    .elect-cont-infor-line {
      width: 100%;
      height: 2px;
      border-bottom: 1.5px solid #e2dfdf;
      margin-top: 20px;
      margin-bottom: 30px;
    }
    .elect-cont-infor-text {
      font-size: 16px;
    }
    .elect-cont-chief {
      width: 100%;
      display: flex;
      min-height: 120px;
      padding: 10px;
      margin-top: 30px;
      flex-direction: column;
      .elect-cont-chief-title {
        font-weight: bold;
      }
      .caseit-cont-chief-cont {
        line-height: 25px;
        padding-left: 30px;
      }
    }

    .elect-cont-mian {
      width: 100%;
      display: flex;
      padding: 10px;
      justify-content: flex-start;
      .elect-cont-main-title {
        font-weight: bold;
      }
      .elect-cont-main-item {
        width: 20%;
      }
      .elect-cont-main-name {
        width: 80px;
      }
    }

    .elect-cont-time {
      width: 100%;
      display: flex;
      padding: 10px;
      justify-content: flex-start;
      .elect-cont-time-title {
        font-weight: bold;
      }
      .elect-cont-time-item {
        width: 200px;
      }
      .elect-cont-time-name {
        width: 80px;
      }
    }

    .elect-cont-mian-border {
      border-bottom: 1.5px solid #e2dfdf;
    }
    .elect-cont-mg20 {
      margin-top: 20px;
    }
    .elect-cont-sign {
      width: 100%;
      height: 30px;
      // border-bottom: 1.5px solid #e2dfdf;
      padding-left: 50px;
      display: flex;
      margin-bottom: 30px;
      justify-content: space-between;
      .elect-cont-sign-cont {
        width: 20%;
      }
      .elect-cont-sign-bolb {
        font-weight: bold;
      }
    }

    .elect-cont-sign-top {
      border-bottom: 0;
      margin-bottom: 0;
      margin-top: 30px;
    }
    .elect-cont-physique {
      width: 100%;
      height: 30px;
      text-align: left;
      padding-left: 80px;
      margin-top: 30px;
      margin-bottom: 50px;
      display: flex;
      justify-content: flex-end;
      .elect-cont-physique-text {
        width: 45%;
        font-weight: bold;
      }
      .elect-cont-physique-time {
        margin-left: 10%;
        .elect-cont-physique-title {
          font-weight: bold;
        }
      }
    }

    .elect-cont-textnor {
      font-weight: normal;
    }
  }
}
.examType {
  width: 100%;
  display: flex;
  justify-content: flex-end;
  padding-right: 50px;
}
</style>
