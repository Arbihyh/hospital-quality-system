<template>
  <!-- 超声报告单 -->
  <div id="ultrasTextA">
    <div v-for="(dataObj, index) in dataObjArr" :key="index" class="ultras-cont">
      <div class="cont-title-description">滨州医学院烟台附属医院超声诊断报告</div>
      <div class="examType">{{ dataObj.ExamType }}</div>

      <div class="ultras-cont-header" />
      <div class="ultras-cont-infortop ultras-cont-border">
        <div class="ultras-cont-infor-title cont-textbold">检查日期:</div>
        <div class="ultras-cont-infor-listwl">
          <span>{{ dataObj.JYSJ }}</span>
        </div>
        <div class="ultras-cont-infor-title cont-textbold">报告时间:</div>
        <div class="ultras-cont-infor-list">
          <span>{{ dataObj.BGSJ }}</span>
        </div>
      </div>
      <div class="ultras-cont-infortop">
        <div class="ultras-cont-infor-title cont-textbold">检查号:</div>
        <div class="ultras-cont-infor-listwl">
          <span>{{ dataObj.StudyUid }}</span>
        </div>
        <div class="ultras-cont-infor-title cont-textbold">住院号:</div>
        <div class="ultras-cont-infor-list">
          <span>{{ dataObj.JZLSH }}</span>
        </div>
      </div>
      <div class="ultras-cont-infor">
        <div class="ultras-cont-infor-title ultras-cont-infor-text cont-textbold">姓名:</div>
        <div class="ultras-cont-infor-list">
          <span>{{ dataObj.BRXM }}</span>
        </div>
        <div class="ultras-cont-infor-title cont-textbold">性别:</div>
        <div class="ultras-cont-infor-list">
          <span>{{ dataObj.BRXB == 1 ? '男' : '女' }}</span>
        </div>
        <div class="ultras-cont-infor-title cont-textbold">年龄:</div>
        <div class="ultras-cont-infor-list">
          <span>{{ dataObj.BRNL }}</span>
        </div>
        <div class="ultras-cont-infor-title cont-textbold">科别:</div>
        <div class="ultras-cont-infor-list">
          <span>{{ dataObj.SQKSMC }}</span>
        </div>
      </div>

      <div class="ultras-cont-chief ultras-cont-mg20">
        <div class="ultras-cont-chief-title">超声所见:</div>
        <div v-for="(jitem, index) in dataObj.YXBX" :key="index" class="ultras-cont-chief-cont">{{ jitem }}</div>
      </div>
      <div class="ultras-cont-chief ultras-cont-mg20">
        <div class="ultras-cont-chief-title">
          超声提示:
          <span class="ultras-cont-textnor" />
        </div>
        <div v-for="(jitem, index) in dataObj.YXZD" :key="index" class="ultras-cont-chief-cont">{{ jitem }}</div>
      </div>

      <div class="ultras-cont-sign ultras-cont-sign-top">
        <span class="ultras-cont-sign-bolb">检查医师：</span>
        <span class="ultras-cont-sign-cont">{{ dataObj.JCYS }}</span>
        <span class="ultras-cont-sign-bolb">审核医师：</span>
        <span class="ultras-cont-sign-cont">{{ dataObj.SHRXM }}</span>
        <span class="ultras-cont-sign-bolb">录入员：</span>
        <span class="ultras-cont-sign-cont">{{ dataObj.LRY }}</span>
        <span class="ultras-cont-sign-bolb">会诊医师：</span>
        <span class="ultras-cont-sign-cont">{{ dataObj.HZYS }}</span>
      </div>
      <div class="ultras-cont-sign">
        <span class="ultras-cont-sign-bolbw">开单时间：</span>
        <span class="ultras-cont-sign-contw">{{ dataObj.KDSJ }}</span>
        <span class="ultras-cont-sign-bolbw">检查科室：</span>
        <span class="ultras-cont-sign-cont">{{ dataObj.JCKSMC }}</span>
      </div>
      <div class="ultras-cont-physique">
        <div class="ultras-cont-physique-text">本诊断报告仅供临床参考，报告仅此一份，复诊时带来。</div>
        <!-- <div class="ultras-cont-physique-time">
          <span class="ultras-cont-physique-title">检查日期</span>
          <span>{{ dataObj.JYSJ }}</span>
        </div> -->
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
#ultrasTextA {
  padding: 0;
  margin: 0 30px;
  .ultras-cont {
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
    .ultras-cont-header {
      width: 100%;
      height: 50px;
      display: flex;
      justify-content: flex-start;
      align-items: center;
      margin-top: 20px;
      padding-right: 30px;
    }
    .ultras-cont-infor {
      width: 100%;
      height: 38px;
      display: flex;
      justify-content: center;
      align-items: center;
      border-bottom: 1.5px solid #e2dfdf;
      margin-top: 10px;
      padding-bottom: 10px;
      margin-bottom: 30px;
      .ultras-cont-infor-list {
        width: 15%;
      }
      .ultras-cont-infor-title {
        width: 100px;
        text-align: right;
      }
      .ultras-cont-infor-listw {
        width: 38%;
      }
      .ultras-cont-infor-listwl {
        width: 30%;
      }
      .ultras-cont-infor-text {
        text-align: right;
      }
    }
    .ultras-cont-infortop {
      width: 100%;
      height: 38px;
      display: flex;
      justify-content: flex-start;
      align-items: center;
      padding-left: 5%;
      .ultras-cont-infor-list {
        width: 200px;
      }
      .ultras-cont-infor-title {
        width: 100px;
        text-align: right;
      }
      .ultras-cont-infor-listw {
        width: 200px;
      }
      .ultras-cont-infor-listwl {
        width: 200px;
      }
      .ultras-cont-infor-text {
        text-align: right;
      }
    }
    .ultras-cont-chief {
      width: 100%;
      min-height: 80px;
      padding: 10px;
      .ultras-cont-chief-title {
        font-weight: bold;
      }
      .ultras-cont-chief-cont {
        line-height: 25px;
        padding-left: 30px;
      }
    }
    .ultras-cont-mg20 {
      margin-top: 20px;
    }
    .ultras-cont-mg40 {
      margin-top: 40px;
    }
    .ultras-cont-border {
      border-bottom: 1.5px solid #e2dfdf;
    }
    .ultras-cont-sign {
      width: 100%;
      height: 30px;
      border-bottom: 1.5px solid #e2dfdf;
      padding-left: 50px;
      display: flex;
      justify-content: flex-start;
      align-items: center;
      margin-bottom: 30px;
      .ultras-cont-sign-cont {
        width: 180px;
        text-align: left;
      }
      .ultras-cont-sign-contw {
        width: 200px;
      }
      .ultras-cont-sign-bolb {
        width: 140px;
        font-weight: bold;
      }
      .ultras-cont-sign-bolbw {
        font-weight: bold;
      }
    }
    .ultras-cont-sign-top {
      border-bottom: 0;
      margin-bottom: 0;
    }
    .ultras-cont-physique {
      width: 100%;
      height: 30px;
      text-align: left;
      padding-left: 80px;
      margin-top: 30px;
      margin-bottom: 50px;
      display: flex;
      justify-content: flex-start;
      .ultras-cont-physique-text {
        font-weight: bold;
      }
      .ultras-cont-physique-time {
        margin-left: 10%;
        .ultras-cont-physique-title {
          font-weight: bold;
        }
      }
    }

    .ultras-cont-textnor {
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
