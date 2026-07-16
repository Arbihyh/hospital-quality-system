<template>
  <div class="box">
    <el-row :gutter="16">
      <!-- 左侧菜单 -->
      <el-col :span="8">
        <div class="box_wrapper" :style="{'height': boxWrapperHeight}">
          <el-input placeholder="输入关键字进行过滤" v-model="filterText"></el-input>
          <el-tree
            class="filter-tree"
            node-key="id"
            highlight-current
            :data="showMenus"
            :props="defaultProps"
            :filter-node-method="filterNode"
            ref="tree"
            @node-click="handleNodeClick"
            :current-node-key="ruleId"
            :default-expanded-keys="[ruleId]"
          >
            <span class="custom-tree-node" slot-scope="{ node }">
              <span>{{ node.label }}</span>
            </span>
          </el-tree>
        </div>
      </el-col>
      <!-- 右侧列表 -->
      <el-col :span="16">
        <div class="box_wrapper" :style="{'height': boxWrapperHeight}">
          <el-form :inline="true" :model="formInline" class="demo-form-inline">
            <el-form-item label="">
              <el-date-picker v-model="formInline.start_time" type="date" :picker-options="pickerOptions1" placeholder="开始日期"></el-date-picker>
            </el-form-item>
            <el-form-item label="">
              <el-date-picker v-model="formInline.end_time" type="date" :picker-options="pickerOptions2" placeholder="结束日期"></el-date-picker>
            </el-form-item>
            <el-form-item>
              <el-button type="primary" @click="onSearch">查询</el-button>
            </el-form-item>
          </el-form>
          <!-- 排名柱状图 -->
          <div class="rank-box">
            <Title :title="'Top 10'" style="margin-bottom: 0" />
            <div v-if="rankData.length" id="myChart1" style="width: 100%; height: 400px"></div>
            <el-empty v-else description="暂无排名数据"></el-empty>
          </div>
          <div class="table-box">
            <Title :title="'科室排名'" />
            <el-table :data="tableData" style="width: 100%">
              <el-table-column type="index" label="序号" width="80" align="center"></el-table-column>
              <el-table-column prop="dep_name" label="出院科室"></el-table-column>
              <el-table-column prop="" label="" align="center">
                <template slot="header" slot-scope="scope">
                  <span>{{ scope._self.ruleName }}</span>
                </template>
                <template slot-scope="scope">
                  <span>{{ (scope.row.res * 100).toFixed(2) + '%' }}</span>
                </template>
              </el-table-column>
              <el-table-column prop="numerator" label="正确病历数" align="center">
                <template slot-scope="scope">
                  <span class="link" @click="toPage(scope.row, 1)">{{ scope.row.numerator }}</span>
                </template>
              </el-table-column>
              <el-table-column prop="denominator" label="病历总数" align="center">
                <template slot-scope="scope">
                  <span class="link" @click="toPage(scope.row, 0)">{{ scope.row.denominator }}</span>
                </template>
              </el-table-column>
            </el-table>
          </div>
        </div>
      </el-col>
    </el-row>
  </div>
</template>

<script>
import * as echarts from 'echarts';
import Title from '@/components/Title';
import { dateFormat } from '@/utils'

export default {
  components: {
    Title,
  },
  data() {
    return {
      menus: [
        {
          id: 2,
          name: '二、病历书写时效性指标',
          children: [
            {
              id: 21,
              name: '指标四、入院记录 24 小时内完成率',
            },
            {
              id: 22,
              name: '指标五、手术记录24小时内完成率',
            },
            {
              id: 23,
              name: '指标六、出院记录24小时内完成率',
            },
            {
              id: 24,
              name: '指标七、病案首页24小时内完成率',
            },
          ],
        },
        {
          id: 3,
          name: '三、重大检查记录符合率',
          children: [
            {
              id: 31,
              name: '指标八、CT/MRI检查记录符合率',
            },
            {
              id: 32,
              name: '指标九、病理检查记录符合率',
            },
            {
              id: 33,
              name: '指标十、细菌培养检查记录符合率',
            },
          ],
        },
        {
          id: 4,
          name: '四、诊疗行为记录符合率',
          children: [
            {
              id: 41,
              name: '指标十一、抗菌药物使用记录符合率',
            },
            {
              id: 42,
              name: '指标十二、恶性肿瘤化学治疗记录符合率',
            },
            {
              id: 43,
              name: '指标十三、恶性肿瘤放射治疗记录符合率',
            },
            {
              id: 44,
              name: '指标十四、手术相关记录完整率',
            },
            {
              id: 45,
              name: '指标十五、植入物相关记录符合率',
            },
            {
              id: 46,
              name: '指标十六、临床用血相关记录符合率',
            },
            {
              id: 47,
              name: '指标十七、医师查房记录完整率',
            },
            {
              id: 48,
              name: '指标十八、患者抢救记录及时完成率',
            },
            {
              id: 49,
              name: 'MER-D&T-08-1 患者抢救成功率',
            },
          ],
        },
        {
          id: 5,
          name: '五、病历归档质量指标',
          children: [
            {
              id: 51,
              name: '指标十九、出院患者病历2日归档率',
            },
            {
              id: 52,
              name: '指标二十、出院患者病历归档完整率',
            },
            {
              id: 53,
              name: '指标二十一、主要诊断填写正确率',
            },
            {
              id: 54,
              name: '指标二十二、主要诊断编码正确率',
            },
            {
              id: 55,
              name: '指标二十三、主要手术填写正确率',
            },
            {
              id: 56,
              name: '指标二十四、主要手术编码正确率',
            },
            {
              id: 57,
              name: '指标二十五、不合理复制病历发生率',
            },
            {
              id: 58,
              name: '指标二十六、知情同意书规范签署率',
            },
            {
              id: 59,
              name: '指标二十七、甲级病历率',
            },
          ],
        },
      ],
      // 医院嵌入需显示指标4/6/7/8/10/11/12/13/15/16/18/20/22/24/26
      menus2: [
        {
          id: 2,
          name: '二、病历书写时效性指标',
          children: [
            {
              id: 21,
              name: '指标四、入院记录 24 小时内完成率',
            },
            {
              id: 23,
              name: '指标六、出院记录24小时内完成率',
            },
            {
              id: 24,
              name: '指标七、病案首页24小时内完成率',
            },
          ],
        },
        
        {
          id: 3,
          name: '三、重大检查记录符合率',
          children: [
            {
              id: 31,
              name: '指标八、CT/MRI检查记录符合率',
            },
            {
              id: 33,
              name: '指标十、细菌培养检查记录符合率',
            },
          ],
        },
        {
          id: 4,
          name: '四、诊疗行为记录符合率',
          children: [
            {
              id: 41,
              name: '指标十一、抗菌药物使用记录符合率',
            },
            {
              id: 42,
              name: '指标十二、恶性肿瘤化学治疗记录符合率',
            },
            {
              id: 43,
              name: '指标十三、恶性肿瘤放射治疗记录符合率',
            },
            {
              id: 45,
              name: '指标十五、植入物相关记录符合率',
            },
            {
              id: 46,
              name: '指标十六、临床用血相关记录符合率',
            },
            {
              id: 48,
              name: '指标十八、患者抢救记录及时完成率',
            },
          ],
        },
        {
          id: 5,
          name: '五、病历归档质量指标',
          children: [
            {
              id: 51,
              name: '指标十九、出院患者病历2日归档率',
            },
            {
              id: 53,
              name: '指标二十一、主要诊断填写正确率',
            },
            {
              id: 55,
              name: '指标二十三、主要手术填写正确率',
            },
            {
              id: 57,
              name: '指标二十五、不合理复制病历发生率',
            }
          ],
        },
      ],
      filterText: '',
      defaultProps: {
        children: 'children',
        label: 'name',
      },
      ruleId: '',
      ruleName: '--',
      pickerOptions1: {
        disabledDate: time => {
          if (this.formInline.end_time) {
            return time.getTime() > new Date(this.formInline.end_time).getTime();
          } else {
            return time.getTime() > Date.now();
          }
        },
      },
      pickerOptions2: {
        disabledDate: time => {
          if (this.formInline.start_time) {
            return time.getTime() < new Date(this.formInline.start_time).getTime();
          } else {
            return time.getTime() > Date.now();
          }
        },
      },
      formInline: {
        time: '',
        start_time: '',
        end_time: '',
      },
      tableData: [],
      rankData: [],
      myChart: ''
    };
  },
  created() {
    const currentYear = new Date().getFullYear().toString()
    this.formInline.start_time = new Date(`${currentYear}-01-01 00:00:00`)
    this.formInline.end_time = new Date()
    const menus = this.$route.path === '/embedIndex-home' ? this.menus2 : this.menus
    this.ruleId = menus[0].children[0].id
    this.ruleName = menus[0].children[0].name
    this.getList()
    this.getRankList()
  },
  computed: {
    boxWrapperHeight() {
      return this.$route.path === '/embedIndex-home' ? '815px' : '885px'
    },
    showMenus() {
      return this.$route.path === '/embedIndex-home' ? this.menus2 : this.menus
    }
  },
  activated() {
    if (this.myChart) {
      this.myChart.resize();
    }
  },
  methods: {
    // 菜单筛选
    filterNode(value, data) {
      if (!value) return true;
      return data.name.indexOf(value) !== -1;
    },
    // 菜单点击
    handleNodeClick(data) {
      const { id, name } = data;
      if (id > 10) {
        this.ruleId = id;
        this.ruleName = name.split('、')[1];
        this.getList();
        this.getRankList();
      }
    },
    // 搜索
    onSearch() {
      if (this.ruleId) {
        this.getList();
        this.getRankList();
      } else {
        this.$message.error('请选择指标！');
      }
    },
    // 获取科室排名
    getList() {
      const { start_time, end_time } = this.formInline;
      const params = {
        type: this.ruleId,
        page: 1,
        page_size: 1000,
        start_time: start_time ? dateFormat(start_time, 'YYYYMMDD') : '',
        end_time: end_time ? dateFormat(end_time, 'YYYYMMDD') : ''
      };

      this.$axios2.post('/dep_statistics', params).then(res => {
        if (Array.isArray(res.data.data)) {
          this.tableData = res.data.data;
        } else {
          this.tableData = [];
        }
      });
    },
    // 获取排名rank10
    getRankList() {
      const { start_time, end_time } = this.formInline;
      const params = {
        type: this.ruleId,
        page: 1,
        page_size: 10,
        start_time: start_time ? dateFormat(start_time, 'YYYYMMDD') : '',
        end_time: end_time ? dateFormat(end_time, 'YYYYMMDD') : ''
      };
      this.$axios2.post('/dep_statistics', params).then(res => {
        if (Array.isArray(res.data.data)) {
          this.rankData = res.data.data;
        } else {
          this.rankData = [];
        }
        if (this.rankData.length) {
          this.$nextTick(() => {
            this.initCharts1();
          });
        }
      });
    },
    // 柱状图
    initCharts1() {
      let dataName = [];
      let dataFleg = [];
      for (let item in this.rankData) {
        dataName.push(this.rankData[item].dep_name);
        dataFleg.push((this.rankData[item].res * 100).toFixed(2));
      }
      // 销毁上一次实例
      echarts.init(document.getElementById('myChart1')).dispose();
      // 构建新实例
      let myChart = echarts.init(document.getElementById('myChart1'));
      this.myChart = myChart
      window.addEventListener('resize', function () {
        myChart.resize();
      });
      if (this.rankData.length) {
        myChart.setOption({
          toolbox: {
            feature: {
              saveAsImage: {
                name: '科室排名',
              },
            },
          },
          tooltip: {
            trigger: 'axis',
            axisPointer: {
              type: 'cross',
              crossStyle: {
                color: '#999',
              },
            },
          },
          legend: {
            show: true,
          },
          xAxis: {
            type: 'category',
            data: dataName,
            axisLabel: {
              rotate: 15,
            },
          },
          grid: {
            left: '3%',
            right: '3%',
            bottom: '3%',
            containLabel: true,
          },
          yAxis: {
            type: 'value',
            axisLabel: {
              show: true,
              interval: 0,
              formatter: '{value} %',
            },
          },
          series: [
            {
              data: dataFleg,
              type: 'bar',
              showBackground: true,
              barMaxWidth: 30,
              label: {
                show: true,
                position: 'top',
              },
              name: this.ruleName,
              backgroundStyle: {
                color: 'rgba(180, 180, 180, 0.2)',
              },
            },
          ],
        });
      } else {
        myChart.setOption({
          title: {
            text: '暂无数据',
            x: 'center',
            y: 'center',
            textStyle: {
              fontSize: 14,
              fontWeight: 'normal',
            },
          },
        });
      }
    },
    // 跳转列表页
    toPage(row, status) {
      const { start_time, end_time } = this.formInline;
      let path
      if (this.$route.path === '/embedIndex-home') {
        path = 'embedIndex-caseIndexAnalysisList'
      } else {
        path = '/caseIndexAnalysisList'
      }
      
      this.$router.push({ path, query: { ruleId: this.ruleId, ruleName: this.ruleName, start: start_time ? dateFormat(start_time, 'YYYYMMDD') : '', end: end_time ? dateFormat(end_time, 'YYYYMMDD') : '', dep_name: row.dep_name, status } });
    },
  },
};
</script>

<style lang="scss" scoped>
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

.rank-box {
  margin-bottom: 20px;
}
.table-box {
  .link {
    color: #409eff;
    font-weight: 600;
    cursor: pointer;
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
</style>