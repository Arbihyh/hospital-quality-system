<template>
  <div id="MyDiv">
    <div class="cont-left">
      <div class="cont-title-description">滨州医学院烟台附属医院</div>
      <div class="cont-title-item">病程记录</div>
      <div>
        <el-row type="flex" class="row-bg" justify="space-around">
          <el-col :span="6">
            <div class="refachInput">
              <span ref="UNT_ID">科室:</span>
              <span class="refachInput-text">{{ data.UNT_ID?data.UNT_ID:'暂无' }}</span>
            </div>
          </el-col>
          <el-col :span="6">
            <div class="refachInput">
              <span ref="AAA01">姓名:</span>
              <span class="refachInput-text">{{ data.AAA01?data.AAA01:'暂无' }}</span>
            </div>
          </el-col>
          <el-col :span="6">
            <div class="refachInput">
              <span ref="AAA27">床位:</span>
              <span class="refachInput-text">{{ data.AAA01?data.AAA01:'暂无' }}</span>
            </div>
          </el-col>
          <el-col :span="6">
            <div class="refachInput">
              <span ref="AAA27">住院号:</span>
            </div>
          </el-col>
        </el-row>
      </div>
      <div class="table">
        <table ref="tables">
          <tr>
            <td class="table-value m-all" colspan="1" />
            <td class="table-value m-all" colspan="1" />
            <td class="table-value m-all" colspan="1" />
            <td class="table-value m-all" colspan="1" />
            <td class="table-value m-all" colspan="1" />
            <td class="table-value m-all" colspan="1" />
            <td class="table-value m-all" colspan="1" />
            <td class="table-value m-all" colspan="1" />
            <td class="table-value m-all" colspan="1" />
            <td class="table-value m-all" colspan="1" />
          </tr>
          <tr>
            <td class="table-value m-all" colspan="2">2022年09月01日（时：分)</td>
            <td class="table-value m-all" colspan="2" />
            <td class="table-value m-all" colspan="2" />
            <td class="table-value m-all" colspan="2" />
            <td class="table-value m-all" colspan="2">首次病程记录</td>
          </tr>
          <tr>
            <td class="table-label" style="height: 80px" colspan="2">病历特点:</td>
            <td class="table-value" style="height: 80px" colspan="8" />
          </tr>
          <tr>
            <td class="table-label" style="height: 80px" colspan="2">初步诊断:</td>
            <td class="table-value" style="height: 80px" colspan="8" />
          </tr>
          <tr>
            <td class="table-label" style="height: 120px" colspan="2">诊断依据:</td>
            <td class="table-value" style="height: 120px" colspan="8" />
          </tr>
          <tr>
            <td class="table-label" style="height: 120px" colspan="2">鉴别诊断:</td>
            <td class="table-value" style="height: 120px" colspan="8" />
          </tr>
          <tr>
            <td class="table-label" style="height: 120px" colspan="2">诊疗计划:</td>
            <td class="table-value" style="height: 120px" colspan="8" />
          </tr>
          <tr class="m-all">
            <td class="table-value m-all" colspan="8" style="height: 60px" />
            <td class="table-value m-all" colspan="1">医生签名：</td>
            <td class="table-value m-all" colspan="1" />
          </tr>
          <tr class="m-all">
            <td class="table-value m-all" colspan="2">2022年09月01日（时：分)</td>
            <td class="table-value m-all" colspan="2" />
            <td class="table-value m-all" colspan="2" />
            <td class="table-value m-all" colspan="2" />
            <td class="table-value m-all" colspan="2">医师查房记录</td>
          </tr>
          <tr>
            <td class="table-value m-all" style="height: 180px" colspan="10" />
          </tr>
          <tr class="m-all">
            <td class="table-value m-all" colspan="2">2022年09月01日（时：分)</td>
            <td class="table-value m-all" colspan="2" />
            <td class="table-value m-all" colspan="2" />
            <td class="table-value m-all" colspan="2" />
            <td class="table-value m-all" colspan="2">医师查房记录</td>
          </tr>
          <tr>
            <td class="table-value m-all" style="height: 180px" colspan="10" />
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
      <style lang='scss' scoped>
#MyDiv {
  padding: 0 15px;
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
</style>
