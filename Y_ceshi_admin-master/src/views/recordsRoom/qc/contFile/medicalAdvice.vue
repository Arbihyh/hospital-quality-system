
<template>
  <div id="MyDiv">
    <div class="cont-left">
      <div class="cont-title-description">滨州医学院烟台附属医院</div>
      <div class="cont-title-item">长期医嘱单</div>
      <div />
      <div class="cont-title-name">
        <div class="cont-title-item"><span class="cont-title-text">姓名:</span><span v-if="dataObj.info" class="cont-title-normal">{{ dataObj.info.AAA01 }}</span></div>
        <div class="cont-title-item"><span class="cont-title-text">性别:</span><span v-if="dataObj.info" class="cont-title-normal">{{ dataObj.info.AAA02C }}</span></div>
        <div class="cont-title-item"><span class="cont-title-text">年龄:</span><span v-if="dataObj.info" class="cont-title-normal">{{ dataObj.info.AAA04 }}</span></div>
        <div class="cont-title-item"><span class="cont-title-text cont-title-twidt">科别:</span><span v-if="dataObj.info" class="cont-title-normal">{{ dataObj.info.AAB02C }}</span></div>
        <div class="cont-title-item"><span class="cont-title-text">床号:</span><span class="cont-title-normal" /></div>
        <div class="cont-title-item"><span class="cont-title-text">住院号:</span><span v-if="dataObj.info" class="cont-title-normal">{{ dataObj.info.AAA28 }}</span></div>
      </div>
      <div class="table">
        <table ref="tables">
          <tr style="height:10px;">
            <td style="height:10px;" class="table-value m-all" colspan="1" />
            <td style="height:10px;" class="table-value m-all" colspan="1" />
            <td style="height:10px;" class="table-value m-all" colspan="1" />
            <td style="height:10px;" class="table-value m-all" colspan="1" />
            <td style="height:10px;" class="table-value m-all" colspan="1" />
            <td style="height:10px;" class="table-value m-all" colspan="1" />
            <td style="height:10px;" class="table-value m-all" colspan="1" />
            <td style="height:10px;" class="table-value m-all" colspan="1" />
            <td style="height:10px;" class="table-value m-all" colspan="1" />
            <td style="height:10px;" class="table-value m-all" colspan="1" />
            <td style="height:10px;" class="table-value m-all" colspan="1" />
          </tr>
          <tr>
            <td class="table-label medical-font" colspan="7">开始</td>
            <td class="table-label medical-font" colspan="4">停止</td>
          </tr>
          <tr>
            <td class="table-label medical-font" colspan="1">日期</td>
            <td class="table-label medical-font" colspan="1">时间</td>
            <td class="table-label medical-font" colspan="1">医嘱</td>
            <td class="table-label medical-font" colspan="1">剂量</td>
            <td class="table-label medical-font" colspan="1">用法</td>
            <td class="table-label medical-font" colspan="1">医师签名</td>
            <td class="table-label medical-font" colspan="1">护士签名</td>
            <td class="table-label medical-font" colspan="1">日期</td>
            <td class="table-label medical-font" colspan="1">时间</td>
            <td class="table-label medical-font" colspan="1">医师签名</td>
            <td class="table-label medical-font" colspan="1">护士签名</td>
          </tr>

          <tr v-for="(item,index) in dataObj.list" :key="index">
            <td class="table-value" colspan="1">{{ item.KSDATE }}</td>
            <td class="table-value" colspan="1">{{ item.KSTIME }}</td>
            <td class="table-value" colspan="1">{{ item.YZMC }}</td>
            <td class="table-value" colspan="1">{{ item.YCJL }}</td>
            <td class="table-value" colspan="1">{{ item.SYPC }}</td>
            <td class="table-value" colspan="1">{{ item.KZYS }}</td>
            <td class="table-value" colspan="1">{{ item.XZJDGH }}</td>
            <td class="table-value" colspan="1">{{ item.TZDATE }}</td>
            <td class="table-value" colspan="1">{{ item.TZTIME }}</td>
            <td class="table-value" colspan="1">{{ item.TZYS }}</td>
            <td class="table-value" colspan="1">{{ item.XZJDGH }}</td>
          </tr>
          <tr>
            <td class="table-value" colspan="1" />
            <td class="table-value" colspan="1" />
            <td class="table-value" colspan="2" />
            <td class="table-value" colspan="1" />
            <td class="table-value" colspan="1" />
            <td class="table-value" colspan="1" />
            <td class="table-value" colspan="1" />
            <td class="table-value" colspan="1" />
            <td class="table-value" colspan="1" />
          </tr>
          <tr>
            <td class="table-value" colspan="1" />
            <td class="table-value" colspan="1" />
            <td class="table-value" colspan="2" />
            <td class="table-value" colspan="1" />
            <td class="table-value" colspan="1" />
            <td class="table-value" colspan="1" />
            <td class="table-value" colspan="1" />
            <td class="table-value" colspan="1" />
            <td class="table-value" colspan="1" />
          </tr>
        </table>
      </div>
    </div>
  </div>
</template>
<script>
// import OtherComponent from '@/components/OtherComponent'
export default {
  name: 'PasswordManagement',
  directives: {},
  filters: {},
  extends: {},
  mixins: {},
  props: {
    dataObj: {
      type: Object,
      default: null
    }
  },
  data() {
    return {
      ifFile: false
    }
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
    console.log(err, vm, info)
  },
  methods: {}
}
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
    padding: 20px 0 10px 0;
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
    .cont-title-name{
      width: 100%;
      height: 50px;
      display: flex;
      // align-items: flex-end;
      justify-content: space-between;
      .cont-title-item{
        flex: 1;
        text-align: left;
        display: flex;
        justify-content: left;
      }
      .cont-title-text{
        font-weight: bold;
      }
      .cont-title-twidt{
        width: 35px;
      }
      .cont-title-normal{
        font-weight: normal;
        margin-left: 5px;
      }
    }
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
  .medical-font{
    font-weight: bold;
  }
  </style>

