<template>
  <!-- 发病时间页面 -->
  <div class="box">
    <el-row :gutter="16">
      <!-- 左侧菜单 -->
      <el-col :span="8">
        <div class="box_wrapper" :style="{ height: boxWrapperHeight }">
          <el-input placeholder="输入关键字进行过滤" v-model="filterText"></el-input>
          <el-tree
            class="filter-tree"
            node-key="id"
            highlight-current
            :data="menus"
            :props="defaultProps"
            :filter-node-method="filterNode"
            ref="tree"
            @node-click="handleNodeClick"
            :current-node-key="ruleId"
            :default-expanded-keys="[ruleId]"
          ></el-tree>
        </div>
      </el-col>
      <!-- 右侧列表 -->
      <el-col :span="16">
        <div class="box_wrapper" :style="{ height: boxWrapperHeight }">
          <el-form :inline="true" :model="formInline" class="demo-form-inline">
            <el-form-item label="查询时间">
              <el-date-picker
                v-model="formInline.year"
                :clearable="false"
                type="year"
                :picker-options="pickerOptions"
                format="yyyy年"
                value-format="yyyy"
                placeholder="选择年份"
              ></el-date-picker>
            </el-form-item>
            <el-form-item>
              <el-button type="primary" @click="onSearch">查询</el-button>
            </el-form-item>
            <el-form-item style="float: right">
              <el-button v-if="$route.query.type === 'children'" @click="onBack">返回</el-button>
            </el-form-item>
          </el-form>
          <el-table :data="tableData" style="width: 100%">
            <el-table-column prop="name" label="名称" show-overflow-tooltip></el-table-column>
            <el-table-column prop="time" label="日期" width="200"></el-table-column>
            <el-table-column prop="denominator" label="数据" width="200">
              <template slot-scope="scope">
                <span class="link" @click="toCaseIndexPage(scope.row)">{{ scope.row.denominator }}</span>
              </template>
            </el-table-column>
            <el-table-column prop="" label="来源" width="200">
              <template slot-scope="scope">
                <span :class="{ green: scope.row.source === '人工录入' }">{{ scope.row.source }}</span>
              </template>
            </el-table-column>
          </el-table>
        </div>
      </el-col>
    </el-row>
  </div>
</template>

<script>
export default {
  data() {
    return {
      menus: [
        {
          id: 60,
          name: '指标一，脑梗发病时间',
        },
        {
          id: 61,
          name: '指标二，心肌梗死发病时间',
        },
        {
          id: 62,
          name: '指标三，发病4.5小时内脑梗死患者静脉溶栓率',
        },
      ],
      formInline: {
        year: '',
      },
      tableData: [],
      filterText: '',
      defaultProps: {
        children: 'children',
        label: 'name',
      },
      ruleId: 60,
      ruleName: '脑梗发病时间',
      time: new Date(),
      pickerOptions: {
        disabledDate(time) {
          const date = new Date();
          const year = date.getFullYear();
          const timeYear = time.getFullYear();
          return year < timeYear;
        },
      },
    };
  },
  created() {
    // 获取当前时间
    this.formInline.year = new Date().getFullYear().toString();

    this.getList();
  },
  computed: {
    // 判断是取分子还是分母
    judgeNum() {
      const str = `${this.ruleId}`;
      const length = str.length;
      const lastNum = str[length - 1];
      if (Number(lastNum) > 1) {
        // 分母
        return 'denominator';
      } else {
        // 分子
        return 'numerator';
      }
    },
    menuList() {
      if (this.$route.path === '/embedIndex-home') {
        return this.menus2;
      } else {
        return this.$route.query.type === 'children' ? this.cMenus : this.menus;
      }
    },
    judgeEdit() {
      return this.greenColorMenus.includes(this.ruleId) && !!this.tableData.length;
    },
    boxWrapperHeight() {
      return this.$route.path === '/embedIndex-home' ? '815px' : '885px';
    },
  },
  watch: {
    filterText(val) {
      // 树上面的筛选
      this.$refs.tree.filter(val);
    },
  },
  methods: {
    // 返回
    onBack() {
      this.$router.go(-1);
    },
    // 病案指标列表跳转
    toCaseIndexPage(row) {
      const { time, source } = row;
      let path = '/reviewIndicatorsList';
      this.$router.push({ path, query: { year: this.formInline.year, time, ruleId: Number(`${this.ruleId}`.slice(0, 2)), stamp: Date.now() } });
    },
    // 菜单筛选
    filterNode(value, data) {
      if (!value) return true;
      return data.name.indexOf(value) !== -1;
    },
    handleNodeClick(data) {
      const { id, name } = data;
      this.ruleId = id;
      if (id == 60) {
        this.ruleName = '脑梗发病时间';
      }

      if (id == 61) {
        this.ruleName = '心肌梗死发病时间';
      }
      this.getList();
      console.log('vvvvvv');
      console.log(name);
      // if (id > 10 && this.formInline.year) {
      //   this.getList();
      // }
    },
    // 获取右侧列表数据
    getList() {
      const { year } = this.formInline;
      const params = {
        start_time: `${year}0101`,
        end_time: `${year}1231`,
        year,
        type: Number(this.ruleId),
      };

      params.request_source = 2;
      this.$axios2.post('/get_assessment_indicators', params).then(res => {
        if (Array.isArray(res.data)) {
          res.data.map(item => {
            item.name = this.ruleName;
            item.ruleId = this.ruleId;
          });
          this.tableData = res.data;
        } else {
          this.tableData = [];
        }
      });
    },
    // 查询
    onSearch() {
      const { year } = this.formInline;
      console.log(year);
      if (!year) {
        this.$message.error('请选择查询时间');
        return;
      }
      if (!this.ruleId) {
        this.$message.error('请选择查询指标');
        return;
      }
      this.getList();
    },
  },
};
</script>

<style lang="scss" scoped>
.table_edit {
  margin-left: 10px;
  cursor: pointer;
  &:hover {
    opacity: 0.6;
  }
}
.link {
  font-weight: 600;
  color: #409eff;
}

.pointer {
  cursor: pointer;
}
.box {
  padding: 0 16px 16px 16px;
  .box_wrapper {
    padding: 16px;
    background: #fff;
    border-radius: 5px;
    overflow-x: hidden;
    overflow-y: auto;
  }
}
.filter-tree {
  margin-top: 16px;
  ::v-deep .el-tree-node__content {
    height: 36px;
    line-height: 36px;
  }
}

::v-deep.el-table .el-table__header tr th {
  background: #f1f6ff;
  color: #13171e;
  border-bottom: 0px;
}
::v-deep.el-table .el-table__row td {
  color: #7e8bab;
  border-bottom: 1px solid #f4f4f4;
}
::v-deep.el-table .el-table__header tr th:first-child {
  border-radius: 5px 0px 0px 5px;
}
::v-deep.el-table .el-table__header tr th:nth-child(3) {
  border-radius: 0px 5px 5px 0px;
}
.green {
  color: #67c23a;
}
</style>
