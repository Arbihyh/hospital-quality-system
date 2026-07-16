<template>
  <!-- 影像报告单 -->
  <div id="imagingA">
    <div v-for="(dataObj, index) in dataObjArr" :key="index" class="uimag-cont">
      <div class="cont-title-description">滨州医学院烟台附属医院影像诊断报告</div>
      <div class="examType">{{ dataObj.ExamType }}</div>
      <div class="uimag-cont-header" />
      <div class="uimag-cont-infortop">
        <div class="uimag-cont-infor-titlet cont-textbold">检查日期:</div>
        <div class="uimag-cont-infor-listwl">
          <span>{{ dataObj.JYSJ }}</span>
        </div>
        <div class="uimag-cont-infor-titlet cont-textbold">报告时间:</div>
        <div class="uimag-cont-infor-listwl">
          <span>{{ dataObj.BGSJ }}</span>
        </div>
      </div>
      <div class="uimag-cont-infor">
        <div class="uimag-cont-infor-title uimag-cont-infor-text cont-textbold">姓名:</div>
        <div class="uimag-cont-infor-list">
          <span v-if="dataObj">{{ dataObj.BRXM }}</span>
        </div>
        <div class="uimag-cont-infor-title cont-textbold">性别:</div>
        <div class="uimag-cont-infor-list">
          <span>{{ dataObj.BRXB == 1 ? '男' : '女' }}</span>
        </div>
        <div class="uimag-cont-infor-title cont-textbold">年龄:</div>
        <div class="uimag-cont-infor-list">
          <span />
          {{ dataObj.BRNL }}
        </div>
        <div class="uimag-cont-infor-title cont-textbold">影像号:</div>
        <div class="uimag-cont-infor-list">
          <span v-if="dataObj.PatientID">{{ dataObj.PatientID }}</span>
        </div>
      </div>
      <div class="uimag-cont-infor">
        <div class="uimag-cont-infor-title uimag-cont-infor-text cont-textbold">科室:</div>
        <div class="uimag-cont-infor-list">
          <span>{{ dataObj.SQKSMC }}</span>
        </div>
        <div class="uimag-cont-infor-title cont-textbold">住院号:</div>
        <div class="uimag-cont-infor-list">
          <span>{{ dataObj.JZLSH }}</span>
        </div>
        <div class="uimag-cont-infor-title cont-textbold">床号:</div>
        <div class="uimag-cont-infor-list">
          <span>{{ dataObj.CH }}</span>
        </div>
        <div class="uimag-cont-infor-title cont-textbold">检查号:</div>
        <div class="uimag-cont-infor-list">
          <span>{{ dataObj.StudyUid }}</span>
        </div>
      </div>
      <div class="uimag-cont-infor-line" />
      <div class="elect-cont-infor-text">{{ dataObj.JCMC }}</div>

      <div class="uimag-cont-chief uimag-cont-mg20">
        <div class="uimag-cont-chief-title">
          影像学表现:
          <span class="uimag-cont-textnor" />
        </div>
        <div v-for="(jitem, index) in dataObj.YXBX" :key="index" class="caseit-cont-chief-cont">{{ jitem }}</div>
      </div>
      <div class="uimag-cont-chief uimag-cont-mg20">
        <div class="uimag-cont-chief-title">
          影像学诊断:
          <span class="uimag-cont-textnor" />
        </div>
        <div v-for="(jitem, index) in dataObj.YXZD" :key="index" class="caseit-cont-chief-cont">{{ jitem }}</div>
      </div>

      <div class="uimag-cont-sign uimag-cont-sign-top">
        <span class="uimag-cont-sign-bolb">影像医师：</span>
        <span class="uimag-cont-sign-cont">{{ dataObj.BGRXM }}</span>
        <span class="uimag-cont-sign-bolb">审核医师：</span>
        <span class="uimag-cont-sign-cont">{{ dataObj.SHRXM }}</span>
      </div>
      <div class="uimag-cont-sign">
        <span class="uimag-cont-sign-bolb">开单时间：</span>
        <span class="uimag-cont-sign-cont">{{ dataObj.KDSJ }}</span>
        <span class="uimag-cont-sign-bolb">检查科室：</span>
        <span class="uimag-cont-sign-cont">{{ dataObj.JCKSMC }}</span>
      </div>
      <div class="uimag-cont-physique">
        <div class="uimag-cont-physique-text">【本报告仅供临床医师诊断参考使用】</div>
      </div>
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
#imagingA {
  padding: 0;
  margin: 0 30px;
  .uimag-cont {
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
    .uimag-cont-header {
      height: 50px;
      width: 100%;
      display: flex;
      justify-content: flex-end;
      align-items: center;
      // margin-top: 20px;
      margin-bottom: 20px;
      padding-right: 30px;
    }
    .uimag-cont-infor {
      width: 100%;
      height: 30px;
      display: flex;
      justify-content: center;
      align-items: center;
      margin-top: 5px;
      .uimag-cont-infor-list {
        width: 15%;
      }
      .uimag-cont-infor-title {
        width: 8%;
        text-align: right;
      }
      .uimag-cont-infor-titlet {
        width: 100px;
        text-align: right;
      }
      .uimag-cont-infor-listw {
        width: 38%;
      }
      .uimag-cont-infor-listwl {
        width: 200px;
      }
      .uimag-cont-infor-text {
        text-align: right;
      }
    }
    .uimag-cont-infortop {
      width: 100%;
      height: 30px;
      display: flex;
      justify-content: left;
      align-items: center;
      border-bottom: 1.5px solid #e2dfdf;
      margin-top: 20px;
      padding-left: 5%;

      .uimag-cont-infor-list {
        width: 15%;
      }
      .uimag-cont-infor-title {
        width: 100px;
        text-align: right;
      }
      .uimag-cont-infor-listw {
        width: 38%;
      }
      .uimag-cont-infor-listwl {
        width: 30%;
      }
      .uimag-cont-infor-text {
        text-align: right;
      }
    }
    .uimag-cont-infor-line {
      width: 100%;
      height: 2px;
      border-bottom: 1.5px solid #e2dfdf;
      margin-top: 20px;
      margin-bottom: 30px;
    }
    .elect-cont-infor-text {
      font-size: 16px;
    }
    .uimag-cont-chief {
      width: 100%;
      min-height: 80px;
      padding: 10px;
      .uimag-cont-chief-title {
        font-weight: bold;
      }
      .caseit-cont-chief-cont {
        line-height: 25px;
        padding-left: 30px;
      }
    }
    .uimag-cont-mg20 {
      margin-top: 20px;
    }
    .uimag-cont-sign {
      width: 100%;
      height: 30px;
      border-bottom: 1.5px solid #e2dfdf;
      padding-left: 50px;
      display: flex;
      margin-bottom: 30px;
      .uimag-cont-sign-cont {
        width: 200px;
      }
      .uimag-cont-sign-bolb {
        width: 100px;
        font-weight: bold;
      }
    }
    .uimag-cont-sign-top {
      border-bottom: 0;
      margin-bottom: 0;
    }
    .uimag-cont-physique {
      width: 100%;
      height: 30px;
      text-align: left;
      padding-left: 80px;
      margin-top: 30px;
      margin-bottom: 50px;
      display: flex;
      justify-content: flex-start;
      .uimag-cont-physique-text {
        width: 45%;
        font-weight: bold;
      }
      .uimag-cont-physique-time {
        margin-left: 10%;
        .uimag-cont-physique-title {
          font-weight: bold;
        }
      }
    }

    .uimag-cont-textnor {
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
