<template>
  <!-- 检验报告单 -->
  <div id="checkoutA">
    <div v-for="(dataObj, index) in dataObjArr" :key="index" class="checkout-cont">
      <div class="cont-title-description">滨州医学院烟台附属医院检验报告单</div>
      <div class="examType">{{ dataObj.ExamType }}</div>
      <div class="checkout-cont-header" />

      <div class="checkout-cont-infortop">
        <div class="checkout-cont-infor-title cont-textbold">姓名:</div>
        <div class="checkout-cont-infor-list">
          <span>{{ dataObj.XM }}</span>
        </div>
        <div class="checkout-cont-infor-title cont-textbold">性别:</div>
        <div class="checkout-cont-infor-list">
          <span>{{ dataObj.XB }}</span>
        </div>
        <div class="checkout-cont-infor-title cont-textbold">年龄:</div>
        <div class="checkout-cont-infor-list">
          <span>{{ dataObj.NL }}</span>
        </div>
        <div class="checkout-cont-infor-title cont-textbold">病区:</div>
        <div class="checkout-cont-infor-list">
          <span>{{ dataObj.BQ }}</span>
        </div>
        <div class="checkout-cont-infor-title cont-textbold">住院号:</div>
        <div class="checkout-cont-infor-list">
          <span>{{ dataObj.AAA28 }}</span>
        </div>
      </div>
      <div class="checkout-cont-infortop checkout-cont-inforbotm">
        <div class="checkout-cont-infor-title cont-textbold">采集时间:</div>
        <div class="checkout-cont-infor-list">
          <span>{{ dataObj.CJSJ }}</span>
        </div>
        <div class="checkout-cont-infor-title cont-textbold">接收时间:</div>
        <div class="checkout-cont-infor-list">
          <span>{{ dataObj.JSSJ }}</span>
        </div>
        <div class="checkout-cont-infor-title cont-textbold">审查目的:</div>
        <div class="checkout-cont-infor-list">
          <span />
        </div>
      </div>
      <div class="checkout-cont-infortop checkout-cont-inforbotm">
        <div class="checkout-cont-infor-title cont-textbold">样本类型:</div>
        <div class="checkout-cont-infor-list">
          <span>{{ dataObj.YBLX }}</span>
        </div>
        <div class="checkout-cont-infor-title cont-textbold">编号:</div>
        <div class="checkout-cont-infor-list">
          <span>{{ dataObj.NO }}</span>
        </div>
        <div class="checkout-cont-infor-title cont-textbold">申请人:</div>
        <div class="checkout-cont-infor-list">
          <span>{{ dataObj.SJYS }}</span>
        </div>
      </div>

      <div class="checkout-cont-infor-line" />
      <div v-if="dataObj.template_type == 1" class="checkout-cont-info">
        <div class="checkout-cont-chief checkout-cont-mg20">
          <div class="checkout-cont-chief-item checkout-cont-chief-bole">细菌培养结果</div>
          <div class="checkout-cont-chief-item checkout-cont-chief-bole">细菌名称</div>
          <div class="checkout-cont-chief-item checkout-cont-chief-bole">细菌数量</div>
          <div class="checkout-cont-chief-item checkout-cont-chief-bole">药敏名称</div>
          <div class="checkout-cont-chief-item checkout-cont-chief-bole">药敏结果</div>
          <div class="checkout-cont-chief-item checkout-cont-chief-bole">部位（样本类型）</div>
        </div>
        <div v-for="(item, index) in dataObj.JCXM" :key="index" class="checkout-cont-chief checkout-cont-mg20">
          <div class="checkout-cont-chief-item">{{ item.PYJG }}</div>
          <div class="checkout-cont-chief-item">{{ item.XJMC }}</div>
          <div class="checkout-cont-chief-item">{{ item.XJSL }}</div>
          <div class="checkout-cont-chief-item">{{ item.YMMC }}</div>
          <div class="checkout-cont-chief-item">{{ item.YMJG }}</div>
          <div class="checkout-cont-chief-item">{{ item.YMBW }}</div>
        </div>
      </div>
      <div v-if="dataObj.template_type == 2" class="checkout-cont-info">
        <div class="checkout-cont-chief checkout-cont-mg20">
          <div class="checkout-cont-chief-item checkout-cont-chief-bole checkout-cont-tc">序号</div>
          <div class="checkout-cont-chief-item checkout-cont-chief-bole">中文名称</div>
          <div class="checkout-cont-chief-item checkout-cont-chief-bole">英文名称</div>
          <div class="checkout-cont-chief-item checkout-cont-chief-bole">检验结果</div>
          <div class="checkout-cont-chief-item checkout-cont-chief-bole">异常结果提示</div>
          <div class="checkout-cont-chief-item checkout-cont-chief-bole">参考范围</div>
          <div class="checkout-cont-chief-item checkout-cont-chief-bole">单位</div>
        </div>
        <div v-for="(jitem, index) in dataObj.JCXM" :key="index" class="checkout-cont-chief checkout-cont-mg20">
          <div class="checkout-cont-chief-item checkout-cont-tc">{{ index }}</div>
          <div class="checkout-cont-chief-item">{{ jitem.JYXM }}</div>
          <div class="checkout-cont-chief-item">{{ jitem.YW }}</div>
          <div class="checkout-cont-chief-item">{{ jitem.JG }}</div>
          <div class="checkout-cont-chief-item">{{ jitem.TS }}</div>
          <div class="checkout-cont-chief-item">{{ jitem.CKFW }}</div>
          <div class="checkout-cont-chief-item">{{ jitem.DW }}</div>
        </div>
      </div>

      <div class="checkout-cont-infor-line" />
      <div class="checkout-cont-zd">
        <span class="checkout-cont-zd-title">临床诊断为</span>
      </div>
      <div class="checkout-cont-sign">
        <span class="checkout-cont-sign-bolb">报告时间：</span>
        <span class="checkout-cont-sign-cont">{{ dataObj.BGSJ }}</span>
        <span class="checkout-cont-sign-bolb">检 验 者：</span>
        <span class="checkout-cont-sign-cont">{{ dataObj.JYY }}</span>
        <span class="checkout-cont-sign-bolb">审 核 者：</span>
        <span class="checkout-cont-sign-cont">{{ dataObj.SHY }}</span>
      </div>

      <div class="checkout-cont-physique">
        <div class="checkout-cont-physique-text">
          注：此报告仅对送检标本负责，供医师参考。标*项目为质评合格项目。如有疑问请在48小时内反馈至检验科。地址：烟台市牟平区金埠大街717号滨州医学院烟台附属医院门诊部3楼检验科。电话：0535-4770362。
        </div>
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
#checkoutA {
  padding: 0;
  margin: 0 30px;
  .checkout-cont {
    width: 100%;
    display: flex;
    flex-direction: column;
    justify-content: flex-start;
    align-items: center;
    border: 1.5px solid #e2dfdf;
    margin-top: 30px;
    margin-bottom: 30px;
    .cont-title-description {
      margin: 20px;
      font-size: 24px;
      font-weight: bold;
      color: #2c3240;
      text-align: center;
      margin-top: 40px;
    }
    .cont-textbold {
      font-weight: bold;
    }
    .checkout-cont-header {
      height: 30px;
      width: 100%;
      display: flex;
      justify-content: flex-start;
      align-items: center;
      margin-top: 5px;
      padding-left: 50px;
    }
    .checkout-cont-mark {
      width: 100%;
      margin-top: 10px;
      display: flex;
      justify-content: space-between;
      padding-left: 50px;
      padding-right: 50px;
      .checkout-cont-mark-mint {
        width: 50%;
        .checkout-cont-mark-title {
          font-weight: bold;
        }
        .checkout-cont-mark-item {
          display: inline-block;
          width: 30%;
        }
      }
    }
    .checkout-cont-info {
      width: 100%;
      min-height: 300px;
      .checkout-cont-chief-proof {
        padding-left: 100px;
        margin-top: 20px;
        .checkout-cont-chief-pbold {
          font-weight: bold;
        }
      }
    }
    .checkout-cont-zd {
      width: 100%;
      margin-bottom: 20px;
      text-align: left;
      padding-left: 30px;
      .checkout-cont-zd-title {
        font-weight: bold;
      }
    }
    .checkout-cont-infortop {
      width: 100%;
      height: 30px;
      display: flex;
      justify-content: left;
      align-items: center;
      margin-top: 20px;
      padding-left: 5%;
      .checkout-cont-infor-list {
        width: 15%;
      }
      .checkout-cont-infor-title {
        width: 11%;
        text-align: right;
      }

      .checkout-cont-infor-text {
        text-align: right;
      }
    }
    .checkout-cont-inforbotm {
      justify-content: flex-start;
      padding-left: 2%;
    }
    .checkout-cont-infor-line {
      width: 100%;
      height: 2px;
      border-bottom: 1.5px solid #e2dfdf;
      margin-top: 20px;
      margin-bottom: 30px;
    }
    .checkout-cont-chief {
      width: 100%;
      min-height: 30px;
      display: flex;
      justify-content: center;
      align-items: center;
      .checkout-cont-chief-item {
        width: 15%;
        text-align: left;
        padding-right: 15px;
      }
      .checkout-cont-tc {
        text-align: center;
      }
      .checkout-cont-chief-bole {
        font-weight: bold;
      }
    }
    .checkout-cont-mg20 {
      margin-top: 5px;
    }
    .checkout-cont-sign {
      width: 100%;
      height: 30px;
      padding-left: 50px;
      display: flex;
      align-items: center;
      justify-content: center;
      .checkout-cont-sign-cont {
        width: 20%;
      }
      .checkout-cont-sign-bolb {
        font-weight: bold;
      }
    }
    .checkout-cont-physique {
      width: 100%;
      height: 30px;
      text-align: left;
      padding-left: 80px;
      padding-right: 80px;
      margin-top: 30px;
      margin-bottom: 50px;
      display: flex;
      justify-content: flex-start;
      .checkout-cont-physique-text {
        width: 100%;
        font-weight: bold;
      }
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
