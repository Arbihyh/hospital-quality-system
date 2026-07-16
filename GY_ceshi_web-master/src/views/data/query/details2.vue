<template>
  <div id="MyDiv">
    <div class="header">
      <el-button type="success" v-if="!ifFile" @click="funEdit">编辑</el-button>
      <el-button type="warning" v-if="ifFile" @click="funNext">保存</el-button>
      <el-button type="primary" icon="el-icon-download" class="export-btn">导出数据</el-button>
      <el-button @click="getback">返回</el-button>
    </div>
    <div class="cont">
      <div class="cont_container">
        <div class="cont-left">
          <div class="cont-title-description">滨州医学院烟台附属医院</div>
          <div class="cont-title-description">住院病案首页</div>
          <div class="cont-title-num">病案号：00050014</div>
          <div class="cont-case">
            <div class="tr-dev">
              <div class="td-dev">
                医疗机构名称：
                <span class="td_underline" v-if="!ifFile">748397439473947389</span>
                <input class="td_input" type="text" placeholder="请输入" v-if="ifFile" />
              </div>
              <div class="td-dev">
                统一社会信用代码:
                <span class="td_underline" v-if="!ifFile">748397439473947389</span>
                <input class="td_input" type="text" placeholder="请输入" v-if="ifFile" />
              </div>
              <div class="td-dev">
                （组织机构代码：
                <span class="td_underline" v-if="!ifFile">748397439473947389</span>
                <input class="td_input" type="text" placeholder="请输入" v-if="ifFile" />
                ）
              </div>
            </div>
            <div class="tr-dev">
              <div class="td-dev">
                医疗付费方式：
                <span class="td_underline" v-if="!ifFile">748397439473947389</span>
                <input class="td_input" type="text" placeholder="请输入" v-if="ifFile" />
              </div>
              <div class="td-dev">
                健康卡号：
                <span class="td_underline" v-if="!ifFile">748397439473947389</span>
                <input class="td_input" type="text" placeholder="请输入" v-if="ifFile" />
              </div>
              <div class="td-dev">
                第
                <span class="td_underline" v-if="!ifFile">1</span>
                <input class="td_input" type="text" placeholder="请输入" v-if="ifFile" />
                次住院
              </div>
            </div>
            <div class="tr-title">患者基本信息</div>
            <div class="tr-dev">
              <div class="td-dev">
                姓名：
                <span class="td_underline" v-if="!ifFile">刘宇琛</span>
                <input class="td_input" type="text" placeholder="请输入" v-if="ifFile" />
              </div>
              <div class="td-dev">
                性别：
                <span class="td_underline" v-if="!ifFile">男</span>
                <input class="td_input" type="text" placeholder="请输入" v-if="ifFile" />
              </div>
              <div class="td-dev">
                出生日期：
                <span class="td_underline" v-if="!ifFile">1996</span>
                <input class="td_input" type="text" placeholder="请输入" v-if="ifFile" />
              </div>
              <div class="td-dev">
                年龄：
                <span class="td_underline" v-if="!ifFile">18</span>
                <input class="td_input" type="text" placeholder="请输入" v-if="ifFile" />
                <span>岁</span>
              </div>
              <div class="td-dev">
                国籍：
                <span class="td_underline" v-if="!ifFile">中国</span>
                <input class="td_input" type="text" placeholder="请输入" v-if="ifFile" />
              </div>
            </div>
            <div class="tr-dev">
              <div class="td-dev">
                出生地：
                <span class="td_underline" v-if="!ifFile">中国</span>
                <input class="td_input" type="text" placeholder="请输入" v-if="ifFile" />
              </div>
              <div class="td-dev">
                籍贯：
                <span class="td_underline" v-if="!ifFile">中国</span>
                <input class="td_input" type="text" placeholder="请输入" v-if="ifFile" />
              </div>
              <div class="td-dev">
                民族：
                <span class="td_underline" v-if="!ifFile">中国</span>
                <input class="td_input" type="text" placeholder="请输入" v-if="ifFile" />
              </div>
            </div>
            <div class="tr-dev">
              <div class="td-dev">
                身份证件类别：
                <span class="td_underline" v-if="!ifFile">中国</span>
                <input class="td_input" type="text" placeholder="请输入" v-if="ifFile" />
              </div>
              <div class="td-dev">
                证件号码：
                <span class="td_underline" v-if="!ifFile">中国</span>
                <input class="td_input" type="text" placeholder="请输入" v-if="ifFile" />
              </div>
              <div class="td-dev">
                职业：
                <span class="td_underline" v-if="!ifFile">中国</span>
                <input class="td_input" type="text" placeholder="请输入" v-if="ifFile" />
              </div>
              <div class="td-dev">
                婚姻：
                <span class="td_underline" v-if="!ifFile">中国</span>
                <input class="td_input" type="text" placeholder="请输入" v-if="ifFile" />
              </div>
            </div>
            <div class="tr-dev">
              <div class="td-dev">
                现住址 ：
                <span class="td_underline" v-if="!ifFile">中国</span>
                <input class="td_input" type="text" placeholder="请输入" v-if="ifFile" />
              </div>
              <div class="td-dev">
                电话：
                <span class="td_underline" v-if="!ifFile">中国</span>
                <input class="td_input" type="text" placeholder="请输入" v-if="ifFile" />
              </div>
              <div class="td-dev">
                邮编：
                <span class="td_underline" v-if="!ifFile">中国</span>
                <input class="td_input" type="text" placeholder="请输入" v-if="ifFile" />
              </div>
            </div>
            <div class="tr-dev">
              <div class="td-dev">
                户口地址：
                <span class="td_underline" v-if="!ifFile">中国</span>
                <input class="td_input" type="text" placeholder="请输入" v-if="ifFile" />
              </div>
              <div class="td-dev">
                邮编：
                <span class="td_underline" v-if="!ifFile">中国</span>
                <input class="td_input" type="text" placeholder="请输入" v-if="ifFile" />
              </div>
            </div>
            <div class="tr-dev">
              <div class="td-dev">
                工作单位：
                <span class="td_underline" v-if="!ifFile">中国</span>
                <input class="td_input" type="text" placeholder="请输入" v-if="ifFile" />
              </div>
              <div class="td-dev">
                地址：
                <span class="td_underline" v-if="!ifFile">中国</span>
                <input class="td_input" type="text" placeholder="请输入" v-if="ifFile" />
              </div>
              <div class="td-dev">
                电话：
                <span class="td_underline" v-if="!ifFile">中国</span>
                <input class="td_input" type="text" placeholder="请输入" v-if="ifFile" />
              </div>
              <div class="td-dev">
                邮编：
                <span class="td_underline" v-if="!ifFile">中国</span>
                <input class="td_input" type="text" placeholder="请输入" v-if="ifFile" />
              </div>
            </div>
            <div class="tr-dev">
              <div class="td-dev">
                新生儿出生体重：
                <span class="td_underline" v-if="!ifFile">中国</span>
                <input class="td_input" type="text" placeholder="请输入" v-if="ifFile" />
                <span>克</span>
              </div>
              <div class="td-dev">
                新生儿入院体重 ：
                <span class="td_underline" v-if="!ifFile">中国</span>
                <input class="td_input" type="text" placeholder="请输入" v-if="ifFile" />
              </div>
            </div>
            <div class="tr-dev">
              <div class="td-dev">
                联系人姓名：
                <span class="td_underline" v-if="!ifFile">中国</span>
                <input class="td_input" type="text" placeholder="请输入" v-if="ifFile" />
              </div>
              <div class="td-dev">
                与患者关系：
                <span class="td_underline" v-if="!ifFile">中国</span>
                <input class="td_input" type="text" placeholder="请输入" v-if="ifFile" />
              </div>
              <div class="td-dev">
                地址：
                <span class="td_underline" v-if="!ifFile">中国</span>
                <input class="td_input" type="text" placeholder="请输入" v-if="ifFile" />
              </div>
              <div class="td-dev">
                电话：
                <span class="td_underline" v-if="!ifFile">中国</span>
                <input class="td_input" type="text" placeholder="请输入" v-if="ifFile" />
              </div>
            </div>
            <div class="tr-dev">
              <div class="td-dev">
                联系人姓名：
                <span class="td_underline" v-if="!ifFile">中国</span>
                <input class="td_input" type="text" placeholder="请输入" v-if="ifFile" />
              </div>
              <div class="td-dev">
                与患者关系：
                <span class="td_underline" v-if="!ifFile">中国</span>
                <input class="td_input" type="text" placeholder="请输入" v-if="ifFile" />
              </div>
              <div class="td-dev">
                地址：
                <span class="td_underline" v-if="!ifFile">中国</span>
                <input class="td_input" type="text" placeholder="请输入" v-if="ifFile" />
              </div>
              <div class="td-dev">
                电话：
                <span class="td_underline" v-if="!ifFile">中国</span>
                <input class="td_input" type="text" placeholder="请输入" v-if="ifFile" />
              </div>
            </div>
          </div>
        </div>
        <div class="cont-reight"></div>
      </div>
    </div>
  </div>
</template>
  <script>
// import OtherComponent from '@/components/OtherComponent'
export default {
  name: 'details',
  components: {
    // OtherComponent
  },
  directives: {},
  filters: {},
  extends: {},
  mixins: {},
  props: {},
  data() {
    return {
      ifFile: false,
    };
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
    console.log(err, vm, info);
  },
  methods: {},
};
</script>
  <style lang='scss' scoped>
#MyDiv {
  margin: 0;
  padding: 0;
}
.cont {
  //   width: 1200px;
  margin: 0 auto;
  min-height: 650px;
  //   background: #ffffff;
  margin-bottom: 21px;
  padding: 0 24px;
}
.title-text {
  font-size: 16px;
  color: #333333;
  padding: 25px 0 30px 0px;
  img {
    width: 17px;
    height: 14px;
    margin-right: 8px;
  }
}
.cont_container {
  //   margin-top: 60px;
  display: flex;
  justify-content: center;
  //   flex-direction: column;
  //   align-items: center;
}
.header {
  height: 60px;
  margin: 5px 20px;
  text-align: right;
}
.cont-reight {
  width: 420px;
  min-height: 750px;
  height: 800px;
  overflow-y: scroll;
  background: #ffffff;
  border: 1px solid #e2e2e2;
  margin: 0 15px 0 0;
  //   position: fixed;
  //   top: 150px;
  //   right: 30px;
}
.cont-left {
  flex: 1;
  //   min-height: 650px;
  margin: 0 15px;
  //   height: 800px;
  //   min-width: 1200px !important;
  //   overflow-y: scroll;
  background: #ffffff;
  border: 1px solid #e2e2e2;
  //   padding: 10px;
}
.cont-title-description {
  margin: 20px;
  font-size: 24px;
  font-weight: bold;
  color: #2c3240;
  //   width: 1200px;
  text-align: center;
}
.cont-title-num {
  margin-right: 20px;
  text-align: right;
  font-size: 20px;
}
.cont-case {
  margin: 15px;
  padding: 5px;
  border: 3px solid #333333;
  min-height: 650px;
}
.tr-dev {
  display: flex;
}
.td-dev {
  flex: 1;
  height: 40px;
  color: #333333;
  line-height: 40px;
  //   background: red;
  margin: 0 5px;
  white-space: nowrap;
}
.td_underline {
  //   min-width: 150px;
  text-decoration: underline;
  padding: 0 5px;
}
.td_input {
  border: none;
  border-bottom: 1px solid #333333;
  outline: none;
}
.tr-title {
  // height: 80px;
  width: 100%;
  font-size: 20px;
  font-weight: 600;
  padding: 10px 0 10px 60px;
  background: rgb(147, 210, 243);
}
</style>
  