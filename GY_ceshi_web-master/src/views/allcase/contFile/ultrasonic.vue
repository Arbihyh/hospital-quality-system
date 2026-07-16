<template>
  <div id="MyDiv">
    <div class="cont-left">
      <div class="cont-title-description">滨州医学院烟台附属医院</div>
      <div class="cont-title-item">超声诊断报告</div>
      <div>
        <el-row type="flex" class="row-bg" justify="space-around">
          <el-col :span="1">
            <div class="refachInput">
              <span ref="UNT_ID">编号:</span>
              <!-- data.AAA27 -->
            </div>
          </el-col>
          <el-col :span="1">
            <div class="refachInput">
              <span ref="AAA27"></span>
            </div>
          </el-col>
          <el-col :span="1">
            <div class="refachInput">
              <span ref="AAA27"></span>
            </div>
          </el-col>
          <el-col :span="2">
            <div class="refachInput">
              <span ref="AAA27">病人号:</span>
            </div>
          </el-col>
        </el-row>
      </div>
      <div class="table">
        <table ref="tables">
          <tr>
            <td class="table-value m-all" colspan="1"></td>
            <td class="table-value m-all" colspan="1"></td>
            <td class="table-value m-all" colspan="1"></td>
            <td class="table-value m-all" colspan="1"></td>
            <td class="table-value m-all" colspan="1"></td>
            <td class="table-value m-all" colspan="1"></td>
            <td class="table-value m-all" colspan="1"></td>
            <td class="table-value m-all" colspan="1"></td>
            <td class="table-value m-all" colspan="1"></td>
            <td class="table-value m-all" colspan="1"></td>
          </tr>
          <tr>
            <td class="table-label" colspan="1">患者姓名:</td>
            <td class="table-value" colspan="2"></td>
            <td class="table-label" colspan="1">性别:</td>
            <td class="table-value" colspan="1"></td>
            <td class="table-label" colspan="1">年龄:</td>
            <td class="table-value" colspan="1"></td>
            <td class="table-label" colspan="1">住院号码:</td>
            <td class="table-value" colspan="2"></td>
          </tr>
          <tr style="border-left: 1px solid #dddddd; border-right: 1px solid #dddddd; border-bottom: 1px solid #dddddd">
            <td class="table-value m-all" style="height: 300px" colspan="1">超声所见:</td>
            <td class="table-value m-all" colspan="9"></td>
          </tr>
          <tr style="border-left: 1px solid #dddddd; border-right: 1px solid #dddddd">
            <td class="table-value m-all" style="height: 300px" colspan="1">超声提示:</td>
            <td class="table-value m-all" colspan="9"></td>
          </tr>
          <tr style="border-left: 1px solid #dddddd; border-right: 1px solid #dddddd; border-bottom: 1px solid #dddddd">
            <td class="table-value m-all" colspan="1">检查医生:</td>
            <td class="table-value m-all" colspan="1"></td>
            <td class="table-value m-all" colspan="1">审核医生:</td>
            <td class="table-value m-all" colspan="1"></td>
            <td class="table-value m-all" colspan="1">录入员:</td>
            <td class="table-value m-all" colspan="1"></td>
            <td class="table-value m-all" colspan="1">会诊医师:</td>
            <td class="table-value m-all" colspan="3"></td>
          </tr>
          <tr style="border-left: 1px solid #dddddd; border-right: 1px solid #dddddd; border-bottom: 1px solid #dddddd">
            <td class="table-value m-all" colspan="3">本诊断报告仅供临床参考，报告仅此一份，复诊时带来</td>
            <td class="table-value m-all" colspan="1">检查日期:</td>
            <td class="table-value m-all" colspan="6"></td>
          </tr>
        </table>
      </div>
    </div>
  </div>
</template>
        <script>
// import OtherComponent from '@/components/OtherComponent'
export default {
  name: 'passwordManagement',
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
      data: '',
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
  // padding:0 15px;
  margin: 0 15px;
}
.cont-title {
  display: flex;
  justify-content: space-between;
  span {
    padding: 5px;
  }
}
.cont-title-item {
  width: 100%;
  margin: 0 auto;
  display: flex;
  justify-content: center;
  font-size: 20px;
  padding: 50px 0 10px 0;
  font-weight: 600;
}
.cont-left {
  //   flex: 1;
  //   min-height: 650px;
  //   margin: 0 15px;
  //   height: 800px;
  //   overflow-y: scroll;
  background: #ffffff;
  // border: 1px solid #e2e2e2;
  padding: 10px;
}
.cont-title-description {
  margin: 20px;
  font-size: 24px;
  font-weight: bold;
  color: #2c3240;
  //   width: 1200px;
  text-align: center;
}
table {
  min-width: 1000px;
  table-layout: fixed;
  empty-cells: show;
  border-collapse: collapse;
  margin: 0 auto;
  margin-bottom: 30px;
  td {
    border: 1px solid #dddddd;
    // padding-left: 16px;
    text-align: center;
    font-size: 12px;
    box-sizing: border-box;
  }
  .table-label {
    width: 150px;
    height: 40px;
    color: #666666;
    background-color: #f9f9f9;
  }
  .table-value {
    width: 210px;
    height: 40px;
    color: #333333;
    // padding-right: 16px;
    text-align: center;
  }
}
.m-all {
  border: none;
}
.m-left {
  border-left: none;
}
.m-right {
  border-right: none;
}
</style>
      
      