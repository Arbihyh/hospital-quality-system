<template>
  <!-- 病历图文报告单 -->
  <div id="caseImageTextA">
    <div class="caseit-cont" v-for="(dataObj, index) in dataObjArr" :key="index">
      <div class="cont-title-description">{{ systemSetting.web_name }}病理诊断报告</div>
      <div class="examType">{{ dataObj.ExamType }}</div>

      <div class="caseit-cont-header">
        <div class="caseit-cont-header-item">
          <div class="caseit-cont-infor-title caseit-cont-infor-text cont-textbold">检查日期:</div>
          <div class="caseit-cont-infor-list">
            <span>{{ dataObj.JYSJ }}</span>
          </div>
        </div>
        <div class="caseit-cont-header-item">
          <div class="caseit-cont-infor-title cont-textbold">报告时间:</div>
          <div class="caseit-cont-infor-list">
            <span>{{ dataObj.BGSJ }}</span>
          </div>
        </div>
      </div>

      <div class="caseit-cont-infor">
        <div class="caseit-cont-infor-title caseit-cont-infor-text cont-textbold">姓名:</div>
        <div class="caseit-cont-infor-list">
          <span>{{ dataObj.BRXM }}</span>
        </div>
        <div class="caseit-cont-infor-title cont-textbold">性别:</div>
        <div class="caseit-cont-infor-list">
          <span>{{ dataObj.BRXB == 1 ? '男' : '女' }}</span>
        </div>
        <div class="caseit-cont-infor-title cont-textbold">年龄:</div>
        <div class="caseit-cont-infor-list">
          <span>{{ dataObj.BRNL }}</span>
        </div>
        <div class="caseit-cont-infor-title cont-textbold">科别:</div>
        <div class="caseit-cont-infor-list">
          <span>{{ dataObj.SQKSMC }}</span>
        </div>
        <div class="caseit-cont-infor-title cont-textbold">住院号码:</div>
        <div class="caseit-cont-infor-list">
          <span>{{ dataObj.JZLSH }}</span>
        </div>
      </div>
      <div class="caseit-cont-infor">
        <div class="caseit-cont-infor-title cont-textbold">送检科别:</div>
        <div class="caseit-cont-infor-list">
          <span>{{ dataObj.JCBJGMC }}</span>
        </div>
        <div class="caseit-cont-infor-title cont-textbold">标本部分:</div>
        <div class="caseit-cont-infor-listw">
          <span>{{ dataObj.JCBW }}</span>
        </div>
        <div class="caseit-cont-infor-title cont-textbold">病理号:</div>
        <div class="caseit-cont-infor-listw">
          <span>{{ dataObj.StudyUid }}</span>
        </div>
      </div>

      <div class="caseit-cont-chief caseit-cont-mg20">
        <div class="caseit-cont-chief-title">大体描述:</div>
        <div class="caseit-cont-chief-cont" v-for="(jitem, index) in dataObj.JCBW" :key="index">{{ jitem }}</div>
      </div>
      <div class="caseit-cont-chief caseit-cont-mg20 caseit-cont-chief-tb">
        <div class="caseit-cont-chief-title">病理诊断:</div>
        <div class="caseit-cont-chief-cont" v-for="(jitem, index) in dataObj.YXZD" :key="index">{{ jitem }}</div>
      </div>

      <div class="caseit-cont-sign">
        <span class="caseit-cont-sign-bolb">报告医师：</span>
        <span class="caseit-cont-sign-cont">{{ dataObj.BGRXM }}</span>
        <span class="caseit-cont-sign-bolb">审核医师：</span>
        <span class="caseit-cont-sign-cont">{{ dataObj.SHRXM }}</span>
      </div>
      <div class="caseit-cont-sign">
        <span class="caseit-cont-sign-bolb">开单时间：</span>
        <span class="caseit-cont-sign-cont">{{ dataObj.KDSJ }}</span>
        <span class="caseit-cont-sign-bolb">检查科室：</span>
        <span class="caseit-cont-sign-cont">{{ dataObj.JCKSMC }}</span>
      </div>
      <div class="caseit-cont-physique">注:如对病理诊断有疑问，请及时联系诊断医师。</div>
    </div>
  </div>
</template>
<script>
import { mapState } from 'vuex';
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
      default: false,
    },
  },
  data() {
    return {
      ifFile: false,
    };
  },
  computed: {
    ...mapState({
      systemSetting: state => state.app.systemSetting,
    }),
  },
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
    console.log(err, vm, info);
  },
  methods: {},
};
</script>
<style lang="scss" scoped>
#caseImageTextA {
  padding: 0;
  margin: 0 30px;
  .caseit-cont {
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
    .caseit-cont-header {
      height: 50px;
      width: 100%;
      border-bottom: 1.5px solid #e2dfdf;
      display: flex;
      justify-content: flex-start;
      align-items: center;
      margin-top: 50px;
      margin-bottom: 20px;
      padding-right: 30px;
      .caseit-cont-header-item {
        display: flex;
        justify-content: flex-start;
        align-items: center;
        width: 50%;
      }
    }
    .caseit-cont-infor {
      width: 100%;
      height: 40px;
      display: flex;
      justify-content: center;
      align-items: center;

      .caseit-cont-infor-list {
        width: 15%;
      }
      .caseit-cont-infor-title {
        width: 100px;
        text-align: right;
      }
      .caseit-cont-infor-listw {
        width: 200px;
      }
      .caseit-cont-infor-listwl {
        width: 61%;
      }
      .caseit-cont-infor-text {
        text-align: right;
      }
    }
    .caseit-cont-chief {
      width: 100%;
      min-height: 80px;
      padding: 10px;
      border-top: 1.5px solid #e2dfdf;
      .caseit-cont-chief-title {
        font-weight: bold;
      }
      .caseit-cont-chief-cont {
        line-height: 25px;
        padding-left: 30px;
      }
    }
    .caseit-cont-chief-tb {
      border-top: 0;
    }
    .caseit-cont-mg20 {
      margin-top: 20px;
    }
    .caseit-cont-sign {
      width: 100%;
      height: 30px;
      padding-top: 20px;
      padding-left: 50px;
      display: flex;
      justify-content: flex-start;
      margin-bottom: 30px;
      .caseit-cont-sign-cont {
        width: 200px;
        text-align: left;
      }
      .caseit-cont-sign-bolb {
        font-weight: bold;
        width: 100px;
      }
    }

    .caseit-cont-sign-top {
      border-bottom: 0;
      margin-bottom: 0;
    }
    .caseit-cont-physique {
      width: 100%;
      height: 30px;
      text-align: left;
      padding-left: 80px;
      margin-top: 30px;
      font-weight: bold;
      margin-bottom: 50px;
    }

    .caseit-cont-auxi {
      width: 100%;
      min-height: 30px;
      display: flex;
      .caseit-cont-auxi-list {
        width: 25%;
        text-align: left;
      }
    }
    .caseit-cont-prel {
      width: 100%;
      padding-left: 50%;
    }
    .caseit-cont-prel-text {
      width: 100%;
      padding-left: 58%;
      line-height: 20px;
    }
    .caseit-cont-textnor {
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
