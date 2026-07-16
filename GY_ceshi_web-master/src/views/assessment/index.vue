<template>
  <div id="MyDiv" style="padding: 16px;">
    <div class="cont">
      <div class="cont_container">
        <el-form :inline="true" :model="formInline" class="demo-form-inline">
          <el-form-item label="考核指标">
            <el-input v-model="formInline.user" placeholder="请输入考核指标"></el-input>
          </el-form-item>
          <el-form-item label="指标类型">
            <el-select v-model="formInline.region" placeholder="请选择指标类型">
              <el-option label="出院患者手术占比" value="0"></el-option>
              <el-option label="出院患者四级手术占比" value="1"></el-option>
              <el-option label="I类切口手术部位感染率" value="2"></el-option>
              <el-option label="出院患者微创手术占比" value="3"></el-option>
              <el-option label="手术患者并发症发生率" value="4"></el-option>
            </el-select>
          </el-form-item>
          <el-form-item label="时间">
            <el-date-picker
              class="width500"
              v-model="formInline.rangeDate"
              size="large"
              type="daterange"
              range-separator="-"
              start-placeholder="开始日期"
              end-placeholder="结束日期"
              format="yyyy 年 MM 月 dd 日"
              value-format="yyyyMMdd"
            ></el-date-picker>
          </el-form-item>
          <el-form-item>
            <el-button type="primary" @click="funQuery(formInline.region)">查询</el-button>
          </el-form-item>
          <el-form-item>
            <el-button @click="resetForm()">重置条件</el-button>
          </el-form-item>
          <el-form-item>
            <el-button type="primary" icon="el-icon-download" class="export-btn">导出数据</el-button>
          </el-form-item>
        </el-form>
      </div>
      <div class="tableData">
        <el-table :data="tableData" border style="width: 100%">
          <el-table-column type="expand">
      <template slot-scope="props">
        <el-form label-position="left" inline class="demo-table-expand">
          <el-form-item :label="props.row.denominatorname">
            <span>{{ props.row.denominator }}</span>
          </el-form-item>
          <el-form-item :label="props.row.numeratorName">
            <span>{{ props.row.numerator }}</span>
          </el-form-item>
        </el-form>
      </template>
    </el-table-column>
        <el-table-column type="index" label="序号" width="70px">
        </el-table-column>
        <el-table-column prop="name" label="指标名称"></el-table-column>
        <el-table-column prop="AAA01" label="单位"></el-table-column>
        <el-table-column prop="AAA01" label="分工部门"></el-table-column>
        <el-table-column prop="AAA01" label="指标类型"></el-table-column>
        <el-table-column prop="res" label="得分"></el-table-column>
      </el-table>
      <!-- 分页控制 -->
      <!-- <el-pagination
        v-if="tableData && tableData.length !== 0"
        @size-change="SizeChangeEvent"
        @current-change="pageHasChanged"
        :total="paginationData.total"
        background
        class="table-pagination"
        style="margin: 15px 0px"
        :page-size="paginationData.pageSize"
        :current-page.sync="paginationData.currentPage"
        layout="total, sizes, prev, pager, next, jumper"
      ></el-pagination> -->
      </div>
    </div>
  </div>
</template>
    <script>
export default {
  name: 'assessment',
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
      formInline: {
        user: '',
        region: '0',
        rangeDate: [],
      },
      tableData:[
        {
          index:0,
          name:'出院患者手术占比',
          denominator:'212', //分子
          numerator:'2121', //分母
          res:'12', // 比例
          denominatorname:'出院患者手术台次数',
          numeratorName:'同期出院患者总人次数',
        },
        {
          index:1,
          name:'出院患者四级手术占比',
          denominator:'', //分子
          numerator:'', //分母
          denominatorname:'出院患者四级手术台次数',
          numeratorName:'同期出院患者手术台次数',
          res:'', // 比例
        },
        {
          index:2,
          name:'I类切口手术部位感染率',
          denominator:'', //分子
          numerator:'', //分母
          denominatorname:'I类切口手术部位感染人次数',
          numeratorName:'同期I类切口手术台次数',
          res:'', // 比例
        },
        {
          index:3,
          name:'出院患者微创手术占比',
          denominator:'', //分子
          numerator:'', //分母
          denominatorname:'出院患者微创手术台次数',
          numeratorName:'同期出院患者手术台次数',
          res:'', // 比例
        },
        {
          index:4,
          name:'手术患者并发症发生率',
          denominator:'', //分子
          numerator:'', //分母
          denominatorname:'手术患者并发症发生例数',
          numeratorName:'同期出院的手术患者人数',
          res:'', // 比例
        }
      ],
      paginationData: {
        total: 10,
        currentPage: 1,
        pageSize: 10,
      },
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
    for(let i = 0 ;i< 5;i++){
      // this.formInline.region = i
      this.funQuery(i)
    }
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
  methods: {
    funQuery(i){
      let pramse = {
        start_time:this.formInline.rangeDate[0], // 开始时间
        end_time:this.formInline.rangeDate[1], //结束时间
        type:i, //要获取的数据，0出院患者手术占比，1出院患者四级手术占比，2I类切口手术部位感染率，3出院患者微创手术占比，4
      }
      console.log(i)
      console.log(this.formInline.region)
      this.$axios2.post('/get_assessment_indicators',pramse).then(res=>{
          this.tableData[i].denominator =res.data.denominator
          this.tableData[i].numerator =res.data.numerator
          this.tableData[i].res =res.data.res
      })
    },
    SizeChangeEvent(val) {
      this.paginationData.pageSize = val;
      this.funQuery();
    },
    pageHasChanged() {
      this.funQuery();
    },
    resetForm() {
        Object.assign(this.$data.formInline, this.$options.data().formInline);
      }
  },
};
</script>
    <style lang='scss' scoped>
#MyDiv {
  margin: 0;
  padding: 0;
}
.cont {
  // width: 1200px;
  margin: 0 auto;
  min-height: 850px;
  background: #ffffff;
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
    padding-top: 30px;
  display: flex;
  justify-content: center;
  flex-direction: column;
  align-items: center;
}
.tableData{
    margin-top:60px;
}
</style>
    