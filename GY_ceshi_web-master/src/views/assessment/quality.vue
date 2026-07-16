<template>
  <div id="MyDiv" style="padding: 16px;">
    <div class="cont">
      <div class="cont_container">
        <div class="query">
          <div class="scds">
            <el-input placeholder="请选择内容" v-model="formData.state" suffix-icon="el-icon-search"></el-input>
          </div>
          <!-- <div class="Leftlist"> -->
          <div class="leftData-case">
            <el-tree :data="leftData" :props="defaultProps"  @node-click="handleNodeClick" highlight-current></el-tree>
          </div>
        </div>
        <div class="tableData">
          <div class="right-case">
            <el-form :inline="true" :model="formInline" class="demo-form-inline">
              <el-form-item label="时间">
                <el-date-picker v-model="formInline.rangeDate" type="year" placeholder="选择年"></el-date-picker>
              </el-form-item>
              <el-form-item>
                <el-button type="primary" @click="funQuery">查询</el-button>
              </el-form-item>
            </el-form>
          </div>
          <div class="right-tableData">
            <div class="tableData">
              <el-table :data="tableData" border style="width: 100%">
                <el-table-column type="index" label="序号" width="70px"></el-table-column>
                <el-table-column prop="label" label="名称">
                </el-table-column>
                <el-table-column prop="year" label="时间">
                  <template slot-scope="props">{{ props.row.year }}-{{ props.row.month }}</template>
                </el-table-column>
                <el-table-column prop="dead_radio" label="数据"></el-table-column>
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
      formData: {
        state: '',
      },
      formInline: {
        rangeDate: '',
        type: '',
      },
      defaultProps: {
        children: 'children',
        label: 'label',
        color: '0',
      },
      leftData: [
        {
          label: '急性心肌梗死',
          num: '1',
          color: '1',
        },
        {
          label: '心力衰竭',
          num: '2',
          color: '0',
        },
        {
          label: '肺炎（住院、成人）',
          num: '3',
          color: '0',
        },
        {
          label: '肺炎（住院、儿童）',
          num: '4',
          color: '0',
        },
        {
          label: '脑梗死，6髋关节置换术',
          num: '5',
          color: '0',
        },
        {
          label: '髋关节置换术',
          num: '6',
          color: '0',
        },
        {
          label: '膝关节置换术',
          num: '7',
          color: '0',
        },
        {
          label: '冠状动脉旁路移植术',
          num: '8',
          color: '0',
        },
        {
          label: '剖宫产',
          num: '9',
          color: '0',
        },
        {
          label: '慢性阻塞性肺疾病',
          num: '10',
          color: '0',
        },
      ],
      // "description": "病种类型下标，1急性心肌梗死，2心力衰竭，3肺炎（住院、成人），4肺炎（住院、儿童），5脑梗死，6髋关节置换术，7膝关节置换术，8冠状动脉旁路移植术，9剖宫产，10慢性阻塞性肺疾病",
      tableData: [],
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
    funQuery() {
      if (!this.formInline.type) {
        this.$message.error('请选择单病种类型');
        return;
      }
      let pramse = {
        year: this.formInline.rangeDate,
        type: this.formInline.type.num,
      };
      this.$axios.post('get_illness_type', pramse).then(res => {
        console.log(res);
        this.tableData = res.data;
        for(let item in this.tableData){
          this.tableData[item].label = this.formInline.type.label
        }
      });
    },
    handleNodeClick(data) {
      console.log(data);
      this.formInline.type = data;
      // this.formData.state = data.label;
      this.funQuery();
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
    },
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
  //   margin: 0 auto;
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
  //   justify-content: center;
  //   flex-direction: column;
  //   align-items: center;
}
.tableData {
  margin-top: 60px;
}
.query {
  width: 400px;
  height: 100px;
  // background: red;
}
.tableData {
  flex: 1;
  margin: 0 30px;
}
.leftData-case {
  margin: 50px 0;
  width: 330px;
  height: 750px;
  overflow: scroll;
}
.right-case {
  padding: 0 150px;
}
.span_color {
  color: red;
}
</style>
      